<?php namespace Bugvote\Controllers\App;

use Bugvote\Commons\UserRoles;
use Bugvote\Services\Context;
use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Commons\ViewModelBase;
use Bugvote\ViewModels\AppRootVM;
use Bugvote\ViewModels\BasePageVM;
use Bugvote\ViewModels\SuggestionItemViewModel;

// this needs a builder that can replace [:appUrl] with $this->appUrl, etc
class RoadmapRouter
{
	public $appUrlTitle;
	public $appId;

	function __construct($ctx)
	{
		$this->appUrlTitle = $ctx->parameters->strings->appUrl;
		$this->appId = $ctx->resolver->getAppIdFromAppUrlTitle($ctx, $this->appUrlTitle);
	}

	function urlViewRoadmap() {
		return "/a/$this->appUrlTitle/roadmap";
	}

	function urlCreateNewSection() {
		return "/a/$this->appUrlTitle/roadmap/create/section";
	}

	const ViewRoadmap = "/a/[:appUrl]/roadmap";
	const CreateNewSection = "/a/[:appUrl]/roadmap/create/section";
}

class RoadmapControllerVM extends AppRootVM
{
	function __construct(Context $ctx, $menuItem = false)
	{
		parent::__construct($ctx);
		$this->setPrimaryMenuItem("roadmap");
		$this->urlViewRoadmap = $this->urlViewApp . "/roadmap";
		$this->urlCreateNewSection = $this->urlViewRoadmap . "/create/section";

		$categories = $ctx->dal->fetchMultipleObjs("
		    select * from roadmapCategories
		"
		);

		$items = $ctx->dal->fetchMultipleObjs('
            SELECT
            	s.suggestionId, s.title, s.suggestionTypeId, s.seoUrlId, s.seoUrlTitle,
            	o.fullName AS creatorName, oa.assetId AS posterImgId, oa.originalFilename as posterImgFilename,
            	coalesce(v.votes,0) AS votes,
            	coalesce(mv.myvote,0) AS myvote,
            	coalesce(c.comments,0) AS comments,
            	coalesce(mc.mycomments,0) AS mycomments,
            	unix_timestamp(s.postedAt) AS postedAt,
            	(unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt)) AS createdAgeSec
            FROM
            	roadmaps r
            		left join suggestions s using (suggestionId)
            		LEFT JOIN (SELECT sum(vote) AS votes, suggestionId FROM suggestionVotes GROUP BY suggestionId) AS v ON (v.suggestionId = s.suggestionId)
            		LEFT JOIN (SELECT vote AS myvote, userId, suggestionId FROM suggestionVotes where userId = :userId) AS mv ON (mv.suggestionId = s.suggestionId)

            		LEFT JOIN (SELECT count(commentId) AS comments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS c ON (c.suggestionId = s.suggestionId)
            		LEFT JOIN (SELECT count(commentId) AS mycomments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS mc ON (c.suggestionId = s.suggestionId AND mv.userId = :userId)

            		LEFT JOIN users o ON (o.userId = s.userId)
            		left join assets oa on (o.profileMediumAssetId = oa.assetId)
            WHERE
            	appId = :appId
            GROUP BY
            	s.suggestionId
            ORDER BY
            	votes DESC,
            	createdAgeSec ASC
            ',
			[':appId' => $this->appId, ':userId' => $this->userId],
			"roadmap items"
		);

		$this->items = SuggestionItemViewModel::createCollection($ctx, $items);
		//$this->items = RoadmapEntryVM::createCollection($ctx, $items);
		$this->categories = RoadmapCategoryItemVM::createCollection($ctx, $categories);
	}
}

class RoadmapEntryVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
	}
}

class RoadmapCategoryItemVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
	}
}

class RoadmapController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new RoadmapControllerVM($ctx);

		$this->renderTemplate($vm, 'Site', "App/Roadmap");
	}

	function createNewSection(Context $ctx)
	{
		$router = new RoadmapRouter($ctx);

		$data = $this->getRequirements([
			'appId' => ['type' => 'int'],
			'category' => ['type' => 'string', 'minLength' => 3],
		]);

		if(!$data)
			$ctx->redirect($router->urlViewRoadmap());

		$ctx->dal->insert('roadmapCategories')->set([
			'projectId' => $data->appId,
			'category' => $data->category
		]);

		$ctx->log->write("created roadmap category $data->category for projectId=$data->appId");

		$ctx->redirect($router->urlViewRoadmap());
	}
}