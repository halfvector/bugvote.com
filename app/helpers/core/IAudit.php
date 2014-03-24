<?php

namespace Bugvote\Core;

use Bugvote\Core\Logging\ILogger;

interface IAudit
{
	/**
	 * @param $logger ILogger
	 * @param $timer PerformanceLog
	 */
	function __construct($logger, $timer);

	/** @return ILogger */
	function getLogger();

	/** @return PerformanceLog */
	function getTimer();

	/**
	 * commit all logs and performance timings
	 * pushes them to a file, or to another machine
	 */
	function commitAll();
}