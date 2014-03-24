<?php namespace Bugvote\ViewModels;

use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\Commons\UserRoles;

class UserStatusVM
{
	public $userIsAdmin = false;
	public $userIsAnon = false;
	public $userIsRegular = false;
	public $userIsDeveloper = false;

	public $userRole = UserRoles::Anonymous;

	public $userId = 0;

	public $urlUserProfile;
	public $urlUserProfileImage;

	function __construct(Context $ctx)
	{
		$user = $ctx->user->getUser();

		$this->userId = $user->getUserId();
		$this->userRole = $user->getRole();

		$this->userIsAnon = $this->userRole == UserRoles::Anonymous;
		$this->userIsRegular = $this->userRole == UserRoles::Regular;
		$this->userIsDeveloper = $this->userRole == UserRoles::Developer;
		$this->userIsAdmin = $this->userRole == UserRoles::Admin;
		$this->userIsModerator = $this->userRole == UserRoles::Moderator;

		// default avatar
		$this->urlUserProfileImage = "/img/placeholders/default-avatar-1.png";

		if($this->userIsAnon)
		{   // we have an anonymous user, don't waste any effort here
			$this->urlUserProfile = "?REGISTRATION_PAGE";
			$this->userName = "Anonymous";

		} else
		{
			$this->urlUserProfile = $ctx->url->createUserUrl($this->userId);

			$this->userName = $ctx->dal->fetchSingleValue("select fullName from users where userId = :userId", ["userId" => $this->userId]);

			if($this->userId)
			{
				$userProfileAsset = $ctx->dal->fetchSingleObj(
					"select assetId, originalFilename from users u left join assets on (profileMediumAssetId = assetId) where userId = :userId",
					['userId' => $this->userId]
				);

				if($userProfileAsset)
					$this->urlUserProfileImage = new ImageUrlGenerator($ctx, new ImageAsset($userProfileAsset));
			}
		}
	}
}