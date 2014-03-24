<?php

namespace Bugvote\Controllers\Developer;

use Bugvote\Commons\IPageModel;
use Bugvote\Commons\IScaffoldDataModel;
use Bugvote\Commons\ISecondaryMenuProvider;
use Bugvote\Commons\PageModel;
use Bugvote\Services\Context;
use Bugvote\Models\Developer\App\AppHeader;
use Bugvote\Models\Developer\App\AppNews;
use Bugvote\Models\Developer\App\Roadmap\IndexDM;
use DAL;

interface IController
{
	function getUrl();
}

abstract class ControllerBase
{
	protected $url = "(undefined)";
	public function getUrl() { return $this->url; }

	abstract function getBaseUrl($context);
}

class ViewModel
{
	public $layout = false;
	public $template = false;
	public $primaryMenuItem = false;
	public $secondaryMenuItem = false;

	public function __construct($layout, $template)
	{
		$this->layout = $layout;
		$this->template = $template;
	}
}

class SmallAppHeader implements ISecondaryMenuProvider
{
	protected $appId = false;

	function getSecondaryMenu(Context $context)
	{
		//return [];

		return [
			'news' => [ 'url' => "/developer/app/$this->appId/news", 'active' => '', 'title' => 'News', 'icon' => 'icon-globe', 'notifications' => 0 ],
			'bugs' => [ 'url' => "/developer/app/$this->appId/bugs", 'active' => '', 'title' => 'Bugs', 'icon' => 'icon-comment', 'notifications' => 2 ],
			'roadmap' => [ 'url' => "/developer/app/$this->appId/roadmap", 'active' => '', 'title' => 'Roadmap', 'icon' => 'icon-comment', 'notifications' => 2 ],
		];
	}

	/**
	 * @param $appId
	 * @param \Bugvote\Commons\IPageModel $model
	 * @uses API,PageModel
	 */
	public function __construct($appId, IPageModel $model)
	{
		$context = $model->getContext();
		
		$this->appId = $appId;
		$this->details = $context->api->GetAppDetails($appId);
		$model->setSecondaryNav($this->getSecondaryMenu($context));
	}
}

# /roadmap/2
class RoadmapEntry
{
	function __construct($appId, $todoId, DAL $dal)
	{
		$this->details = $dal->selectMany('* from roadmap')->where(['todoId' => $todoId]);
	}
}

# DataModel with full CRUD support
# everything that's needed all in one class
class RoadmapDataModel implements IScaffoldDataModel
{
	function index(Context $context)
	{
		$todoId = $context->parameters->integers->roadmapId;
		$this->details = $context->dal->selectMany('* from roadmap')->where(['todoId' => $todoId]);
	}

	function show(Context $context)
	{
		$todoId = $context->parameters->integers->roadmapId;
		$this->details = $context->dal->selectMany('* from roadmap')->where(['todoId' => $todoId]);
		return $this;
	}

	function design(Context $context)
	{
		// TODO: Implement design() method.
	}

	function create(Context $context)
	{
		// TODO: Implement create() method.
	}

	function edit(Context $context)
	{
		// TODO: Implement edit() method.
	}

	function update(Context $context)
	{
		// TODO: Implement update() method.
	}

	function confirm(Context $context)
	{
		// TODO: Implement confirm() method.
	}

	function destroy(Context $context)
	{
		// TODO: Implement destroy() method.
	}
}

class AppRoadmap extends ControllerBase
{
	protected $baseTemplate = "Developer/App/Roadmap";
    protected $baseUrl = "/plan";

	function getBaseUrl($context)
	{
		$appId = $context->request->appId;
		return "/developer/app/$appId/roadmap";
	}

	function index(Context $context)
	{
		// prepare variables
		$todoId = $context->request->todoId;
		$appId = $context->dal->fetchSingleValue('select appId from roadmap where todoId = :todoId', ['todoId' => $todoId]);

		// build data models, passing the minimal amount of dependencies
		$main = new PageModel($context);
		$main->app = new SmallAppHeader($appId, $main);
		$main->roadmap = new RoadmapIndex($appId, $todoId, $context->dal);

		// configure view model
		$main->layout = "Developer/Roadmap/Layout";
		$main->template = "Developer/Roadmap/Index";
		$main->setButtons("/developer/app/$appId", 'roadmap');

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
		// prepare variables
		$todoId = $context->request->roadmapId;
		$appId = $context->dal->fetchSingleValue('select appId from roadmap where todoId = :todoId', ['todoId' => $todoId]);

		// build data models, passing the minimal amount of dependencies
		$main = new PageModel($context);
		$main->app = new SmallAppHeader($appId, $main);
		//$main->roadmap = new RoadmapEntry($appId, $todoId, $context->dal);
		$main->roadmap = (new RoadmapDataModel())->show($context);

		// configure view model
		$main->layout = "Developer/Roadmap/Layout";
		$main->template = "Developer/Roadmap/Show";
		$main->setButtons("/developer/app/$appId", 'roadmap');

		// render
		$context->render($main);
	}

	function edit(Context $context)
	{
		// prepare variables
		$todoId = $context->request->roadmapId;
		$appId = $context->dal->fetchSingleValue('select appId from roadmap where todoId = :todoId', ['todoId' => $todoId]);

		// build data models, passing the minimal amount of dependencies
		$main = new PageModel($context);
		$main->app = new SmallAppHeader($appId, $main);
		//$main->roadmap = new RoadmapEntry($appId, $todoId, $context->dal);
		$main->roadmap = (new RoadmapDataModel())->show($context);

		// configure view model
		$main->layout = "Developer/Roadmap/Layout";
		$main->template = "Developer/Roadmap/Edit";
		$main->setButtons("/developer/app/$appId", 'roadmap');

		// render
		$context->render($main);
	}
}