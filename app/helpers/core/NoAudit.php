<?php

namespace Bugvote\Core;


use Bugvote\Core\Logging\ILogger;

class NoAudit implements IAudit
{
	/** @return ILogger */
	function getLogger()
	{
		return null;
	}

	/** @return PerformanceLog */
	function getTimer()
	{
		return null;
	}

	/**
	 * @param $logger ILogger
	 * @param $timer PerformanceLog
	 */
	function __construct($logger = null, $timer = null)
	{

	}

	/**
	 * commit all logs and performance timings
	 * pushes them to a file, or to another machine
	 */
	function commitAll()
	{

	}
}