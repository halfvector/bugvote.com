<?php namespace Bugvote;

use Bugvote\Core\Logging\LogWriterRedis;
use Bugvote\Core\Logging\LogWriterUDP;
use Bugvote\Lib\Bootstrap;

// config preps paths and boots the autoloader
require "config.php";

// bootstrap + route dispatch + services injection + DB connect is about ~5ms
$bootstrap = Bootstrap::create(__DIR__);

// there is an entire module dedicated to this, but until i rewrite it, this is a handy short version of it:
// register a shutdown function to scrape the log & perf data for this session and write it out to a listener
// this takes ~4ms with non-blocking async udp
// about ~10ms with synchronous multi-exec redis
// but being done on shut-down, we don't delay the output to nginx in any way, so this delay doesn't count towards TTFB or full page load
register_shutdown_function(
	function () use ($bootstrap) {
		// collect event-log and performance-log
		$eventLog = $bootstrap->logger->getLogSession();
		$performanceLog = $bootstrap->perf->getPerformanceSession();

		// spawn a log-writer
		//$logRenderer = new LogWriterBrowser();
		//$logRenderer = new LogWriterRedis($bootstrap->ctx->redis);
		$logRenderer = new LogWriterUDP();

		$logRenderer->Write("log", $eventLog);
		$logRenderer->Write("performance", $performanceLog);
	}
);

$bootstrap->dispatch();
