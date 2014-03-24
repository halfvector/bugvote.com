<?php

namespace Bugvote\Core\Logging;

class LogEntry
{
	function __construct($channel, $timestamp, $message, $metadata)
	{
		$this->file = $metadata->file;
		$this->line = $metadata->line;
		$this->method = $metadata->method;
		$this->message = $message;
		$this->timestamp = $timestamp;
		$this->channel = $channel;
	}

	/** @var string */
	public $file;
	/** @var int */
	public $line;
	/** @var string */
	public $method;
	/** @var string */
	public $message;
	/** @var int */
	public $timestamp; // relative to the start of the log
	/** @var string */
	public $channel = "log"; // default channel is called "log". could have: debug, error, fatal error, performance
}