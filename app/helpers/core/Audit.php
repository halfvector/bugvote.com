<?php namespace Bugvote\Core;


use Bugvote\Core\Logging\AppExecutionSession;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Logging\LogSession;
use Bugvote\Core\Logging\LogWriterBrowser;
use Bugvote\Core\Logging\LogWriterUDP;
use Pheanstalk_Pheanstalk;

/**
 * This Audit Adapter pushes messages over a fast queue
 * the messages get logged into a database on the other side asynchronously
 * this helps keep the whole system light
 */
class Audit implements IAudit
{
	/** @var ILogger */ protected $logger;
	/** @var PerformanceLog */ protected $timer;

	/** @return ILogger */
	function getLogger()
	{
		return $this->logger;
	}

	/** @return PerformanceLog */
	function getTimer()
	{
		return $this->timer;
	}

	/**
	 * @param $logger ILogger
	 * @param $timer PerformanceLog
	 */
	function __construct($logger, $timer)
	{
		$this->logger = $logger;
		$this->timer = $timer;
	}

	/**
	 * commit all logs and performance timings
	 * pushes them to a file, or to another machine
	 */
	function commitAll()
	{
		$logWriter = new LogWriterUDP();
        $logRenderer = new LogWriterBrowser();

		if($this->logger)
		{
			$start = microtime(true);

			/*
			// slow (5ms)
			$log = $this->logger->getLog();

			// blazing fast (1.5ms) once warm
			$pheanstalk = new Pheanstalk_Pheanstalk('127.0.0.1');
			$pheanstalk
				->useTube('testtube')
				->put(json_encode($log));
			*/

			$span = (microtime(true) - $start) * 1000;
			echo "sending out logs took $span msec<br>\n";

            //$logRenderer->Write("log", $log);
            //var_dump($log);
		}

		if($this->timer)
		{
			$start = microtime(true);
			$log = new LogSession();
			$log->entries = $this->timer->getCompactData();
			$log->session = AppExecutionSession::getMetadata();

			$logWriter->Write("performance", $log);
			$span = (microtime(true) - $start) * 1000;
			//echo "sending out timings took $span msec<br>\n";
		}
	}
}

class Audit3
{
	/** @var ILogger */
	protected static $logger;

	public static function setLogger($logger) {
		self::$logger = $logger;
	}

	public $log = true;

	public static function write($message) {
		self::$log && self::$logger->write($message);
	}

	public static function writeObject($message, $object) {
		self::$log && self::$logger->writeObject($message, $object);
	}
}