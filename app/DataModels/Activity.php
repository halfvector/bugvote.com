<?php namespace Bugvote\DataModels;

use Bugvote\Services\Context;

class Activity
{
	protected $ctx;
	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}

	function recordNewBug($appId, $userId, $bugId, $date)
	{
		$this->ctx->dal->insert('activity')->set([
			'appId' => $appId, 'userId' => $userId, 'refId' => $bugId, 'type' => 101, 'happenedAt' => $date
		]);
	}
} 