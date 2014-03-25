<?php namespace Bugvote\Lib;

use Bugvote\Commons\ImageManager;
use Bugvote\Commons\UrlResolver;
use Bugvote\Core\AssetManager;
use Bugvote\Core\AuditFacade;
use Bugvote\Core\ClockworkDataSource;
use Bugvote\Core\DAL\DatabaseSettings;
use Bugvote\Core\DAL;
use Bugvote\Core\Errors\MissingRouteControllerException;
use Bugvote\Core\Errors\MissingRouteControllerMethodException;
use Bugvote\Core\IAudit;
use Bugvote\Core\Logging\AppPerformanceLog;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Logging\LogEntriesCom;
use Bugvote\Core\Logging\LoggerDeferred;
use Bugvote\Core\ObjectCache;
use Bugvote\Core\Paths;
use Bugvote\Core\Permissions;
use Bugvote\Core\Reflector;
use Bugvote\Core\Renderer\MustacheRenderer;
use Bugvote\Core\RequestVariables;
use Bugvote\Core\RouteProcessor;
use Bugvote\Core\UrlBuilder;
use Bugvote\Services\Context;
use Bugvote\Services\DataSigning;
use Bugvote\Services\UserSession;

// external libs
use Clockwork\Clockwork;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\Storage\FileStorage;
use AltoRouter;
use Redis;


class Bootstrap
{
	/** @var ObjectCache */		    protected	$cache			= null;
	/** @var Paths */			    public	    $paths			= null;
	/** @var IAudit */			    protected	$auditProvider	= false;
	/** @var AuditFacade */		    protected	$auditFacade	= false;
	/** @var LogEntriesCom */       public      $logentries     = false;

	/** @var AppPerformanceLog */   public $perf;
	/** @var ILogger */             public $logger;

	// the core framework bootstrap, nothing app-specifics here
	public static function create($appPath)
	{
		$bootstrap = new Bootstrap();

		// then performance and logging
		// then default services (sessions, time, etc)

		$perf = new AppPerformanceLog();
		$p = $perf->start("Bootstrapping");

		session_start();
		date_default_timezone_set('UTC');
		set_include_path(get_include_path() . PATH_SEPARATOR . $appPath);

		$p1 = $p->fork("Core Services");

		// error handling
		$logger = new LoggerDeferred();
		$logger->stripRootPath(BUGVOTE_APP);
		$logger->stripNamespace('Bugvote\\');

		// advanced logging
		// hooks on script-exit, catches all errors, does its work after the user-request has been fully flushed out
		//ErrorManager::registerHandlers($this->ctx);

		$logger->write("Deferred Logger started");

		$bootstrap->paths = new Paths(BUGVOTE_ROOT);
		$bootstrap->cache = new ObjectCache($logger);
		$bootstrap->perf = $perf;
		$bootstrap->logger = $logger;
		$bootstrap->router = new AltoRouter();

		$p1->stop();

		if(isset($_SERVER["REQUEST_URI"])) {
			// handling web-request
			$logger->write("Handling URI: {$_SERVER["REQUEST_URI"]}");
		} elseif (isset($_SERVER["argv"])) {
			$logger->write("Handling URI: {$_SERVER["argv"][0]}");
		}

		$p->stop();
		return $bootstrap;
	}

	/** @var AltoRouter */
	protected $router;

	public function registerRoutes()
	{
		$p = $this->perf->start("Route load");

		// load (probably cached) routes
		$routeProcessor = new RouteProcessor($this->paths->AbsoluteAppPath, $this->cache);
		$routes = $routeProcessor->getRoutes();

		$p->next("Route setup");

		// register routes
		foreach($routes as $route)
			$this->router->map($route['verb'], $route['path'], '\\Bugvote\\Controllers\\' . $route['class'] . "Controller#" . $route['method'], $route['name']);

		$p->stop();
	}

	/** @var Context */
	public $ctx;

