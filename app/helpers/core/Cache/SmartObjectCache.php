<?php namespace Bugvote\Core\Cache;

use Bugvote\Core\AuditFacade;
use Predis\Client;
use Redis;

class SmartObjectCache implements IObjectCache
{
	protected $audit;
	protected $redis;
	protected $inverseCacheIndex = [];

	function __construct(AuditFacade $facade)
	{
		$this->audit = $facade;
		//$this->redis = new Client("tcp://127.0.0.1:6379", [ 'connections' => [ 'tcp'  => 'Predis\Connection\PhpiredisConnection', 'unix' => 'Predis\Connection\PhpiredisStreamConnection' ] ]);
		//$this->redis = new Client("unix://var/run/redis/redis.sock", [ 'connections' => [ 'tcp'  => 'Predis\Connection\PhpiredisConnection', 'unix' => 'Predis\Connection\PhpiredisStreamConnection' ] ]);
		//$this->redis = phpiredis_connect("/var/run/redis/redis.sock");
		//$this->redis = phpiredis_connect("127.0.0.1:6379");

		$this->redis = new Redis();
		$this->redis->connect("/var/run/redis/redis.sock");

		$facade->markTiming("Redis connect");
	}

	function createCacheDependency($dependencyKey, $objectCacheKey)
	{
		//$start = microtime(true);
		$this->redis->sAdd($dependencyKey, $objectCacheKey);
		//$span = (microtime(true) - $start) * 1000;
		//$this->audit->log("assigned dependency \"$dependencyKey\" to cached object \"$objectCacheKey\" in $span msec");
	}

	function cache($key, $object)
	{
		$start = microtime(true);
		$serialized = serialize($object);
		$span = (microtime(true) - $start) * 1000;

		$this->redis->set($key, $serialized, 600);

		$length = strlen($serialized);
		$this->audit->log("serialized object \"$key\" to $length bytes in $span msec");
	}

	function invalidate($dependencyKey)
	{
		// grab all the dependent cache entries
		$dependents = $this->redis->smembers($dependencyKey);

		$this->audit->log("invalidating \"$dependencyKey\" dependents");

		$expiry = rand(0,5);

		foreach($dependents as $dependent)
		{
			//$cachedObjectKey = $this->redis->get($dependent);

			$this->audit->log("  evicting object cache \"$dependent\"");

			// staggered expiration
			//$this->redis->expire($dependent, $expiry);

			// or straight up delete
			$this->redis->del($dependent);
		}

		$this->redis->del($dependencyKey);
	}

	function getCachedOrEval($key, $method, $params, $forceInvalidation = false)
	{
		$cached = $this->redis->get($key);
		if($cached !== FALSE)
			return $cached;

		// uncached path
		$result = call_user_func_array($method, $params);

		$this->cache($key, $result);

		return $result;
	}

	function get($key)
	{
		$result = $this->redis->get($key);
		if($result !== FALSE)
		{
			//$this->audit->log("cache hit on: $key");
			$bytes = strlen($result);
			$start = microtime(true);
			$unserialized = unserialize($result);
			$span = (microtime(true) - $start) * 1000;
			$this->audit->log("unserialized object \"$key\" from $bytes bytes in $span msec");

			return $unserialized;
		}

		//$this->audit->log("cache miss on: $key");
		return FALSE;
	}
}