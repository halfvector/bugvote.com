<?php namespace Bugvote\Core;

use Bugvote\Core\Logging\AppExecutionSession;
use Bugvote\Core\Logging\ILogWriter;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Logging\LogSession;
use Bugvote\Core\Logging\LogWriterIPC;
use Bugvote\Core\Logging\LogWriterUDP;
use Bugvote\Core\Logging\LogWriterZeroMQ;
use Bugvote\Core\Logging\LoggerFailSafe;

// this is a global service
class ErrorManager
{
	/** @var ILogger */
	protected static $failsafeLogger = false;

	/** @var IAudit */
	protected static $audit = false;

	public static function setFailSafeLogger($failsafeLogger) {
		self::$failsafeLogger = $failsafeLogger;
	}

	public static function getFailsafeLogger()
	{
		if(!self::$failsafeLogger)
			self::$failsafeLogger = new LoggerFailSafe();

		return self::$failsafeLogger;
	}

	// special global hook to help handle library-errors
	public static function OnLibraryError($message)
	{
		$metadata = Reflector::GetFrameMetadata(1);
		$timestamp = microtime(true);

		// but most importantly, write immediately to a system-level log
		$line = "[Library Error] $timestamp $metadata->file:$metadata->line $metadata->method $message";
		error_log($line);
	}

	/**
	 * @param $audit IAudit
	 */
	public static function registerHandlers($audit)
	{
		self::$audit = $audit;

		// convert errors into exceptions
		set_error_handler('\Bugvote\Core\ErrorManager::onUnhandledError');

		// monitor exceptions
		set_exception_handler('\Bugvote\Core\ErrorManager::onUnhandledException');

		// check if an error occured before shutdown -- catches fatal/parse errors
		register_shutdown_function('\Bugvote\Core\ErrorManager::onShutdown');
	}

	/**
	 * routes an error into an exception
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @throws \ErrorException
	 * @throws \OutOfRangeException
	 */
	public static function onUnhandledError($errno, $errstr, $errfile, $errline)
	{
		$rawTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$simpleTrace = self::SimplifyBacktrace($rawTrace);

		$err = ["msg" => $errstr, "source" => "$errfile:$errline", "trace" => $simpleTrace];

		// note the error, even if it gets handled
		self::HandleFirstChanceError($err);

		/*
		if( $errno == 8 )
		{	// out of range error
		throw new OutOfRangeException($errstr, 0);
		}
		*/

		throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
	}

	/**
	 * last-chance for unhandled exceptions
	 * @param \Exception $err
	 */
	public static function onUnhandledException($err)
	{
		$unrolledException = self::UnrollException($err);
		self::HandleLastChanceError($unrolledException);
	}

	// possibly handled error
	protected static function HandleFirstChanceError($err)
	{
		if(!self::$audit)
			return;

		$logger = self::$audit->getLogger();
		$logger && $logger->writeObject("First Chance Exception", $err);

		//$errStr = print_r($err, true);
		//echo "<pre>[ ] First Chance Exception:\n$errStr</pre>\n";
	}

	// unhandled error
	protected static function HandleLastChanceError($err)
	{
		if(!self::$audit)
			return;

		$logger = self::$audit->getLogger();
		$logger && $logger->writeObject("Last Chance Unhandled Exception", $err);

		$errStr = print_r($err, true);
		echo "<pre>[!] Unhandled Exception:\n$errStr</pre>\n";
	}

	public static function onShutdown()
	{
		$isError = false;

		if ($error = error_get_last()) {
			switch($error['type']){
				case E_ERROR:
				case E_PARSE:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					$isError = true;
					break;
			}
		}

		// check if shutdown was CAUSED by an unrecoverable error
		if ($isError) {
			// write to the fail-safe log
			self::getFailsafeLogger()->writeObject("Unhandled Exception ended execution prematurely", $error);

			var_dump("Unhandled Exception");
			var_dump($error);

			// write to deferred log
			$logger = self::$audit->getLogger();
			$logger && $logger->writeObject("Unhandled Exception ended execution prematurely", $error);
		}

		// tell fcgi to finish the request, and not wait the extra msec for audit to flush
		// this doesn't increase throughput but does decrease latency by an insignificant bit
		//fastcgi_finish_request();

		// tell auditing system that we are done, all errors were trapped, logs can now be sent out
		self::$audit && self::$audit->commitAll();
	}

	/**
	 * @param \Exception $err
	 * @return array
	 */
	public static function UnrollException($err)
	{
		$rawTrace = $err->getTrace();

		$msg = $err->getMessage();
		$trace = self::SimplifyBacktrace($rawTrace);

		return [get_class($err), $msg, $trace];
	}

	public static function SimplifyBacktrace($rawTrace)
	{
		$trace = [];

		foreach($rawTrace as $frame)
		{
			$line = self::GetFrameAsString($frame);

			$trace []= $line;
		}

		return $trace;
	}

	public static function GetFrameAsString($frame)
	{
		if(isset($frame["file"]))
			$fileSource = $frame["file"] . ":" . $frame["line"];
		else
			$fileSource = "(unknown):(unknown)";

		if(isset($frame["class"]))
			$codeSource = $frame["class"] . $frame["type"] . $frame["function"];
		else
			$codeSource = $frame["function"];

		//return [$fileSource, $codeSource];
		return "$codeSource @ $fileSource";
	}
}
