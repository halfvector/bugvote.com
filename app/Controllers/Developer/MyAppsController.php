<?php

namespace Controllers\Developer;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\PageModel;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;
use Bugvote\Core\AssetManager;
use Models\Developer\App\AppHeader;

class MyAppsController extends BaseController
{
    protected $baseUrl = "/my";

    function getParentUrl() {
        return $this->baseUrl;
    }

    function getUrl() {
        return $this->baseUrl . '/' . $this->context->parameters->integers->myId;
    }

    function getSecondaryMenu($appId)
    {
        return [
            //'home' => [ 'url' => "$this->baseUrl/$appId", 'active' => '', 'title' => 'Home', 'icon' => 'icon-home', 'notifications' => 0 ],
            //'roadmap' => [ 'url' => "$this->baseUrl/$appId/roadmap", 'active' => '', 'title' => 'News', 'icon' => 'icon-bullhorn', 'notifications' => 2 ],
            //'newest' => [ 'url' => "$this->baseUrl/$appId/newest", 'active' => '', 'title' => 'Fresh', 'icon' => 'icon-lightbulb' ],
            'ideas' => [ 'url' => "$this->baseUrl/$appId/ideas", 'active' => '', 'title' => 'Idea Lab', 'icon' => 'icon-beaker' ],
            //'hottest' => [ 'url' => "$this->baseUrl/$appId/hottest", 'active' => '', 'title' => 'Rising', 'icon' => 'icon-cloud-upload' ],
            'workshop' => [ 'url' => "$this->baseUrl/$appId/workshop", 'active' => '', 'title' => 'Garage', 'icon' => 'icon-wrench' ],
            'features' => [ 'url' => "$this->baseUrl/$appId/features", 'active' => '', 'title' => 'Features', 'icon' => 'icon-star' ],
        ];

        /*
         return [
			'news' => [ 'url' => "/developer/app/$appId/news", 'active' => '', 'title' => 'News', 'icon' => 'icon-globe', 'notifications' => 0 ],
			'bugs' => [ 'url' => "/developer/app/$appId/bugs", 'active' => '', 'title' => 'Bugs', 'icon' => 'icon-comment', 'notifications' => 2 ],
			'roadmap' => [ 'url' => "/developer/app/$appId/roadmap", 'active' => '', 'title' => 'Roadmap', 'icon' => 'icon-comment', 'notifications' => 2 ],
		];
        */
    }

    function index(Context $context)
    {
        $ctx = $this->context;
        $userId = $ctx->user->getUserId();

        $main = new PageModel($ctx);
        $main->apps = $ctx->dal->selectMany('* from apps natural join appAdmins natural join users')->where(['userId' => $userId]);
        foreach($main->apps as $app) {
            $app->appThumbnail = AssetManager::GetWebPathForAsset($app->thumbnailMediumAssetId);
            $app->showUrl = "/my/$app->appId";
            $app->editUrl = "/my/$app->appId/edit";
        }

        $main->developers = $ctx->dal->selectMany('* from developers natural join developerUsers natural join users')->where(['userId' => $userId]);

        // config view
        $this->setView($main, 'Index', 'Site');
        $main->setButtons($this->getParentUrl(), "index");

        // render
        $ctx->render($main);
    }

    function design(Context $context)
    {
        $ctx = $this->context;
        $userId = $ctx->user->getUserId();

        $main = new PageModel($ctx);

        // config view
        $this->setView($main, 'Create', 'Site');
        $main->setButtons($this->getParentUrl(), "index");

        // render
        $ctx->render($main);
    }

    function edit(Context $context)
    {
        $ctx = $this->context;
        $appId = $ctx->parameters->integers->myId;

        // TODO: check permissions

        $vm = new PageModel($ctx);
        $vm->details = $ctx->dal->select1('* from apps')->where(['appId' => $appId]);
        $vm->details->appImg = AssetManager::GetWebPathForAsset($vm->details->thumbnailMediumAssetId);

        $this->render($vm, 'Edit');
    }

    function show(Context $ctx)
    {
        $appId = $ctx->parameters->integers->myId;

        $vm = new PageModel($ctx);
        $vm->setSecondaryNav($this->getSecondaryMenu($appId));
        $vm->app = $ctx->dal->select1('a.*, i.assetId, i.assetPath as thumbnailPath from apps a left join assets i on (thumbnailMediumAssetId = assetId)')->where(['appId' => $appId]);
        $vm->app->imgUrl = AssetManager::GetWebPathForAsset($vm->app->assetId);

        //$vm->app = $ctx->dal->select1('* from apps')->where(['appId' => $appId]);
        //$vm->app = new AppHeader($vm);
        //$vm->app = $ctx->dal->selectMany('* from apps )

        $this->render($vm, 'Show');
    }

    function create(Context $ctx)
    {
        $appName = $ctx->parameters->strings->title;

        $appId = $ctx->dal->insert('apps')->set(['appName' =>$appName]);
        $appAdminId = $ctx->dal->insert('appAdmins')->set(['appId' => $appId, 'userId' => $ctx->user->getUserId()]);

        \Log::Write("Created appId=$appId with appAdminId=$appAdminId");

        $ctx->redirect($this->getParentUrl() . '/' . $appId);
    }

    function update(Context $ctx)
    {
        $data = $this->getRequirements([
            'appId' => ['type' => 'int'],
            'assetId' => ['type' => 'int'],
            'title' => ['type' => 'string', 'minLength' => 3],
            'thumbnail' => ['type' => 'file', 'optional' => true]
        ]);

        $updates = ['appName' => $data->title];

        if($data->thumbnail != null)
        {
            $assetId = $data->assetId ? $ctx->api->UpdateAsset($data->assetId, $data->thumbnail) : $ctx->api->CreateAsset($data->thumbnail);
            $updates['thumbnailMediumAssetId'] = $assetId;
        }

        $ctx->dal->update('apps')->set($updates)->where(['appId' => $data->appId]);

        \Log::Write("Updated appId=$data->appId");

        $ctx->redirect($this->getParentUrl() . '/' . $data->appId);
    }
}
