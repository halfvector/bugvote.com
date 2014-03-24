<?php namespace Bugvote\Core;

use Bugvote\Lib\Bootstrap;
use Clockwork\DataSource\DataSource;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Symfony\Component\Console\Application;

class ClockworkDataSource extends DataSource
{
	//protected $timeline;
	protected $bootstrap;

	function __construct(Bootstrap $bootstrap)
	{
		$this->bootstrap = $bootstrap;
		//$this->timeline = new Timeline();
	}

	function resolve(Request $request)
	{
		$now = $_SERVER['REQUEST_TIME_FLOAT'];

		$timings = $this->bootstrap->perf->getEventsList();

		$timeline = [];

		foreach($timings as $timing) {
			$timing["start"] = ($timing["start"] / 1000 + $now);
			$timing["end"] = ($timing["end"] / 1000 + $now);

			//$timeline[$timing["description"]] = $timing;
			$timeline[] = $timing;
		}

		uasort($timeline, function($a, $b) {
			if($a['start'] > $b['start'])
				return 1;

			if($a['start'] == $b['start']) {
				if($a['end'] > $b['end'])
					return 1;
				elseif ($a['end'] < $b['end'])
					return -1;

				return 0;
			}

			return -1;
		});

		$queries = [];

		//var_dump($timeline);

		foreach($timeline as $item) {
			if(strstr($item["description"], "DAL")) {
				$queries []= ['query' => $item['meta']['query'], 'duration' => $item['duration']];
			}
		}

		usort($queries, function($a, $b){
			if($a['duration'] > $b['duration'])
				return 1;

			if($a['duration'] == $b['duration'])
				return 0;

			return -1;
		});

		$request->timelineData = $timeline;
		$request->databaseQueries = $queries;

		return $request;
	}

	function parsePerformanceData($perf)
	{
		var_dump($perf);
	}
}


class YourCustomDataSource extends DataSource
{
	protected $context;

	/**
	 * YourAppContext is something that links to your main web-app and contains useful data like timings, queries, and logs
	 */
	function __construct(YourAppContext $context)
	{
		$this->context = $context;
	}

	/**
	 * Clockwork will call this function when you do $clockwork->resolveRequest()
	 * so you will want $this->context to reference your web-app in some meaningful way so you can extract data on demand
	 */
	function resolve(Request $request)
	{
		$timings = $this->context->getTimings();

		// eg:
		// $timings[0] = ['start' => 1387208058.1, 'end' => 1387208058.5, 'duration' => 40, 'description' => 'parsing tweets']
		// where start & end are in seconds, and duration in milliseconds
		// start & end should also be relative to ($_SERVER['REQUEST_TIME_FLOAT'] * 1000) (hence those giant number of seconds)
		// so if your performance logging system makes timings relative to the app-start, you may want to add the app-start-time to it

		// you can also sort the timeline nicely:
		uasort($timeline, function($a, $b) {
			if($a['start'] > $b['start'])
				return 1;

			if($a['start'] == $b['start']) {
				if($a['end'] > $b['end'])
					return 1;
				elseif ($a['end'] < $b['end'])
					return -1;

				return 0;
			}

			return -1;
		});

		$queries = $this->context->getQueries();

		// eg:
		// $queries[0] = ['query' => "SELECT awesomeness FROM cereals WHERE name = `Captain Crunch`", 'duration' => 13]
		// where query is whatever you want to show, could be a short label or the entire SQL query
		//       and duration is in milliseconds

		$request->timelineData = $timeline;
		$request->databaseQueries = $queries;

		return $request;
	}
}
