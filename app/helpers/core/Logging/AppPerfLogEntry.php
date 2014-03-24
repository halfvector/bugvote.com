<?php namespace Bugvote\Core\Logging;

class AppPerfLogEntry
{
	function __construct($offset, $label, $span, $meta = null)
	{
		$this->offset = $offset;
		$this->label = $label;
		$this->span = $span;
		$this->meta = $meta;
	}

	public $meta;
	public $offset, $label, $span;
	public $children = []; // children log entries (yey tree timing)
}