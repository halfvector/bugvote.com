<?php

namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;
use \ZMQ;
use \ZMQContext;

// fast, non-blocking comparable to zeromq, no backlogging, and reliable enough (never seen it drop under stress tests)
class LogWriterUDP implements ILogWriter
{
	/**
	 * @param $channel string
	 * @param $data object raw data to be logged (arrays, simple objects)
	 */
	public function Write($channel, $data)
	{
		try
		{
			$final = ['channel' => $channel, 'data' => $data];
			$final = json_encode($final);

			//$queue = msg_get_queue(5150);
			//msg_send($queue, 1, $final, false, false);

			$msgDetails = ['size' => strlen($final)];
			$msgDetails = json_encode($msgDetails);

			$start = microtime(true);

			$fp = pfsockopen("udp://127.0.0.1", 5555);
			stream_set_blocking($fp, 0);
			fwrite($fp, $msgDetails);
			fwrite($fp, $final);
			fflush($fp);
			fclose($fp);

			$span = microtime(true) - $start;
			var_dump("udp write timespan: " . number_format($span * 1000, 3) . " msec");
		}
		catch(\Exception $e)
		{	// worth logging
			$msg = "Error writing to socket: " . $e->getMessage();

			$metadata = Reflector::GetFrameMetadata(0);
			$line = "[Error] " . $metadata->file . ":" . $metadata->line . " " . $metadata->method . " " . $msg;

			//var_dump($line);
			//error_log($line);
		}
	}
}
