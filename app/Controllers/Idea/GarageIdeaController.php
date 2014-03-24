<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\UrlHelper;
use Bugvote\Services\Context;
use Michelf\MarkdownExtra;
use Bugvote\ViewModels\IdeaRootVM;

class GarageIdeaController extends BaseController
{
	function getIdeaDetails(Context $ctx, $ideaId, $userId)
	{
		// all of this prep is pretty much nothing compared to actual sql-queries and even cache-hits on local APC/Redis instances
		// 0.07ms prep, 0.30ms cache hit, 1.4 ms db query, 2.3 ms full cache miss

		$idea = $ctx->dal->fetchSingleObj(
			"select
				s.appId, s.seoUrlId, s.suggestionId, u.*, s.seoUrlTitle, a.originalFilename,
            	unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt) as originalAgeSec, s.suggestionTypeId, s.suggestionStateId,
            	r.description, r.title, r.revisionReason, r.revisionDate, unix_timestamp(utc_timestamp()) - unix_timestamp(r.revisionDate) as revisionAgeSec
            from suggestions s
            	join users u using (userId)
            	left join assets a on (assetId = profileMediumAssetId)
            	left join suggestionRevisions r using (suggestionId)
            where
            	suggestionId = :suggestionId
            group by
            	suggestionId, revisionId
            order by
            	revisionId desc",
			[":suggestionId" => $ideaId],
			"suggestion details"
		);

		$idea->authorImg = $ctx->assetManager->GetWebPathForAsset($idea->profileMediumAssetId) . $idea->originalFilename;
		//$idea->revisionAge = $ctx->api->SmartLongAge($idea->revisionAgeSec);
		//$idea->originalAge = $ctx->api->SmartShortAge($idea->originalAgeSec);
		$idea->isRevised = $idea->revisionReason ? 1 : 0;

		//$markdownParser = new MarkdownExtraParser();
		//$idea->description = $markdownParser->transformMarkdown($idea->description);

		$idea->description = MarkdownExtra::defaultTransform($idea->description);

		//$ctx->perf->mark("Markdown transform");

		$getNumOfVotes = $ctx->dal->fetchSingleValue(
			"select coalesce(sum(vote),0) as votes from suggestionVotes where suggestionId = :suggestionId",
			['suggestionId' => $ideaId],
			"suggestion vote count"
		);

		$idea->numOfVotes = $getNumOfVotes;

		$idea->numOfRevisions = rand(0,10);

		$userVote = $ctx->dal->fetchSingleValue(
			"select vote from suggestionVotes where suggestionId = :suggestionId and userId = :userId",
			['suggestionId' => $ideaId, "userId" => $userId],
			"user's vote (up or down)"
		);

		$idea->isUpvoted = $userVote > 0;
		$idea->isDownvoted = $userVote < 0;

		$idea->seoUrl = UrlHelper::createIdeaUrl($idea->seoUrlId, $idea->seoUrlTitle);

		$idea->editUrl = $idea->seoUrl . "/edit"; // POST: /i/7qn/edit
		$idea->voteUrl = $idea->seoUrl . "/vote"; // POST: /i/7qn/vote
		$idea->revisionHistoryUrl = $idea->seoUrl . "/history"; // POST: /i/7qn/history

		$idea->isBugFix = $idea->suggestionTypeId == 1 ? true : false;

		return $idea;
	}

	function show(Context $ctx)
	{
		$vm = new IdeaRootVM($ctx);
		$vm->message = "Sorry, Idea Garage is still under construction";
		$this->renderTemplate($vm, 'Site', 'UnderConstruction');
		return;

		$vm = new IdeaRootVM($ctx);

		$vm->idea = $this->getIdeaDetails($ctx, $vm->ideaId, $vm->userId);
		$vm->setPrimaryMenuItem("garage");

		// grab basic app details for the layout (title, etc)
		$vm->app = $ctx->dal->fetchMultipleObjs(
			"select *
			from projects
			left join assets on (assetId = thumbnailAssetId)
			where projectId = :id",
			[':id' => $vm->idea->appId],
			"project from projectId"
		);

		if($vm->app->thumbnailAssetId)
			$vm->app->imgUrl = $ctx->assetManager->getWebFullPath($vm->app->assetId, $vm->app->originalFilename);
		else
			$vm->app->imgUrl = '/img/placeholders/200x150.gif';

		$vm->app->urlViewApp = UrlHelper::createAppUrl($vm->app->seoUrlTitle);


		$vm->suggestionType = SuggestionData::$suggestionTypes[$vm->idea->suggestionTypeId];
		$vm->suggestionState = SuggestionData::$suggestionStates[$vm->idea->suggestionStateId];
		$vm->suggestionProgress = $vm->idea->suggestionStateId * 20;

		$this->renderTemplate($vm, 'Site', 'Idea/Garage');
	}
}