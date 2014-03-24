<?php namespace Bugvote\Core\Renderer;

use Bugvote\Core\Logging\AppPerformanceLog;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Paths;
use Mustache_Engine;
use Mustache_Loader_FilesystemLoader;
use Mustache_Logger;
use Mustache_Logger_StreamLogger;

class MustacheRenderer implements IRenderer
{
	protected $logger;
	/** @var Paths */
	protected $paths;
	protected $perf;

	function __construct(Paths $paths, ILogger $logger, AppPerformanceLog $perf)
	{
		$this->logger = $logger;
		$this->paths = $paths;
		$this->perf = $perf;
	}

	function render($templateName, $data)
	{
		$p = $this->perf->start("Mustache Engine instantiation");

		$m = new Mustache_Engine([
				"cache" => $this->paths->AbsoluteTmpPath . '/mustache-cache',
				"cache_file_mode" => 0666,
				"loader" => new Mustache_Loader_FilesystemLoader($this->paths->AbsoluteAppPath . "/Views"),
				"partials_loader" => new Mustache_Loader_FilesystemLoader($this->paths->AbsoluteAppPath . '/Views'),

				'escape' => function($value) { return htmlspecialchars($value, ENT_COMPAT, 'UTF-8'); },
				'charset' => 'UTF-8',

				// spit out warnings to help locate missing partials in the php-fpm and nginx error logs
				'logger' => new Mustache_Logger_StreamLogger('php://stderr', Mustache_Logger::WARNING)
				//'logger' => new MustacheLexyLogger($this->logger, Mustache_Logger::WARNING)
			]
		);

		$m->perf = $this->perf;

		$p->next("Template load");

		$tpl = $m->loadTemplate($templateName);

		$p->next("Render to string");

		$output = $tpl->render($data);

		$p->next("Flush string");

		echo $output;

		$p->stop();
	}
}