	// new executeRoute() replacement
	public function runController($target, $params, &$rendered = false)
	{
		$p = $this->perf->start("Route run: $target()");

		$p1 = $p->fork("DB Config load");

		$mysqlConf = DatabaseSettings::load("mysql", $this->paths);

		$p1->next("Context bootstrap");

		$context = new Context();
		$context->perf = $this->perf;
		$context->log = $this->logger;
		$context->paths = $this->paths;
		$context->router = $this->router;

		$p2 = $p1->fork("MariaDB + Redis + ObjectCache");

		$context->cache = new ObjectCache(null);
		$context->dal = new DAL($mysqlConf, $this->logger, $this->perf);
		$context->redis = new Redis();

		$context->dal->connect();
		$context->redis->pconnect("/var/run/redis/redis.sock", 0, 60000);

		$p2->next("Mustache + Url + Assets");
		$context->renderer = new MustacheRenderer($this->paths, $this->logger, $this->perf);
		$context->images = new ImageManager();
		$context->assetManager = new AssetManager($this->paths, $this->logger, $context->images);
		$context->resolver = new UrlResolver();
		$context->url = new UrlBuilder();
		$context->csrf = new DataSigning($context);
		$context->parameters = new RequestVariables($params);

		$p2->next("Restoring session");
		$context->user = UserSession::Open($context);
		$context->permissions = new Permissions($context->dal, $this->logger, $context->user);
		$p2->stop();

		$this->ctx = $context;

//		$p1->next("Set up analytics");

//		// segment.io analytics system
//		$context->logentries = LogEntriesCom::getLogger("~hash~", true, false, LOG_DEBUG);
//		Analytics::init("~hash~");

//		// setup user data
//		Analytics::identify(15, [
//			"email"        => "aleckz@gmail.com",
//			"name"         => "Alex Barkan",
//			"type"		   => "SysOp"
//		]);

//		$context->metrics = new LibratoClient("aleckz@gmail.com", "~hash~");

		$p1->next("Controller run");

		$context->log->write("Route: $target");

		list($className, $methodName) = explode("#", $target);

		$context->session = $context->user->newSegment("$className");

		// these errors are common enough to have their own handler

		if(!class_exists($className))
		{
			$this->logger->write("ERROR: \"$className\" class not found for route $target");
			throw new MissingRouteControllerException("$target", $className, $methodName, "Cannot route \"$target\" because class not found: $className'");
		}

		// spawn root VM and give it our bootstrap
		// failure here should appear as a 404 to the user: couldn't find the correct resource to handle the request!
		$instance = new $className($context);

		if(!method_exists($instance, $methodName))
		{
			$this->logger->write("ERROR: \"$methodName\" method not found in $target");
			throw new MissingRouteControllerMethodException("$className::$methodName", $target, $methodName, "Cannot route \"$className::$methodName\" because method $methodName not found in class: '$target'");
		} else
		{
			$instance->$methodName($context);
		}

		$p1->stop();
		$p->stop();

		$rendered = $context->rendered;
	}

	public $clockworkEnabled = false;

	// resolves a route and calls executeRoute() to bootstrap a context and use it to execute a controller
	function dispatch()
	{
		$p = $this->perf->start("Route Map build");
		$this->registerRoutes();

		if( $this->clockworkEnabled ) {
			// dump performance/trace data to clockwork
			$clockworkPath = $this->paths->AbsoluteTmpPath . "/clockwork";
			$clockwork = new Clockwork();
			header("X-Clockwork-Id: " . $clockwork->getRequest()->id);
			header("X-Clockwork-Version: " . Clockwork::VERSION);

			$clockworkMiddleware = new ClockworkDataSource($this);

			$clockwork->addDataSource(new PhpDataSource());
			$clockwork->addDataSource($clockworkMiddleware);
			$clockwork->setStorage(new FileStorage($clockworkPath));
		}

		$p->next("Route dispatch");

		$rendered = false;

		$match = $this->router->match();
		if($match)
		{   // run the matching controller
			$this->runController($match["target"], $match["params"], $rendered);
		} else
		{   // no controller found. show 404.
			$renderer = new MustacheRenderer($this->paths, $this->logger, $this->perf);
			$renderer->render('Errors/404', []);
		}

		$p->stop();

		// only draw perf data when rendering a page (eg, not handling POST or redirecting)
		if($rendered)
			$this->perf->dump();

		if(isset($clockwork) && !strstr($match["target"], "Clockwork")) {
			$clockwork->resolveRequest();
			$clockwork->storeRequest();
		}
	}
}
