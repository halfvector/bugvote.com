<?php namespace Bugvote\Core;

use Bugvote\Commons\IUserSessionProvider;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Services\UserSession;

// TODO: finish permissions manager
class Permissions
{
	/** @var DAL */
	protected $dal;
	/** @var ILogger */
	protected $logger;
	/** @var IUserSessionProvider */
	protected $user;

	function __construct(DAL $dal, ILogger $logger, UserSession $user)
	{
		$this->dal = $dal;
		$this->logger = $logger;
		$this->user = $user;
	}

	// current user oriented check
	function CanModifySuggestion($suggestionId)
	{
		$userId = $this->user->getUserId();
		$suggestionAuthorId = $this->dal->fetchSingleValue("select userId from suggestions where suggestionId = :id", [":id" => $suggestionId], "suggestion owner");

		$allowed = $suggestionAuthorId == $userId;

		$this->logger->write("userId=$userId; suggestionId=$suggestionId; permission=" . ($allowed ? "granted" : "denied"));

		return $allowed;
	}

	function IsAppDeveloper($userId, $appId)
	{
		return false;
	}

	// arbitrary user check
	function CanUserModifySuggestion($userId, $suggestionId)
	{


		return true;
	}

	function CanUserViewProject($userId, $projectId)
	{
		return true;
	}

	function CanUserViewSuggestion($userId, $suggestionId)
	{
		return true;
	}

	function CanModifyUser($userId)
	{
		return true;
	}

	function CanUserCommentOnIdea($userId, $ideaId)
	{
		return true;
	}

	// returns list of permission values
	function GetUserProjectPermissions($userId, $projectId)
	{
		// check app ownership
		$roles = $this->dal->fetchMultipleObjs(
			'select privilege from privileges where projectId = :projectId and userId = :userId',
			['projectId' => $projectId, 'userId' => $userId],
			"fetch user-project permissions"
		);

		$privileges = array_map(function($x) { return $x->privilege; }, $roles);
		return $privileges;
	}
}
