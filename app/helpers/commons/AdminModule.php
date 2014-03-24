<?php

namespace Bugvote\Commons;

use Bugvote\Core\API;

class AdminModule extends ModuleBase
{
	protected $primaryMenu;

	public function configurePageModel(IPageModel $model)
	{
		$ctx = $model->getContext();
		$perf = $ctx->getNewPerfContext(__METHOD__);
		$api = $ctx->api;

		$section = new MenuSection();
		$section->priority = 4;
		$section->sectionName = "Admin";

		$section->sectionLinks = [
			'/admin/apps' => ['url' => "/admin/apps", 'active' => '', 'title' => 'Apps', 'longTitle' => 'Manage apps', 'icon' => 'icon-laptop', 'notifications' => 0],
			'/admin/users' => ['url' => "/admin/users", 'active' => '', 'title' => 'Users', 'longTitle' => 'Manage users', 'icon' => 'icon-group', 'notifications' => 0],
			'/admin/bugs' => ['url' => "/admin/bugs", 'active' => '', 'title' => 'Bugs', 'longTitle' => 'Manage suggestions', 'icon' => 'icon-food', 'notifications' => 0],
			'/admin/roadmap' => ['url' => "/admin/roadmap", 'active' => '', 'title' => 'Roadmap', 'longTitle' => 'Manage roadmap', 'icon' => 'icon-tasks', 'notifications' => 0],
			'/admin/ownership' => ['url' => "/admin/ownership", 'active' => '', 'title' => 'Ownership', 'longTitle' => 'Manage app-ownership', 'icon' => 'icon-group', 'notifications' => 0],
		];

		$perf->save();

		$model->addMenuSection($section);
	}
}