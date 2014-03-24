<?php namespace Bugvote\Core\Logging;

interface IPerformanceLog
{
	/**
	 * @param string $label
	 * @return AppPerformanceTimer
	 */
	function start($label);
}