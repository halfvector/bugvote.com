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

		$this->progressPercentage = number_format(rand(0,100),0) . "%";

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
            select
            	s.suggestionId, s.title, s.suggestionTypeId,
            	s.seoUrlId, s.seoUrlTitle,
            	o.fullName as creatorName, o.profileMediumAssetId as creatorImgId,
            	coalesce(v.votes,0) as votes,
            	coalesce(mv.myvote,0) as myvote,
            	coalesce(c.comments,0) as comments,
            	coalesce(mc.mycomments,0) as mycomments,
            	unix_timestamp(s.postedAt) as postedAt,
            	(unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt)) as createdAgeSec
            from
            	suggestions s
            		left join (select sum(vote) as votes, suggestionId from suggestionVotes group by suggestionId) as v on (v.suggestionId = s.suggestionId)
            		left join (select vote as myvote, userId, suggestionId from suggestionVotes group by suggestionId) as mv on (mv.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join (select count(commentId) as comments, suggestionId from suggestionComments group by suggestionId) as c on (c.suggestionId = s.suggestionId)
            		left join (select count(commentId) as mycomments, suggestionId from suggestionComments group by suggestionId) as mc on (c.suggestionId = s.suggestionId and mv.userId = :userId)

            		left join users o on (o.userId = s.userId)
            where
            	appId = :appId and s.suggestionStateId = :suggestionStateId
            group by
            	s.suggestionId
            order by
            	createdAgeSec asc
            limit 5
            ", [':appId' => $appId, ':userId' => $userId, ":suggestionStateId" => $suggestionStateId]
			, "newest ideas stateId=$suggestionStateId"
		);
	}

	function getSuggestionCount(Context $ctx, $appId, $suggestionStateId)
	{
		return $ctx->dal->fetchSingleValue("
            select count(*)
            from suggestions
            where appId = :app and suggestionStateId = :state
            ", ["app" => $appId, "state" => $suggestionStateId], "count stateId=$suggestionStateId"
		);
	}

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
			select
				a.*, i.*,
				-- user data
				u.fullName, po.role as projectRole,
				-- minimal data for release item
				r.version as releaseVersion, r.body as releaseText,
				-- minimal data for suggestion items
				s.title, s.numOfVotes, s.numOfComments, s.seoUrlTitle, s.seoUrlId,
				-- minimal data for comment item
				c.comment
			from
				activity a
				left join suggestions s on (s.suggestionId = a.refId and type between 100 and 200)
				left join suggestionComments c on (c.commentId = a.refId and type between 200 and 300)
				left join releases r on (r.releaseId = a.refId and type between 400 and 500)
				left join users u on (a.userId = u.userId)
				left join assets i on (u.profileMediumAssetId = i.assetId)
				left join projectOwners po on (u.userId = po.userId and po.projectId = a.appId)
			where
				a.appId = :appId
			order by
				a.happenedAt desc
			", ["appId" => $vm->appId], "activity feed"
		);

		$p->next("Activity Feed VMs");

		$vm->events = [];

		foreach($activity as $item)
		{
			$eventVM = null;

			switch((int) ($item->type / 100))
			{
				case 1:
					$eventVM = new UserPostedSuggestion($ctx, $item);
					break;

				case 4:
					$eventVM = new DeveloperPostedReleaseNotes($ctx, $item);
					break;
			}

			if($eventVM != null)
			{	// we matched a ViewModel to the event's DTO
				// append it to the list
				$vm->events []= $eventVM;
			}
		}
		$p->next("Render");

		$this->renderTemplate($vm, 'Site', 'App/News');

		$p->stop();
	}
}
