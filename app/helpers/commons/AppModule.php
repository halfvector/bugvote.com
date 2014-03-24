<?php
namespace Bugvote\Commons;

// defunc?
class AppModule extends ModuleBase
{
	public function configurePageModel(IPageModel $model)
	{
		$perf = \PerformanceLog::start("RegularUserModule configuring page");

		$section = new MenuSection();
		$section->sectionName = "Back";
		$section->priority = 2;
		$section->sectionLinks = [
			'/apps' => ['url' => "/apps", 'active' => '', 'title' => 'Return Home', 'icon' => 'icon-home', 'notifications' => 3],
		];

		$model->addMenuSection($section);

		$section2 = new MenuSection();
		$section2->sectionName = "App Details";
		$section2->sectionLinks = [
			'/apps' => ['url' => "/apps", 'active' => '', 'title' => 'Apps', 'icon' => 'icon-map-marker', 'notifications' => 3],
		];

		$model->addMenuSection($section2);

		$perf->mark("Menu built");

		$perf->save();
	}
}
