<?php

namespace Bugvote\Core\Logging;

interface ILogWriter
{
	public function Write($channel, $data);
}
