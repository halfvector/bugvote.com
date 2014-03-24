<?php

namespace Bugvote\Commons;

use Bugvote\Core\API;

class DeveloperModule extends ModuleBase
{
	protected $primaryMenu;

	public function configurePageModel(IPageModel $model)
	{
		$ctx = $model->getContext();
		$perf = $ctx->getNewPerfContext(__METHOD__);
		$api = $ctx->api;

		$section = new MenuSection();
		$section->priority = 4;
		$section->sectionName = "";

		$section->sectionLinks['/'] = [
			'url' => '/',
			'title' => 'News', 'icon' => 'icon-bullhorn',
			'notifications' => 0
		];

        $section->sectionLinks['/ideas'] = [
            'url' => '/ideas',
            'title' => 'My Apps', 'icon' => 'icon-beaker',
            'notifications' => 0
        ];

        $section->sectionLinks['/garage'] = [
            'url' => '/garage',
            'title' => 'My Apps', 'icon' => 'icon-wrench',
            'notifications' => 0
        ];

        $section->sectionLinks['/unlocked'] = [
            'url' => '/unlocked',
            'title' => 'My Apps', 'icon' => 'icon-heart',
            'notifications' => 0
        ];

        /*
        $apps = $api->GetAppsByUser($model->userId);

		// build links
		foreach ($apps as $app) {

			$notifications = (int) $api->GetNotificationCount($app->appId);

            //$buttonUrl = "Developer\\App\\{$app->appId}";

			$section->sectionLinks[$app->appUrl] = [
				'url' => $app->appUrl, 'active' => '',
				'title' => $app->appName, 'icon' => 'icon-heart',
				'notifications' => $notifications
			];
		}
        */

		$perf->save();

		$model->addMenuSection($section);
	}
}