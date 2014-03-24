<?php namespace Bugvote\Core\Logging;

class AppPerformanceTimer {

	/** @var AppPerformanceLog */
	protected $parent;
	public $label, $start, $stop;

	/** @var AppPerformanceTimer[] */
	public $log = [];
	public $meta;

	function __construct($parent, $label) {
		$this->parent = $parent;
		$this->label = $label;
		$this->start = microtime(true);
	}

	/**
	 * @param string $label
	 * @return AppPerformanceTimer
	 */
	function fork($label) {
		return $this->parent->start($label);
	}

	function stop() {
		if(!$this->parent) {
			// sanity failure: parent is invalid; probably because stop() was already called.
			return;
		}

		$this->stop = microtime(true);
		$this->parent->stop($this);
		$this->parent = null;
	}

	// chain timers
	function next($label) {
		$this->stop = microtime(true);
		$this->parent->next($this, false);
		$this->start = $this->stop;
		$this->label = $label;
	}

	function spanMillisec() {
		return ($this->stop - $this->start) * 1000.0;
	}

	function setMetadata($meta) {
		$this->meta = $meta;
	}
}