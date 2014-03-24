<?php namespace Bugvote\Core\Cache;

use Bugvote\Core\AuditFacade;
use Bugvote\Core\DAL;

class QueryCacheServices
{
	/** @var SmartObjectCache */	public $cache;
	/** @var DAL */					public $dal;
	/** @var AuditFacade */			public $auditor;

	function __construct(IObjectCache $cache, DAL $dal, AuditFacade $auditor)
	{
		$this->cache = $cache;
		$this->dal = $dal;
		$this->auditor = $auditor;
	}
}