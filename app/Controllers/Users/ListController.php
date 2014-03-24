<?php namespace Controllers\Users;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PageViewModel;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Services\Context;
use Bugvote\Core\AssetManager;
use ViewModels\ProfilePrimaryNavVM;

class UserVM extends ViewModelBase
{
	public $urlImageProfile;
	public $urlViewProfile;

	function extend(Context $ctx, $row)
	{
		//$this->urlImageProfile = $ctx->paths->AbsoluteWebCacheRoot . '/40x40-2/' . $dataModel->assetPath;
		$this->urlImageProfile = $ctx->images->getResizeCacheUrl($row->assetPath, 50, 50);
		$this->urlViewProfile = UrlHelper::createUserUrl($row->userId);
	}
}

class ListController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new PageViewModel($ctx);
		$vm->primaryMenu = new ProfilePrimaryNavVM("/u/", "home");
		$vm->title = "BugVote Users";
		$vm->description = "List of users who vote on bugvote.com";

		$usersDM = $ctx->queryBuilder->create("
			select
				*
			from
				users
			left join
				assets on (assetId = profileMediumAssetId)
			", [], "users list"
		)
			->bypassCache()->getMultipleObjects();

		$vm->users = UserVM::createCollection($ctx, $usersDM);

		$this->renderTemplate($vm, 'User', 'Users/List');
	}
}
