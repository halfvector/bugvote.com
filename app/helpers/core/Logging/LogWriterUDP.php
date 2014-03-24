<?php

namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;

// fast, non-blocking comparable to zeromq, no backlogging, and reliable enough (never seen it drop under stress tests)
// write-errors will be ignored, this is logger provides no guarantees.
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

			$msgDetails = ['size' => strlen($final)];
			$msgDetails = json_encode($msgDetails);

			# if the target host isn't listening, this won't generate any errors
			$fp = @pfsockopen("udp://127.0.0.1", 5555);
			@stream_set_blocking($fp, 0);
			@fwrite($fp, $msgDetails);
			@fwrite($fp, $final);
			@fflush($fp);
			@fclose($fp);
		}
		catch(\Exception $e)
		{
			$msg = "Error writing to socket: " . $e->getMessage();
			$metadata = Reflector::GetFrameMetadata(0);
			$line = "[Error] " . $metadata->file . ":" . $metadata->line . " " . $metadata->method . " " . $msg;

			error_log($line);
		}
	}
}
