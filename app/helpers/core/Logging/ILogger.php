<?php

namespace Bugvote\Core\Logging;

interface ILogger
{
	//public function write($message);
	//public function writeObject($message, $object);
	public function getLogSession(); // get the LogSession object

	public function write($message, $channel = "", $pop = 1);
	public function writeObject($message, $object, $channel = "", $pop = 1);
}
