<?php namespace Bugvote\Commons;
use Bugvote\Services\Context;

interface IUrlResolverCache
{
	function set($key, $value, $timeout = 360);
}

class ResolutionCache implements IUrlResolverCache
{
	function ResolutionCache()
	{

	}

	function set($key, $value, $timeout = 60)
	{
		apc_store($key, $value, $timeout);
	}

	function get($key, &$success = null)
	{
		return apc_fetch($key, $success);
	}
}

class NullResolutionCache implements IUrlResolverCache
{

	function set($key, $value, $timeout = 60)
	{
	}

	function get($key, &$success = null)
	{
		return false;
	}
}

/**
 * Class UrlResolver
 * @package Controllers\App
 * resolves IDs from url-names (/a/appTitle, /i/ideaTitle, /u/userName, etc)
 */
class UrlResolver
{
	protected $cache;

	function __construct()
	{
		$this->cache = new NullResolutionCache();
	}

	function getAppIdFromAppUrlTitle(Context $ctx, $appUrlTitle)
	{
		$key = "UrlResolver:/seoUrlTitle=$appUrlTitle/projectId";
		$cachedId = $this->cache->get($key, $success);
		if($success)
		{
			$ctx->perf->mark("cache hit: $key");
			return $cachedId;
		}

		$appId = $ctx->dal->fetchSingleValue(
			'select projectId from projects where seoUrlTitle = :title',
			[':title' => $appUrlTitle],
			"projectId from project title"
		);

		$this->cache->set($key, $appId);

		return $appId;
	}
}