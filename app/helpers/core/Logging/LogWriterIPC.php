<?php

namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;
use \ZMQ;
use \ZMQContext;

class LogWriterIPC implements ILogWriter
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
			//$final = serialize($final);
			//$final = var_export($final, true);

			$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
			socket_set_nonblock($socket);
			socket_sendto($socket, $final, strlen($final), 0, "/tmp/logwriter.sock");
			socket_close($socket);
		}
		catch(\Exception $e)
		{	// worth logging
			$msg = "Error writing to socket: " . $e->getMessage();

			$metadata = Reflector::GetFrameMetadata(0);
			$line = "[Error] " . $metadata->file . ":" . $metadata->line . " " . $metadata->method . " " . $msg;

			var_dump($line);
			error_log($line);
		}
	}
}
