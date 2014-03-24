<?php namespace Bugvote\Core\Cache;

use Bugvote\Core\AuditFacade;
use Bugvote\Core\DAL;
use Bugvote\Core\Reflector;

class QueryBuilder
{
	/** @var QueryCacheServices */
	protected $services;

	function __construct(IObjectCache $cache, DAL $dal, AuditFacade $auditor)
	{
		$this->services = new QueryCacheServices($cache, $dal, $auditor);
	}

	/**
	 * @param $query
	 * @param $parameters
	 * @param $label
	 * @return Query
	 */
	function create($query, $parameters, $label)
	{
		$caller = Reflector::GetSimpleCallerMetadata(2);
		$stripIdx = strrpos($caller, "\\");
		if($stripIdx)
			$caller = substr($caller, $stripIdx+1);
		$label = "$caller $label";

		return new Query($this->services, $query, $parameters, $label);
	}
}