<?php namespace Bugvote\Controllers\App;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\TimeHelper;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Commons\ViewModelCollection;
use Bugvote\Core\DAL;
use Bugvote\Services\Context;
use Bugvote\ViewModels\AppRootVM;
use Bugvote\ViewModels\Dashboard\DeveloperPostedReleaseNotes;
use Bugvote\ViewModels\Dashboard\UserPostedSuggestion;

class DashboardItemViewModel extends ViewModelBase
{
	public $creatorImg;
	public $createdAge;
	public $progressPercentage;
	public $suggestionUrl;
	public $isBugFix;

	function extend(Context $ctx, $row)
	{
		$this->creatorImg = $ctx->assetManager->GetWebPathForAsset($row->creatorImgId);
		$this->createdAge = TimeHelper::SmartShortAge($row->createdAgeSec);

		$this->progressPercentage = number_format(rand(0, 100), 0) . "%";

		$this->suggestionUrl = UrlHelper::createIdeaUrl($row->seoUrlId, $row->seoUrlTitle);
		$this->isBugFix = $row->suggestionTypeId == 1 ? true : false;
	}
}

class DashboardItemCollection extends ViewModelCollection
{
	function createVM(Context $ctx, $dataModel)
	{
		return new DashboardItemViewModel($ctx, $dataModel);
	}
}

class DashboardController extends BaseController
{
	/** @route /a/[:appUrl] */
	function news(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "home");

		$p = $ctx->perf->start("Dashboard DMs");

		// all new suggestions
		$new = $this->getNewestSuggestions($ctx, $vm->appId, $vm->userId, 1);
		$doing = $this->getNewestSuggestions($ctx, $vm->appId, $vm->userId, 4);
		$done = $this->getNewestSuggestions($ctx, $vm->appId, $vm->userId, 5);

		$p->next("Dashboard VMs");

		$vm->new = new DashboardItemCollection($ctx, $new);
		$vm->doing = new DashboardItemCollection($ctx, $doing);
		$vm->done = new DashboardItemCollection($ctx, $done);

		$p->next("Counts");

		$vm->numOfIdeasNew = $this->getSuggestionCount($ctx, $vm->appId, 1);
		$vm->numOfIdeasDoing = $this->getSuggestionCount($ctx, $vm->appId, 4);
		$vm->numOfIdeasDone = $this->getSuggestionCount($ctx, $vm->appId, 5);

		$vm->urlViewNewIdeas = $vm->urlViewApp . "/ideas";
		$vm->urlViewDoingIdeas = $vm->urlViewApp . "/garage";
		$vm->urlViewDoneIdeas = $vm->urlViewApp . "/features";

		$vm->urlNewIdea = $vm->urlViewApp . "/submit";

		$p->next("Activity Feed DMs");

		// activity feed
		$activity = $ctx->dal->fetchMultipleObjs("
			SELECT
				a.*, i.*,
				-- user data
				u.fullName, po.role AS projectRole,
				-- minimal data for release item
				r.version AS releaseVersion, r.body AS releaseText,
				-- minimal data for suggestion items
				s.title, s.numOfVotes, s.numOfComments, s.seoUrlTitle, s.seoUrlId,
				-- minimal data for comment item
				c.comment
			FROM
				activity a
				LEFT JOIN suggestions s ON (s.suggestionId = a.refId AND type BETWEEN 100 AND 200)
				LEFT JOIN suggestionComments c ON (c.commentId = a.refId AND type BETWEEN 200 AND 300)
				LEFT JOIN releases r ON (r.releaseId = a.refId AND type BETWEEN 400 AND 500)
				LEFT JOIN users u ON (a.userId = u.userId)
				LEFT JOIN assets i ON (u.profileMediumAssetId = i.assetId)
				LEFT JOIN projectOwners po ON (u.userId = po.userId AND po.projectId = a.appId)
			WHERE
				a.appId = :appId
			ORDER BY
				a.happenedAt DESC
			", ["appId" => $vm->appId], "activity feed"
		);

		$p->next("Activity Feed VMs");

		$vm->events = [];

		foreach ($activity as $item) {
			$eventVM = null;

			switch ((int)($item->type / 100)) {
				case 1:
					$eventVM = new UserPostedSuggestion($ctx, $item);
					break;

				case 4:
					$eventVM = new DeveloperPostedReleaseNotes($ctx, $item);
					break;
			}

			if ($eventVM != null) { // we matched a ViewModel to the event's DTO
				// append it to the list
				$vm->events [] = $eventVM;
			}
		}
		$p->next("Render");

		$this->renderTemplate($vm, 'Site', 'App/News');

		$p->stop();
	}

	/**
	 * gets latest suggestions for app $appId from the perspective of user $userId
	 * @param \Bugvote\Services\Context $ctx
	 * @param $appId
	 * @param $userId
	 * @param $suggestionStateId
	 * @internal param \Bugvote\Services\Context $qb
	 * @internal param \AppTogether\Core\DAL $dal
	 * @return mixed
	 */
	function getNewestSuggestions(Context $ctx, $appId, $userId, $suggestionStateId)
	{
		return $ctx->dal->fetchMultipleObjs("
            SELECT
            	s.suggestionId, s.title, s.suggestionTypeId,
            	s.seoUrlId, s.seoUrlTitle,
            	o.fullName AS creatorName, o.profileMediumAssetId AS creatorImgId,
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
            WHERE
            	appId = :appId AND s.suggestionStateId = :suggestionStateId
            GROUP BY
            	s.suggestionId
            ORDER BY
            	createdAgeSec ASC
            LIMIT 5
            ", [':appId' => $appId, ':userId' => $userId, ":suggestionStateId" => $suggestionStateId]
			, "newest ideas stateId=$suggestionStateId"
		);
	}

	function getSuggestionCount(Context $ctx, $appId, $suggestionStateId)
	{
		return $ctx->dal->fetchSingleValue("
            SELECT count(*)
            FROM suggestions
            WHERE appId = :app AND suggestionStateId = :state
            ", ["app" => $appId, "state" => $suggestionStateId], "count stateId=$suggestionStateId"
		);
	}
}
