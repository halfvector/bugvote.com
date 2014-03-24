<?php namespace Bugvote\Core\Logging;

use Bugvote\Core\Reflector;


class LoggerDeferred implements ILogger
{
	protected $log;
	protected $rootDir = false;
	protected $rootNamespace = false;

	public function __construct()
	{
		$this->log = new LogSession();
	}

	public function write($message, $channel = "", $pop = 1)
	{
		$metadata = Reflector::GetFrameMetadata($pop);
		$timestamp = microtime(true);

		$entry = new LogEntry($channel, $timestamp, $message, $metadata);
		$this->log->entries []= $entry;
	}

	public function writeObject($message, $object, $channel = "", $pop = 1)
	{
		try
		{
			$objString = print_r($object, true);
		}
		catch(\Exception $e)
		{	// really desperate at this point
			$objString = "";
		}

		$this->write($message . "\n" . $objString, "", $pop + 1);
	}

	public function getLogSession()
	{
		// clean up the log before returning it
		foreach($this->log->entries as $entry)
		{
			if($this->rootDir)
				$entry->file = str_replace($this->rootDir, "", $entry->file);

			// strips out the namespace (AppTogether\) from the full method signature
			if($this->rootNamespace && (strpos($entry->method, $this->rootNamespace) === 0))
				$entry->method = substr($entry->method, strlen($this->rootNamespace));
		}

		// attach metadata
		$this->log->session = AppExecutionSession::getMetadata();

		return $this->log;
	}

	public function stripRootPath($rootDir) {
		$this->rootDir = $rootDir;
	}

	public function stripNamespace($ns) {
		$this->rootNamespace = $ns;
	}
}
