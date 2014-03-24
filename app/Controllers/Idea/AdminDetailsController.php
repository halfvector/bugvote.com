<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\TimeHelper;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\DAL;
use Bugvote\Core\Dropdown;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\ViewModels\IdeaRootVM;

class IdeaViewVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		// all of this prep is pretty much nothing compared to actual sql-queries and even cache-hits on local APC/Redis instances
		// 0.07ms prep, 0.30ms cache hit, 1.4 ms db query, 2.3 ms full cache miss

		$ideaId = $row->suggestionId;

		$this->authorImg = new ImageUrlGenerator($ctx, ImageAsset::create($row->profileMediumAssetId, $row->originalFilename));

		$this->postedTimestamp = TimeHelper::MySQLTimestampToISO8601($row->postedAt);
		$this->revisionTimestamp = TimeHelper::MySQLTimestampToISO8601($row->updatedAt);

		$this->isRevised = $row->revisionReason ? 1 : 0;

		$this->numOfVotes = $ctx->dal->fetchSingleValue(
			"select coalesce(sum(vote),0) as votes from suggestionVotes where suggestionId = :suggestionId",
			['suggestionId' => $ideaId],
			"suggestion vote count"
		);

		$this->numOfRevisions = rand(0,10);

		// returns either the vote value or FALSE when there is none. both work.
		$userVote = $ctx->dal->fetchSingleValue(
			"select vote from suggestionVotes where suggestionId = :suggestionId and userId = :userId",
			['suggestionId' => $ideaId, "userId" => $ctx->user->getUserId()],
			"user's vote (up or down)"
		);

		$this->isUpvoted = $userVote > 0;
		$this->isDownvoted = $userVote < 0;

		$this->seoUrl = $ctx->url->createIdeaUrl($row->seoUrlId, $row->seoUrlTitle);

		$this->editUrl = $this->seoUrl . "/edit"; // POST: /i/7qn/edit
		$this->voteUrl = $this->seoUrl . "/vote"; // POST: /i/7qn/vote
		$this->revisionHistoryUrl = $this->seoUrl . "/history"; // POST: /i/7qn/history

		$this->isBugFix = $this->suggestionTypeId == 1 ? true : false;
		$this->hasTag = $this->suggestionTypeId >= 1;
	}
}

class BugCommentVM extends ViewModelBase
{
	function extend(Context $ctx, $comment)
	{
		//$this->authorImg = $ctx->assetManager->GetWebPathForAsset($comment->profileMediumAssetId);
		$this->urlProfileImage = new ImageUrlGenerator($ctx, new ImageAsset($comment));
		$this->postAge = $ctx->api->SmartShortAge($comment->postAgeSec);
		$this->urlViewUserProfile = $ctx->url->createUserUrl($this->userId);

		//$comment->comment = $markdownParser->transformMarkdown($comment->comment);
		//$comment->comment = trim($comment->comment);
		$this->comment = nl2br($comment->comment);
	}
}

class BugAttachmentVM extends ViewModelBase
{
	function extend(Context $ctx, $attachment)
	{
		$this->isImage = strstr($attachment->mimeType, "image") !== false;

		if($this->isImage)
			//$this->assetUrl = $ctx->assetManager->getResizeUrl($attachment->assetId, $attachment->originalFilename, 100, 70);
			$this->urlResizedImage = new ImageUrlGenerator($ctx, new ImageAsset($attachment));

		$this->urlAsset = $ctx->assetManager->getWebFullPath($attachment->assetId, $attachment->originalFilename);
	}
}

class UserVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		//$this->avatarUrl = $ctx->assetManager->GetWebPathForAsset($row->profileMediumAssetId) . $row->originalFilename;
		$this->urlProfilePic = new ImageUrlGenerator($ctx, new ImageAsset($row));
	}
}

