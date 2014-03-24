<?php

namespace Bugvote\Controllers\Developer;

use Bugvote\Commons\PageModel;
use Bugvote\Services\Context;
use Bugvote\Models\Developer\App\AppHeader;
use Bugvote\Models\Developer\App\AppNews;
use Bugvote\Models\Developer\App\Roadmap\IndexDM;

class BugsController
{
	protected $templateFolder = "Developer/App/Bugs/";
	protected $parentPath = "/developer/app/";

	function index(Context $context)
	{
		$appId = $context->request->appId;

		// setup data models
		$main = new PageModel($context);
		$main->app = new AppHeader($main);
		$main->roadmap = new IndexDM($main);

		// configure view
		$main->layout = "App";
		$main->template = $this->templateFolder . 'Index';
		$main->setButtons($this->parentPath . $appId, 'bugs');

		// render
		$context->render($main);
	}

	function design(Context $context)
	{
		$appId = $context->request->appId;

		// setup data models
		$main = new PageModel($context);
		$main->app = new AppHeader($main);
		$main->features = new AppNews($main);

		// configure view
		$main->layout = "App";
		$main->template = "Developer/App/Roadmap/Create";
		$main->setButtons("/developer/app/$appId", 'roadmap');

		// render
		$context->render($main);
	}

	function create(Context $context)
	{
		$appId = $context->request->appId;
		$title = $context->request->title;
		$tags = $context->request->tags;

		$tagList = implode(',', $tags);

		$url = $this->getBaseUrl($context);

		// TODO: check if appId is valid
		// TODO: check if user is allowed to post a feature for appId
		// TODO: check if title is a duplicate

		if($appId == "")
		{	// return an error
			$context->flash("Oops, can't create a feature with no title", 'error');
			$context->redirect($url . '/new');
		}

		if($title == "")
		{	// return an error
			$context->flash("Oops, can't create a feature with no title", 'error');
			$context->redirect($url . '/new');
		}

		$context->dal->insertSingleObj('
			insert into roadmap
				set appId = :appId, title = :title, createdAt = UTC_TIMESTAMP(), tags = :tags
			', ['appId' => $appId, 'title' => $title, 'tags' => $tagList]
		);

		$context->redirect($url);
	}

	function show(Context $context)
	{
		$appId = $context->request->appId;

		// setup data models
		$main = new PageModel($context);
		$main->app = new AppHeader($main);
		$main->features = new AppNews($main);

		// configure view
		$main->layout = "App";
		$main->template = "Developer/App/Roadmap/Create";
		$main->setButtons("/developer/app/$appId", 'roadmap');

		// render
		$context->render($main);
	}
}