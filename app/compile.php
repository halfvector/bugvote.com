<?php namespace Bugvote;

use Bugvote\Commons\ImageManager;
use Bugvote\Commons\SessionDataHelper;
use Bugvote\Commons\UrlResolver;
use Bugvote\Controllers\App\BugListController;
use Bugvote\Controllers\App\DashboardController;
use Bugvote\Controllers\App\GarageController;
use Bugvote\Controllers\App\RoadmapController;
use Bugvote\Controllers\App\SearchController;
use Bugvote\Controllers\AuthController;
use Bugvote\Controllers\HomeController;
use Bugvote\Controllers\UserSwitchController;
use Bugvote\Core\AssetManager;
use Bugvote\Core\DAL;
use Bugvote\Core\DAL\DatabaseSettings;
use Bugvote\Core\Permissions;
use Bugvote\Core\Renderer\MustacheRenderer;
use Bugvote\Core\UrlBuilder;
use Bugvote\Services\Context;
use Bugvote\Services\DataSigning;
use Bugvote\Services\UserSession;

require "config.php";
require "Lib/Bootstrap.php";

// 10ms overhead to just get the bootstrap up and running
$bootstrap = Lib\Bootstrap::create(__DIR__);

// for this to work I need to include all of the active controllers in here
// a simple bootstrap to wire up logging, perf, msging, and autoload will be perfect

function loadControllers()
{

}

// from https://github.com/chriso/Request/blob/master/lib/Request/Dispatcher.php
class Router
{
	/**
	 * A function, class or method annotation => "@annotation [arg]".
	 */
	const ANNOTATION = '/\* @(\S++)(?: (.++))?/';

	/**
	 * Used to compile routes into a regular expression.
	 */
	const ROUTE = '`([/.]?)\:([a-z]++)(\??)`i';

	/**
	 * Get all functions and classes with a `route` annotation.
	 *
	 * @return Array $routes - each route is `array($route, $function)`
	 */
	public function getRoutes() {
		return array_merge($this->getFunctionRoutes(), $this->getClassRoutes());
	}

	/**
	 * Find all user-defined functions with a `route` annotation.
	 *
	 * @return Array $routes - each route is `array($route, $function)`
	 */
	public function getFunctionRoutes() {
		$functions = get_defined_functions();
		$routes = array();
		foreach ($functions['user'] as $function) {
			$reflector = new \ReflectionFunction($function);
			$docblock = $reflector->getDocComment();

			$annotations = $this->parseAnnotations($docblock);
			if (!array_key_exists('route', $annotations)) {
				continue;
			}

			$route = $this->compileRoute($annotations['route']);
			$routes[] = array($route, $function, $annotations);
		}
		return $routes;
	}

	/**
	 * Find all user-defined classes and methods with a `route` annotation.
	 *
	 * @return Array $routes - each route is `array($route, $function)`
	 */
	public function getClassRoutes() {
		$routes = array();
		foreach (get_declared_classes() as $class) {
			$reflector = new \ReflectionClass($class);
			if (!$reflector->isUserDefined()) {
				continue;
			}

			$docblock = $reflector->getDocComment();
			$classAnnotations = $this->parseAnnotations($docblock);

			foreach ($reflector->getMethods() as $method) {
				$docblock = $method->getDocComment();
				$methodName = $method->getName();

				$annotations = $this->parseAnnotations($docblock);
				if (!array_key_exists('route', $annotations)) {
					continue;
				}

				//Merge class + method annotations
				$route = $classAnnotations['route'] . $annotations['route'];
				$annotations = array_merge($classAnnotations, $annotations);
				$annotations['route'] = $route;

				$route = $this->compileRoute($annotations['route']);
				$function = array($class, $methodName);
				$routes[] = array($route, $function, $annotations);
			}
		}
		return $routes;
	}

	/**
	 * Parse annotations from a docblock.
	 *
	 * @param String $docblock
	 * @return Array $annotations
	 */
	public function parseAnnotations($docblock) {
		$annotations = array();
		preg_match_all(static::ANNOTATION, $docblock, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$annotations[$match[1]] = isset($match[2]) ? $match[2] : null;
		}
		return $annotations;
	}

	/**
	 * Compile routes into a regular expression.
	 *
	 * @param String $route
	 * @return String $regex_route
	 */
	public function compileRoute($route) {
		preg_match_all(static::ROUTE, $route, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			list($pre, $block, $param, $optional) = $match;
			if ($block === '.') {
				$block = '\\.';
			}
			$block .= "(?<$param>[^/]++)";
			if ($optional) {
				$block = "(?:$block)?";
			}
			$route = str_replace($pre, $block, $route);
		}
		return $route;
	}

}

// include our controllers

function createTestingContext($bootstrap)
{
	$databaseSettings = DatabaseSettings::load("home-staging", $bootstrap->paths);

	$context = new Context();
	$context->timer = $bootstrap->perf;
	$context->perf = $bootstrap->perf;
	$context->paths = $bootstrap->paths;
	$context->log = $bootstrap->logger;

	$context->dal = new DAL($databaseSettings, $bootstrap->logger, $bootstrap->perf);;
	$context->renderer = new MustacheRenderer($bootstrap->paths, $bootstrap->logger, $bootstrap->perf);
	$context->session = new SessionDataHelper("defunc");
	$context->images = new ImageManager();
	$context->assetManager = new AssetManager($bootstrap->paths, $bootstrap->logger, $context->images);
	$context->resolver = new UrlResolver();
	$context->url = new UrlBuilder();
	$context->csrf = new DataSigning($context);
	$context->user = UserSession::Open($context);
	$context->permissions = new Permissions($context->dal, $bootstrap->logger, $context->user);

	return $context;
}

$ctx = createTestingContext($bootstrap);

$enabledControllers = [
	// App
	new DashboardController($ctx),
	new BugListController($ctx),
	new GarageController($ctx),
	new RoadmapController($ctx),
	new SearchController($ctx),

	// Home
	new UserSwitchController($ctx),
	new HomeController($ctx),
	new AuthController($ctx),
];

$router = new Router();
$routes = $router->getClassRoutes();
var_dump($routes);

foreach($routes as $route) {
	echo "  get: {$route[0]}\n";
	echo "  controller: {$route[1][0]} :: {$route[1][1]}\n";
	echo "\n";

	// run tests on controller
	//$obj = new $route[1][0]($ctx);
	//$obj->$route[1][1]($ctx);
}