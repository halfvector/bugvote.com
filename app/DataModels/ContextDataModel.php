<?php namespace Bugvote\DataModels;

use Bugvote\Services\Context;

class ContextDataModel
{
	protected $ctx;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}
}