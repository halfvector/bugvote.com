<?php namespace Bugvote\DataModels;

use Bugvote\Commons\UserRoles;

class Privileges
{
	protected $privileges;

	function __construct($ctx, $userId, $projectId)
	{
		$roles = $ctx->dal->fetchMultipleObjs(
			'select privilege from privileges where projectId = :projectId and userId = :userId',
			['projectId' => $projectId, 'userId' => $userId],
			"fetch user-project permissions"
		);

		$this->privileges = array_map(function($x) { return $x->privilege; }, $roles);
	}

	function isAdmin() {
		return in_array(UserRoles::Admin, $this->privileges);
	}

	function isDeveloper() {
		return in_array(UserRoles::Developer, $this->privileges);
	}

	function isDeveloperOrHigher() {
		return $this->isDeveloper() || $this->isAdmin();
	}
}