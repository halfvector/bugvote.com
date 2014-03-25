<?php namespace Bugvote\Controllers\User;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PageViewModel;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\ViewModels\BasePageVM;
use Bugvote\ViewModels\ProfilePrimaryNavVM;

class ProfileViewModel extends PageViewModel
{
	public $dataModel;

	public function __construct(Context $ctx, $dataModel)
	{
		$this->dataModel = $dataModel;

		//$this->profileImg = $ctx->assetManager->GetWebPathForAsset($dataModel->assetPath);
		//$this->createdAge = TimeHelper::SmartShortAge($dataModel->createdAgeSec);
		$this->profileImg = new ImageUrlGenerator($ctx, new ImageAsset($dataModel));
	}
}

class UserBugReportVM extends ViewModelBase
{
	function extend(Context $ctx, $dm)
	{
		$this->url = $ctx->url->createIdeaUrl($dm->seoUrlId, $dm->seoUrlTitle);
	}
}

class ProfileController extends BaseController
{
	// takes a compressed id
	public static function createUserProfileUrl($userId)
	{
		return '/u/' . $userId;
	}

	public static function compressUserId($rawUserId) {
		return base_convert($rawUserId + 10000, 10, 36);
	}

	// returns a positive id, or 0 on error
	public static function decompressUserId($compressedUserId) {
		$id = base_convert($compressedUserId, 36, 10) - 10000;
		$id = max($id, 0);
		return $id;
	}

	function show(Context $ctx)
	{
		$userId = self::decompressUserId($ctx->parameters->userId);

		$user = $ctx->dal->fetchSingleObj("
			select *
			from users
			where userId = :userId
			", ["userId" => $userId], "user details"
		);

		// grab the first social-user account's profile image
		// TODO: allow user to choose which image to use as their primary profile pic
		$userProfileAsset = $ctx->dal->fetchSingleObj(
			"select assetId, originalFilename from socialAccounts u left join assets on (profilePicAssetId = assetId) where userId = :userId limit 1",
			['userId' => $userId],
			'social profile image'
		);

		$vm = new BasePageVM($ctx);
		$vm->primaryMenu = new SimplePrimaryMenuVM(
			[ new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true) ]
		);

		//$vm = new PageViewModel();
		$vm->urlViewProfile = UrlHelper::createUserUrl($user->userId);

		$vm->primaryMenu = new ProfilePrimaryNavVM($vm->urlViewProfile, "home");

		$vm->urlImageProfile = new ImageUrlGenerator($ctx, new ImageAsset($userProfileAsset));
		$vm->urlUpdateProfile = UrlHelper::createUserUrl($userId) . '/update';
		$vm->user = $user;
		$vm->user->compressedUserId = self::compressUserId($user->userId);

		$bugsDM = $ctx->dal->fetchMultipleObjs(
			"select suggestionId, seoUrlId, seoUrlTitle, title, left(suggestion, 140) as description
			from suggestions where userId = :userId",
			[":userId" => $userId]
		);

		$vm->bugs = UserBugReportVM::createCollection($ctx, $bugsDM);

		$this->renderTemplate($vm, 'Site', 'User/ViewProfile');
	}

	function update(Context $ctx)
	{
		// validate userId and permissions

		$form = $this->getRequirements([
			'userId' => ['type' => 'string'],
			'fullName' => ['type' => 'string', 'minLength' => 3],
			'profileImage' => ['type' => 'file', 'optional' => true]
		]);

		if(!$form)
		{	// grab userId from the url
			$userId = $ctx->parameters->strings->userId;
			return $ctx->redirect(self::createUserProfileUrl($userId));
		}

		// decompress userId
		$userId = self::decompressUserId($form->userId);

		if(!$userId)
		{	// sanity failure
			// TODO: user profile update error page
			$ctx->log->write("Error decompressing userId: $form->userId");
			$userId = $ctx->parameters->strings->userId;
			return $ctx->redirect(self::createUserProfileUrl($userId));
		}

		if(!$ctx->permissions->CanModifyUser($userId))
		{	// permission denied
			// TODO: user profile update permission denied page
		}

		// update user's name
		$ctx->log->write("updating user's name: $form->fullName");
		$ctx->dal->update("users")->set(["fullName" => $form->fullName])->where(["userId" => $userId]);

		// update profile image if a new one was provided
		if($form->profileImage)
		{
			$assetId = $ctx->dal->fetchSingleValue("select profileMediumAssetId from users where userId = :userId", ["userId" => $userId], "get assetId");

			if(!$assetId)
			{	// sanity failure, user doesn't seem to have a valid asset assigned.
				// must create a new asset
				return $ctx->redirect(self::createUserProfileUrl(self::compressUserId($userId)));
			}

			$assetFilePath = $this->ctx->assetManager->GetAbsoluteFilePathForAsset($assetId);
			$ctx->assetManager->TryUploadAsset($form->profileImage, $assetFilePath);
		}

		return $ctx->redirect(self::createUserProfileUrl(self::compressUserId($userId)));
	}
}
