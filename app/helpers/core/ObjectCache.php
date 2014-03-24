<?php namespace Bugvote\Core;

use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\UserSession;

class ObjectCache
{
	/** @var ILogger */
	protected $logger;

	public function __construct($logger)
	{
		$this->logger = $logger;
	}

	public function Save($uuid, $object, $ttl = 3600)
	{
		apc_store($uuid, $object, $ttl);
	}

	public function invalidate($uuid)
	{
		apc_delete($uuid);
	}

	public function Load($uuid)
	{
		return apc_fetch($uuid);
	}

	// returns a cached version of the data, or calls the data-creator and saves the results.
	public function Cache($uuid, $method, $params, $forceRebuild = true)
	{
		// cached path
		$cached = apc_fetch($uuid);
		if( $cached !== false && !$forceRebuild )
		{
			$this->logger->write("Cache hit: $uuid");
			return $cached;
		}

		// uncached path
		$result = call_user_func_array($method, $params);

		self::Save($uuid, $result);

		return $result;
	}

	/*
	public function InvalidateUserCache($match)
	{
		$userPart = "/user:" . UserSession::GetUserId() . "/";
		$userCache = apc_fetch($userPart) ?: [];
		foreach($userCache as $key => $value)
		{
			if( strstr($key, $match) !== false )
			{
				$logger = ErrorManager::getLogger();
				if($logger) $logger->write("Deleting from APC: [$key]");
				apc_delete($key);
			}
		}
	}
	*/
}
