<?php namespace Bugvote\Controllers\App;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Services\Context;
use Bugvote\ViewModels\AppRootVM;
use Bugvote\ViewModels\BasePageVM;
use Bugvote\ViewModels\SuggestionItemViewModel;

class SearchVM extends BasePageVM
{
	function __construct(Context $ctx, $menuItem = false)
	{
		parent::__construct($ctx);
	}
}

class SearchItemVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
	}
}

class SearchController extends BaseController
{
	/** @route /a/[:appUrl]/search */
	function show(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "search");
		$vm->primaryMenu->menuItems [] = new PrimaryMenuItem("search", "/search", "Search", "fa fa-search", 0, true);

		$vm->urlCreateSuggestion = $vm->urlViewApp . "/submit";

		$query = $ctx->parameters->q;

		if ($query != "") {
			$matchingBugs = $ctx->dal->fetchMultipleObjs('
				SELECT
	                s.suggestionId, s.title, s.suggestionTypeId, s.seoUrlId, s.seoUrlTitle,
	                o.fullName AS creatorName, oa.assetId AS posterImgId, oa.originalFilename AS posterImgFilename,
	                coalesce(v.votes,0) AS votes,
	                coalesce(mv.myvote,0) AS myvote,
	                coalesce(c.comments,0) AS comments,
	                coalesce(mc.mycomments,0) AS mycomments,
	                unix_timestamp(s.postedAt) AS postedAt,
	                (unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt)) AS createdAgeSec
	            FROM
	                suggestions s
	                    LEFT JOIN (SELECT sum(vote) AS votes, suggestionId FROM suggestionVotes GROUP BY suggestionId) AS v ON (v.suggestionId = s.suggestionId)
	                    LEFT JOIN (SELECT vote AS myvote, userId, suggestionId FROM suggestionVotes GROUP BY suggestionId) AS mv ON (mv.suggestionId = s.suggestionId AND mv.userId = :userId)

	                    LEFT JOIN (SELECT count(commentId) AS comments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS c ON (c.suggestionId = s.suggestionId)
	                    LEFT JOIN (SELECT count(commentId) AS mycomments, suggestionId FROM suggestionComments GROUP BY suggestionId) AS mc ON (c.suggestionId = s.suggestionId AND mv.userId = :userId)

	                    LEFT JOIN users o ON (o.userId = s.userId)
	                    LEFT JOIN assets oa ON (o.profileMediumAssetId = oa.assetId)
	            WHERE
	                appId = :appId AND match(s.title, s.suggestion) against (:query IN BOOLEAN MODE)
	            GROUP BY
	                s.suggestionId
	            ORDER BY
	                votes DESC,
	                createdAgeSec ASC
	            ',
				[':appId' => $vm->appId, ':userId' => $vm->userId, ':query' => $query],
				"hottest bugs"
			);

			$vm->suggestions = SuggestionItemViewModel::createCollection($ctx, $matchingBugs);
		} else {
			$vm->suggestions = null;
		}

		$vm->query = $query;
		$vm->hasSearch = $vm->suggestions != null;
		$vm->hasResults = count($vm->suggestions) > 0;

		$vm->hottestClass = "active";
		$vm->hottestIdeas = $vm->urlViewApp . "/ideas/hottest";
		$vm->newestIdeas = $vm->urlViewApp . "/ideas/newest";

		$this->renderTemplate($vm, 'Site', 'App/Search');
	}
}