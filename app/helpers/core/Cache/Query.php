<?php namespace Bugvote\Core\Cache;

use Bugvote\Core\DAL;

class Query
{
	/** @var QueryCacheServices */
	protected $services;

	public $bypassCache = false;
	public $overrideCache = false;
	public $dependencies = [];

	function __construct(QueryCacheServices $services, $query, $parameters, $label, $dependencies = [])
	{
		$this->services = $services;

		$this->label = $label;
		$this->query = $query;
		$this->parameters = $parameters;
		$this->dependencies = $dependencies ?: [];
	}

	protected function execute($dalMethod, $type)
	{
		// early out when bypassing cache
		if($this->bypassCache)
			return $this->services->dal->$dalMethod($this->query, $this->parameters, $this->label);

		/*
		 * for a fast but complex idea-details query that generates a nicely sized object blob
		 * hash generation: 0.02 ms
		 * cache miss: 0.30 ms
		 * db query: 1.40 ms
		 * cache save + dependency building: 0.55 ms
		 *
		 * cache hit: 0.30 ms
		 *
		 * so about 0.90 ms overhead for a cache-miss (50% overhead)
		 * but saves 1.1 ms for every cache-hit
		 *
		 * on a busy server, the hit-to-miss ratio should be 10:1 at least
		 * so (1.4 ms * 11) / (0.30 * 10 + 2.3 ms * 1) = 15.4ms uncached / 5.3ms 10:1 cached = 3x speedup
		 *
		 * what if ratio is 100:1?
		 * 141.4 ms / (30 + 2.3) = 4x speedup
		 *
		 * what if the hit-to-miss ratio is only 2:1?
		 *
		 * 2.8 ms / (0.3 + 2.3) = 0.07x speedup
		 *
		 * so even if the cache is hit only once in its entire life, we win!
		 */

		$timer = $this->services->auditor->startTimer("Cacheble query [$type $this->label]");

		$paramHash = "";
		foreach($this->parameters as $key => $value)
			$paramHash .= "+$key=$value";

		$queryHash = $this->label . " [#" . crc32($this->query . $paramHash) . "]";

		if(!$this->overrideCache)
		{
			$cached = $this->services->cache->get($queryHash);

			if($cached !== FALSE)
			{
				$timer->save();
				return $cached;
			}
		}

		$timer->mark("Cache miss");

		// fetch result from db
		$result = $this->services->dal->$dalMethod($this->query, $this->parameters, $this->label);

		$timer->resetTimer();

		// update cache
		$this->services->cache->cache($queryHash, $result);

		// append cache dependencies
		foreach($this->dependencies as $dependency)
			$this->services->cache->createCacheDependency($dependency, $queryHash);


		$timer->mark("Cache updated");
		$timer->save();

		return $result;
	}

	function getMultipleObjects()
	{
		return $this->execute("fetchMultipleObjs", "select.m");
	}

	function getSingleValue()
	{
		return $this->execute("fetchSingleValue", "select.1v");
	}

	function getSingleObject()
	{
		return $this->execute("fetchSingleObj", "select.1");
	}

	function getSingleRow()
	{
		return $this->execute("fetchSingleRow", "select.1");
	}

	/**
	 * add a cache dependency. chainable.
	 * @param $dependency
	 * @return Query
	 */
	function dependsOn($dependency)
	{
		$this->dependencies []= $dependency;
		return $this;
	}

	function bypassCache()
	{
		$this->bypassCache = true;
		return $this;
	}

	function overrideCache()
	{
		$this->overrideCache = true;
		return $this;
	}
}
