<?php

namespace Bugvote\Core\Logging;

class LogSession
{
	/** @var AppExecutionSession */
	public $session; // uuid and other useful info for the log's session

	/** @var LogEntry[] */
	public $entries = [];
}
