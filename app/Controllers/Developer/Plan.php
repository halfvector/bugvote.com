<?php

namespace Bugvote\Controllers\Developer;


use Bugvote\Commons\BaseController;
use Bugvote\Commons\PageModel;
use Bugvote\Commons\SessionDataHelper;
use Bugvote\Services\Context;
use Bugvote\Models\Developer\App\AppHeader;
use Bugvote\Models\Developer\App\AppNews;
use Bugvote\Models\Developer\Plan\PlanIndex;
use DAL;


class PlanDataModel
{
    protected $dal;
    protected $session;

    function __construct(DAL $dal, SessionDataHelper $session)
    {
        $this->dal = $dal;
        $this->session = $session;
    }

	function index($userId)
	{
        $this->description = $this->session->getOnce('description', '');
        $this->plans = $this->dal->selectMany('*, date_format(createdAt, "%Y-%m-%dT%TZ") as postedAtIso8601 from plan')->where(['userId' => $userId]);

        foreach($this->plans as $plan)
        {
            if($plan->tags != "")
                $plan->tags = explode(',', $plan->tags);
            else
                $plan->tags = [];

            $plan->descriptionTagged = preg_replace('/\*(.*?)\*/', '<a href="#" class="tag"><i class="icon-tag"></i>$1</a>', $plan->description);
        }

        return $this;
	}

	function show($todoId)
	{
		$this->details = $this->dal->selectMany('* from roadmap')->where(['todoId' => $todoId]);
		return $this;
	}

	function design()
	{
		// TODO: Implement design() method.
	}

	function create($userId, $description, $tags)
	{
        $tagList = implode(',', $tags);

        $this->dal->insertSingleObj('
			insert into plan
				set userId = :userId, description = :description, createdAt = UTC_TIMESTAMP(), tags = :tags
			', [':userId' => $userId, ':description' => $description, ':tags' => $tagList]
        );
	}

	function edit()
	{
		// TODO: Implement edit() method.
	}

	function update()
	{
		// TODO: Implement update() method.
	}

	function confirm()
	{
		// TODO: Implement confirm() method.
	}

	function destroy()
	{
		// TODO: Implement destroy() method.
	}
}

class Plan extends BaseController
{
	protected $baseTemplate = "Developer/Plan";
    protected $baseUrl = "/plan";

    protected $dataModel;

    function __construct($context)
    {
        $this->dataModel = new PlanDataModel($context->dal, $context->session);
        parent::__construct($context);
    }

	function getBaseUrl($context)
	{
		$id = $context->request->planId;
		return "$this->baseUrl/$id";
	}

    function getSecondaryMenu(Context $context)
    {
        return [
            //'news' => [ 'url' => "/developer/app/$this->appId/news", 'active' => '', 'title' => 'News', 'icon' => 'icon-globe', 'notifications' => 0 ],
        ];
    }

	function index()
	{
        $ctx = $this->context;

		// prepare variables
		$todoId = $this->context->request->todoId;
		$appId = $ctx->dal->fetchSingleValue('select appId from roadmap where todoId = :todoId', ['todoId' => $todoId]);

		// build data models, passing the minimal amount of dependencies
		$main = new PageModel($ctx);
        $main->plan = new PlanIndex($ctx->user->getUserId(), $ctx->dal, $ctx->session);

        $main->setSecondaryNav($this->getSecondaryMenu($ctx));

		// configure view model
		$main->layout = $this->baseTemplate . '/Layout';
		$main->template = $this->baseTemplate . '/Index';
		$main->setButtons("/plan", '');

		// render
		$ctx->render($main);
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

	function create()
	{
        $data = $this->getRequirements([
            'description' => ['type' => 'string', 'minLength' => 3],
            'time' => ['type' => 'time', 'optional' => true]
        ]);

        // sanity test: all * tags are closed
        if(substr_count($data->description, '*') % 2 != 0)
            $this->showError("Oops, there is an unclosed * tag in the description");

        preg_match_all('/\*(.*?)\*/', $data->description, $matches);

        $this->dataModel->create($this->context->session->getUserId(), $data->description, $matches[1]);
        $this->context->redirect($this->baseUrl);
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