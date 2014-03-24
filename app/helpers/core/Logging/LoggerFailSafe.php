<?php

namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;
use Exception;

/**
 * Class LoggerFailSafe
 * writes to disk immediately. uses php's built in error_log()
 * good for fatal-errors and logging errors about the error-handling mechanism itself
 */
class LoggerFailSafe implements ILogger
{
	/** @var LogSession */
	protected $log = [];

	public function __construct()
	{
		$this->log = new LogSession();
	}

	public function write($message, $channel = "", $pop = 1)
	{
		$metadata = Reflector::GetFrameMetadata(1);
		$timestamp = microtime(true);

		// but most importantly, write immediately to a system-level log
		$line = "[Emergency] $timestamp $metadata->file:$metadata->line $metadata->method $message";
		error_log($line);
	}

	public function writeObject($message, $object, $channel = "", $pop = 1)
	{
		try
		{
			$objString = print_r($object, true);
		}
		catch(Exception $e)
		{	// really desperate at this point
			$objString = "(unserializable object)";
		}

		$metadata = Reflector::GetFrameMetadata(1);
		$timestamp = microtime(true);

		$line = "[Emergency] $timestamp $metadata->file:$metadata->line $metadata->method $message\n$objString";
		error_log($line);
	}

	public function getLogSession()
	{
		return $this->log;
	}
}