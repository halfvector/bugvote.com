<?php namespace Bugvote\Commons;

use Bugvote\Core\DAL;

class UserSession implements IUserSessionProvider
{
	protected $db, $cookies;

	function __construct(DAL $db, SessionDataHelper $cookies)
	{
		$this->db = $db;
		$this->cookies = $cookies;
	}

	public $userId = 0;
	public $role = UserRoles::Anonymous;

	/**
	 * Test if user is logged in, get userId, role, etc
	 * if this step fails, invalidate session and log user out
	 */
	function process()
	{
		$this->userId = $this->cookies->getInteger("userId");
		if($this->userId)
		{
			$this->role = UserRoles::Regular;
		}
	}

	function getUserId()
	{
		return $this->userId;
	}

	function getRole()
	{
		return $this->role;
	}
}