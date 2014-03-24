<?php

namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;
use \ZMQ;
use \ZMQContext;

class LogWriterZeroMQ implements ILogWriter
{
	/**
	 * @param $channel string
	 * @param $data object raw data to be logged (arrays, simple objects)
	 */
	public function Write($channel, $data)
	{
		try
		{
			$ctx = new ZMQContext();
			$socket = $ctx->getSocket(ZMQ::SOCKET_PUSH, 'log-writer');
			$socket->setSockOpt(ZMQ::SOCKOPT_LINGER, 2000);
			//$socket->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, 'log');
			$socket->setSockOpt(ZMQ::SOCKOPT_HWM, 10);
			$socket->connect("tcp://127.0.0.1:5557");

			$final = ['channel' => $channel, 'data' => $data];
			$final = json_encode($final);

			// if there is no PULLing daemon on the other side of this pipeline
			// the send() call would block, so we tell it to fail silently.
			// zeromq will queue up messages in the background
			//$socket->send($final, ZMQ::MODE_DONTWAIT);
			$socket->send($final);
		}
		catch(\Exception $e)
		{	// worth logging
			$msg = "Error writing to ZeroMQ socket: " . $e->getMessage();

			$metadata = Reflector::GetFrameMetadata(0);
			$line = "[Error] " . $metadata->file . ":" . $metadata->line . " " . $metadata->method . " " . $msg;

			var_dump($line);
			error_log($line);
		}
	}
}
