<?php namespace Bugvote\Core;

class PerformanceLog
{
	public $name, $start, $entries = [];
	public $bytes = 0; // (optional) for throughput measurement

	public function __construct($name)
	{
		$this->name = $name;
		$this->start = microtime(true);
		$this->last = $this->start;
	}

	public function addChild($child)
	{
		// import last timestamp from child to reset timer without calling microtime()
		$this->last = $child->last;

		$this->entries []= $child;
	}

	// tells log to ignore previous events, and have next mark() measure a precise timing
	public function resetTimer()
	{
		$this->last = microtime(true);
	}

	// as fast as possible
	public function mark($str, $metadata = null)
	{
		if( ($self = self::current()) != $this )
		{   // mark in the current context
			return $self->mark($str, $metadata);
		}

		$now = microtime(true);
		$span = ($now - $this->last) * 1000;
		$this->entries []= [ "source" => $str, "time" => $span, "metadata" => $metadata ];
		$this->last = $now;

		return $span;
	}

	// a special override to manually add our own entries. used to import special timings.
	public function writeEntry($str, $spanSec)
	{
		if( ($self = self::current()) != $this )
			return $self->writeEntry($str, $spanSec);

		$this->entries []= [ "source" => $str, "time" => $spanSec * 1000, "metadata" => null ];
	}

	public function create($name, $pop = 1)
	{
		return self::start($name, $pop);
	}

	public static function start($name, $pop = 1)
	{
		$perf = new PerformanceLog($name);
		self::$performanceStack []= $perf;
		return $perf;
	}

	public function save()
	{
		//Log::Write("Close: $this->name", 1);

		// if nothing was marked, then mark the save() call
		//if( $this->last == $this->start )
		$this->last = microtime(true);

		$this->finish();
	}

	// TODO: this violates DI and should be rewritten
	public static function current()
	{
		$parentLog = end(self::$performanceStack);
		return $parentLog;
	}

	protected function finish()
	{
		$perf = array_pop(self::$performanceStack);

		if( $perf != $this )
		{	// how can this happen?
			// closed a parent perf-counter before the child counter?
			ErrorManager::OnLibraryError("Sanity Failure: \$perf != \$this");
			exit;
		}

		// if we have a perf log on stack, add this one as its child
		$parentLog = end(self::$performanceStack);

		if( $parentLog )
			$parentLog->addChild($this);
	}

	protected static $performanceStack = [];

	public function getCompactData()
	{
		/*
		$data = [
			'trace' => $this->compact(),
			'remote_addr' => isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "",
			'completed' => microtime(true),
		];
		*/

		return $this->compact();
	}

	protected function compact()
	{
		$children = [];

		// special case: no entries
		if( count($this->entries) == 0 )
		{   // use title to create a single-entry box
			$this->entries []= (object) [ "source" => "", "time" => ($this->last - $this->start) * 1000, "metadata" => null ];
		}

		foreach( $this->entries as $entry )
		{
			if( $entry instanceof PerformanceLog )
			{
				$children []= $entry->compact();
			} else
			{
				$children []= (object) $entry;
			}
		}

		return (object) ['name' => $this->name, 'children' => $children, 'last' => $this->last, 'start' => $this->start];
	}
}