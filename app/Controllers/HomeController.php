<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\UrlHelper;
use Bugvote\Services\Context;
use Bugvote\ViewModels\BaseNavigationVM;
use Bugvote\ViewModels\BasePageVM;

class HomePrimaryMenuVM extends BaseNavigationVM
{
	public $menuItems = [];

	function __construct()
	{
		$this->menuItems []= new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true);

		parent::__construct("home");
	}
}

class HomeVM extends BasePageVM
{
	public $userId;

	function __construct(Context $ctx)
	{
		$this->userId = $ctx->user->getUserId();
		$this->primaryMenu = new HomePrimaryMenuVM();

		parent::__construct($ctx);
	}

	function setPrimaryMenuItem($name)
	{
		$this->primaryMenu->setActiveItem($name);
	}
}

class HomeController extends BaseController
{
	/** @route GET / */
    public function home(Context $ctx)
    {
        $searchQuery = $ctx->parameters->q;

		$vm = new HomeVM($ctx);

	    //var_dump($vm);

        if($searchQuery)
        {
            $vm->apps = $ctx->dal->fetchMultipleObjs(
               "select * from apps
               where appName like :appName", [':appName' => $searchQuery]);
        }

		$vm->projects = $ctx->dal->fetchMultipleObjs('
			select *
			from projects
			-- left join projectOwners using (projectId)
			-- left join users using (userId)
			-- where role = 1001
		');

		foreach($vm->projects as $app)
		{
			// $ctx<AssetManager>::getWebPath($assetId);
			// $ctx->assetManager->getWebPath($assetId);
			// AssetManager::getWebPath($assetId);


			$app->imgUrl = $ctx->assetManager->GetWebPathForAsset($app->thumbnailAssetId);
			$app->appUrl = UrlHelper::createAppUrl($app->seoUrlTitle);
		}

        $this->renderTemplate($vm, 'Site', 'Home/Home');
    }
}