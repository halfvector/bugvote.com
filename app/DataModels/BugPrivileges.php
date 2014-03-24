<?php namespace Bugvote\DataModels;

class BugPrivileges
{
	protected $privileges;

	function __construct($ctx, $userId, $bugId)
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