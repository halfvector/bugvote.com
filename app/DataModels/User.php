<?php namespace Bugvote\DataModels;

use Bugvote\Commons\UserRoles;
use Bugvote\Services\Context;

class ServiceInjector
{
	protected $services = [];

	function AssertContains(Array $interfaces)
	{
		foreach($interfaces as $interface)
		{
			$found = false;

			foreach($this->services as $service)
			{
				if($service instanceof $interface)
				{
					$found = true;
					break;
				}
			}

			if(!$found)
			{   // couldn't find a wanted interface in the service provider
				return false;
			}
		}

		return true;
	}
}

class User
{
	protected $userId;
	protected $ctx;

	function __construct(Context $ctx, $userId)
	{
		$this->ctx = $ctx;
		$this->userId = $userId;
	}

	function getUserId()
	{
		return $this->userId;
	}

	function getRole()
	{
		if($this->userId)
		{
			return UserRoles::Regular;
		}

		return UserRoles::Anonymous;
	}
}
