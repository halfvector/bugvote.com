<?php namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;
use Bugvote\Services\Context;
use Redis;

class LogWriterRedis implements ILogWriter
{
	/** @var Redis */
	protected $redis;

	function __construct($redis)
	{
		$this->redis = $redis;
	}

	/**
	 * @param $channel string
	 * @param $data object raw data to be logged (arrays, simple objects)
	 */
	public function Write($channel, $data)
	{
		try
		{
			//$start = microtime(true);
			$final = ['channel' => $channel, 'data' => $data];
			$final = json_encode($final);

			$this->redis->rPush($channel, $final);
			//$span = microtime(true) - $start;
			//var_dump("redis write timespan: " . number_format($span * 1000, 3) . " msec " . " for " . strlen($final) . " bytes");
		}
		catch(\Exception $e)
		{	// worth logging
			$msg = "Error writing to redis: " . $e->getMessage();
			$metadata = Reflector::GetFrameMetadata(0);
			$line = "[Error] " . $metadata->file . ":" . $metadata->line . " " . $metadata->method . " " . $msg;
			var_dump($line);
		}
	}
}
