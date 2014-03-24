<?php namespace Bugvote\Core\Logging;

class PerformanceSession
{
	public $session;
	public $entries = [];
}

class AppPerformanceLog implements IPerformanceLog
{
	protected $timers = [];
	protected $log = [];
	protected $epoch; // when time began

	protected $logStack = [];

	function __construct() {
		$this->epoch = $_SERVER['REQUEST_TIME_FLOAT'];
	}

	/**
	 * @param string $label
	 * @return AppPerformanceTimer
	 */
	function start($label) {
		$timer = new AppPerformanceTimer($this, $label);
		$this->timers []= $timer;
		return $timer;
	}

	/**
	 * @param AppPerformanceTimer $timer
	 * @param bool $andRemove
	 */
	function stop($timer, $andRemove = true) {
		// if the timer that is coming to a stop isn't at the top of the stack
		// we have a problem: a child timer failed to stop, so all child measurements are off
		if(!count($this->timers) || $this->timers[count($this->timers)-1] != $timer) {
			// sanity failure: can't stop this timer, the whole performance log should be dismissed
			echo "stop(); Sanity Failure: bad timer\n";

			// grab top timer on the stack
			if(count($this->timers))
			{
				$topTimer = $this->timers[count($this->timers)-1];
				var_dump("*** TOP TIMER ***");
				var_dump($topTimer->label);
				var_dump("*** DONE ***");
			}

			var_dump($this->timers);
			var_dump($timer);
			exit;
		}

		$label = $timer->label;
		$time = $timer->spanMillisec();
		$entry = new AppPerfLogEntry(($timer->start - $this->epoch) * 1000.0, $label, $time, $timer->meta);

		if($andRemove)
		{
			$lastTimer = array_pop($this->timers);
			if($lastTimer != $timer) {
				// sanity failure: stopping the wrong timer
				echo "Sanity Failure: wrong timer\n";
				exit;
				return;
			}

			// merge any children logs
			//if(count($timer->log) > 0)
			//	$this->log []= $timer->log;

			//$this->log []= new AppPerfLogEntry(($timer->start - $this->epoch) * 1000.0, $label, $time);

			$entry->children = $timer->log;
		}

		if(!count($this->timers))  {
			// no more timers, this was the last one (root timer)
			$this->log []= $entry;
		} else {
			// there are more timers on the stack, add this log entry to the top one
			$topTimer = $this->timers[count($this->timers)-1];
			$topTimer->log []= $entry;
			//echo "top timer log " . count($this->timers);
			//var_dump($topTimer->log);
		}
	}

	function next(AppPerformanceTimer $timer) {
		// if the timer that is coming to a stop isn't at the top of the stack
		// we have a problem: a child timer failed to stop, so all child measurements are off
		if(!count($this->timers) || $this->timers[count($this->timers)-1] != $timer) {
			// sanity failure: can't stop this timer, the whole performance log should be dismissed
			echo "next(); Sanity Failure: bad timer\n";

			if(count($this->timers))
			{
				$topTimer = $this->timers[count($this->timers)-1];
				var_dump("*** TOP TIMER ***");
				var_dump($topTimer->label);
				var_dump("*** DONE ***");
			}

			var_dump($this->timers);
			var_dump($timer);
			return;
		}

		$label = $timer->label;
		$time = $timer->spanMillisec();
		$entry = new AppPerfLogEntry(($timer->start - $this->epoch) * 1000.0, $label, $time, $timer->meta);

		// move the children log out of the timer so we can reuse it
		$entry->children = $timer->log;
		$timer->log = [];

		if(count($this->timers) <= 1)  {
			// no more timers, this was the last one (root timer)
			$this->log []= $entry;
		} else {
			// there are more timers on the stack, add this log entry to the top one
			$topTimer = $this->timers[count($this->timers)-2];
			$topTimer->log []= $entry;
		}
	}

	function dump() {
		//var_dump($this->log);

		//echo "<pre>";
		//$this->dumpTree($this->log);
		//echo "</pre>";

		$dump = $this->prettyDump($this->log);

		echo <<<EOT
		<div class="m-perf-bars">
			<div class="bar-container">
				$dump
				<div class="ruler-1ms"></div>
				<div class="ruler-10ms"></div>
			</div>
		</div>
EOT;
	}

	// returns a flat list of all events, useful for timeline visualization (eg: Chrome's Clockwork)
	function getEventsList() {
		$events = [];
		$this->flattenTree($this->log, $events);
		return $events;
	}

	function getPerformanceSession() {

		$log = new PerformanceSession();
		$log->session = AppExecutionSession::getMetadata();
		$log->entries = $this->log;

		return $log;
	}

	/**
	 * @param AppPerfLogEntry[] $nodes
	 * @param $events
	 * returns a flat list with timings in milliseconds
	 */
	protected function flattenTree($nodes, &$events) {
		foreach($nodes as $node) {
			$events []= ['start' => $node->offset, 'end' => $node->offset + $node->span, 'duration' => $node->span, 'description' => $node->label, 'meta' => $node->meta];
			$this->flattenTree($node->children, $events);
		}
	}

	/**
	 * @param AppPerfLogEntry[] $nodes
	 * @param int $indent
	 * @param int $parentOffset
	 * @return string
	 */
	function prettyDump($nodes, $indent = 0, $parentOffset = 0)
	{
		$str = "";
		$scale = 30; // 1 msec = 30 pixels

		foreach($nodes as $node)
		{
			$position = ($node->offset - $parentOffset) * $scale;
			$width = $node->span * $scale;
			$time = number_format($node->span, 2) . " msec";

			$special_label = "section-normal";
			if(strstr($node->label, "DAL "))
				$special_label = "section-dal";
			if(strstr($node->label, "Template Render"))
				$special_label = "section-render";
			if(strstr($node->label, "Controller run"))
				$special_label = "section-controller";


			$str .= "<div class=\"section depth-$indent $special_label\" style=\"left: {$position}px; width: {$width}px\">";
			$str .= $this->prettyDump($node->children, $indent + 1, $node->offset);

			$str .= <<<EOT
	<span class="name label-depth-$indent">
		<span class="title">$node->label <strong>$time</strong></span>
	</span>
	<span class="ptr ptr-start label-depth-$indent"></span>
	<span class="ptr ptr-end label-depth-$indent"></span>
EOT;


			//$str .= "<span class=\"name name-depth-$indent\"><span class=\"ptr\"></span>$node->label: $time</span>";
			$str .= "</div>";
		}

		return $str;
	}

	function dumpTree($nodes, $indent = 0)
	{
		foreach($nodes as $node)
		{
			echo str_repeat("+", $indent);
			echo number_format($node->offset, 2) . " msec " . $node->label . " " . number_format($node->span, 2) . " msec\n";

			$this->dumpTree($node->children, $indent + 1);
		}

		if(!$indent) {
			// root level nodes
			$total = 0;
			foreach($nodes as $node)
				$total += $node->span;
			echo "Total logged time: " . number_format($total, 2) . " msec\n";
		}
	}
}