class AdminDetailsController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new IdeaRootVM($ctx);

		// the latest revision is in the suggestions table
		// no need to touch suggestionRevisions here

		$ideaDM = $ctx->dal->fetchSingleObj(
			"select
				s.appId, s.seoUrlId, s.suggestionId, u.*, s.seoUrlTitle, a.originalFilename,
            	s.postedAt, s.revisionDate as updatedAt,
            	s.suggestionTypeId, s.suggestionStateId,
            	s.formattedDescription as description, s.title,
            	s.revisionReason, s.revisionDate

            	-- unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt) as originalAgeSec,
            	-- unix_timestamp(utc_timestamp()) - unix_timestamp(s.revisionDate) as revisionAgeSec
            from suggestions s
            	join users u using (userId)
            	left join assets a on (assetId = profileMediumAssetId)
            where
            	suggestionId = :suggestionId",
			[":suggestionId" => $vm->ideaId],
			"suggestion details"
		);

		$vm->idea = new IdeaViewVM($ctx, $ideaDM);
		$vm->setPrimaryMenuItem("ideas");

		// app title, url, for app-header
		$vm->app = $ctx->dal->fetchSingleObj(
			"select *
			from projects
			left join assets on (assetId = thumbnailAssetId)
			where projectId = :id",
			[':id' => $vm->idea->appId],
			"project from projectId"
		);

		// app thumbnail
		if($vm->app->thumbnailAssetId)
			$vm->app->imgUrl = $ctx->assetManager->getResizeUrl($vm->app->assetId, $vm->app->originalFilename, 100, 100);
		else
			$vm->app->imgUrl = '/img/placeholders/200x150.gif';

		$vm->app->urlViewApp = $ctx->url->viewApp($vm->app->seoUrlTitle);

		// comment DataModels
		$comments = $ctx->dal->fetchMultipleObjs("
			select s.*, unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt) as postAgeSec, u.*, a.*
            from suggestionComments s
            join users u using (userId)
            left join assets a on (assetId = profileMediumAssetId)
            where suggestionId = :suggestionId
            order by commentId",
			["suggestionId" => $vm->ideaId],
			"suggestion comments"
		);

		// BugComment array from rows
		$vm->comments = BugCommentVM::createCollection($ctx, $comments);
		$vm->hasComments = count($vm->comments) > 0;

		// attachment DataModels
		$attachments = $ctx->dal->fetchMultipleObjs("
			select * from suggestionAttachments
			left join assets using (assetId)
			left join attachmentTypes using (attachmentType)
			where suggestionId = :suggestionId
			" , [":suggestionId" => $vm->ideaId], "attachments"
		);

		$vm->attachments = BugAttachmentVM::createCollection($ctx, $attachments);

		$vm->hasAttachments = count($vm->attachments) > 0;

		$userDM = $ctx->dal->fetchSingleObj('
			select *
			from users
			left join assets on (assetId = profileMediumAssetId)
			where userId = :userId',
			[':userId' => $vm->userId],
			"user info"
		);

		$vm->user = new UserVM($ctx, $userDM);

		// build urls
		$urlBaseBug = '/i/' . $ctx->parameters->strings->ideaId . '/' . $ctx->parameters->strings->seoUrl;

		$vm->ideaEditUrl = $urlBaseBug . "/edit";
		$vm->urlSubmitComment = $urlBaseBug . "/comment";
		$vm->discussionUrl = $vm->idea->seoUrl . "/talk";
		$vm->urlAdminIdea = $urlBaseBug . "/admin";
		$vm->urlDeleteIdea = $urlBaseBug . "/delete";

		$vm->urlUserProfileView = UrlHelper::createUserUrl($vm->userId);

		$vm->canEditIdea = $ctx->permissions->CanModifySuggestion($vm->ideaId);
		$vm->canAdminIdea = $ctx->permissions->GetUserProjectPermissions($vm->userId, $vm->idea->appId) == 1001;

		$vm->suggestionType = SuggestionData::$suggestionTypes[$vm->idea->suggestionTypeId];
		$vm->suggestionState = SuggestionData::$suggestionStates[$vm->idea->suggestionStateId];
		$vm->suggestionProgress = $vm->idea->suggestionStateId * 20;

		// if user is developer of the app, give her special permissions
		$vm->isDeveloper = true;

		$vm->suggestionTypes = new Dropdown("suggestionType", SuggestionData::$suggestionTypes, $vm->idea->suggestionTypeId);
		$vm->suggestionStates = new Dropdown("suggestionState", SuggestionData::$suggestionStates, $vm->idea->suggestionStateId);

		//$vm->setSecondaryButton('ideas');
		//$vm->setThirdButton('/');
		$this->renderTemplate($vm, 'Site', 'Idea/Developer/Manage');
	}

	// POST: /i/7ql/title/admin
	function update(Context $ctx)
	{
		// extract correct idea url using id
		// dont trust the user provided title

		$id = $ctx->parameters->strings->ideaId;
		$url = UrlHelper::buildSuggestionUrlFromTinyId($ctx, $id);

		$userId = $ctx->user->getUserId();

		// if user canceled edit, take us back to source
		if($ctx->request->action == 'discard')
			return $ctx->redirect($url);

		$data = $this->getRequirements([
			'suggestionId' => ['type' => 'string'],
			'suggestionState' => ['type' => 'int'],
			'suggestionType' => ['type' => 'int'],
			'adminAction' => ['type' => 'string']
		]);

		if($data->adminAction == "save")
		{
			$ctx->dal->update('suggestions')->set([
				'suggestionStateId' => $data->suggestionState,
				'suggestionTypeId' => $data->suggestionType,
			])->where(['seoUrlId' => $data->suggestionId]);

			$regularId = UrlHelper::decodeTinyId($id);

			$ctx->smartCache->invalidate("suggestions.suggestionId=$regularId");
		}

		return $ctx->redirect($url);
	}
}