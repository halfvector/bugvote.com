<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\TimeHelper;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\UserRoles;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\DAL;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\DataModels\Privileges;
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

		$this->devStartTimestamp = date("c", time() - 153456);
		$this->devFinishedTimestamp = date("c", time() - 234);

		$this->isRevised = $row->revisionReason ? 1 : 0;

		$this->numOfVotes = $ctx->dal->fetchSingleValue(
			"select coalesce(sum(vote),0) as votes from suggestionVotes where suggestionId = :suggestionId",
			['suggestionId' => $ideaId],
			"suggestion vote count"
		);

		// TODO: make this real
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
		//$this->upvoteUrl = $this->seoUrl . "/upvote"; // POST: /i/7qn/upvote_toggle
		//$this->downvoteUrl = $this->seoUrl . "/downvoteW"; // POST: /i/7qn/downvote_toggle
		$this->upvoteState = $userVote > 0 ? 1 : 0;
		$this->downvoteState = $userVote < 0 ? 1 : 0;

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
		//$this->postAge = $ctx->api->SmartShortAge($comment->postAgeSec);
		//$this->urlViewUserProfile = $ctx->url->createUserUrl($this->userId);

		//$comment->comment = $markdownParser->transformMarkdown($comment->comment);
		//$comment->comment = trim($comment->comment);
		$this->comment = nl2br($comment->comment);

		//var_dump($comment);

		// calculate permissions for current user

		$currentUserId = $ctx->user->getUserId();

		// TODO: if user is a moderator or admin, she can edit the comment too
		$this->canEdit = $comment->userId == $currentUserId;
		$this->canDelete = $comment->userId == $currentUserId;

		$permissions = $ctx->permissions->GetUserProjectPermissions($currentUserId, $ctx->appId);
		if(UserRoles::HasRole($permissions, UserRoles::Admin))
		{
			$this->canEdit = true;
			$this->canDelete = true;
		}
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

class CategoryVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		$this->data = $ctx->csrf->encode(["id" => $this->categoryId, "name" => $this->category]);
	}
}

class LocalContext
{
	protected $ctx;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}
}

class NewBugDetailsController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new IdeaRootVM($ctx);

		///////
		// gather all data (easy to cache it all here)

		// idea details
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

		// scheduling categories
		$categories = $ctx->dal->fetchMultipleObjs(
			"select * from roadmapCategories where projectId = :appId",
			['appId' => $vm->appId]
		);

		// app title, url, for app-header
		$appDM = $ctx->dal->fetchSingleObj(
			"select *
				from projects
				left join assets on (assetId = thumbnailAssetId)
				where projectId = :id",
			[':id' => $vm->appId],
			"project from projectId"
		);

		// comments
		$comments = $ctx->dal->fetchMultipleObjs(
			"select s.*, unix_timestamp(utc_timestamp()) - unix_timestamp(s.postedAt) as postAgeSec, u.*, a.*
				from suggestionComments s
				join users u using (userId)
				left join socialAccounts sa using (userId)
				left join assets a on (assetId = profilePicAssetId)
			where suggestionId = :suggestionId
			group by commentId
			order by commentId",
			["suggestionId" => $vm->ideaId],
			"suggestion comments"
		);

		// attachments
		$attachments = $ctx->dal->fetchMultipleObjs("
			select * from suggestionAttachments
				left join assets using (assetId)
				left join attachmentTypes using (attachmentType)
			where suggestionId = :suggestionId
			" , [":suggestionId" => $vm->ideaId], "attachments"
		);

		// user details
		$userDM = $ctx->dal->fetchSingleObj('
			select *
				from users
				left join assets on (assetId = profileMediumAssetId)
			where userId = :userId',
			[':userId' => $vm->userId],
			"user info"
		);

		///////
		// format the data, building our ViewModel
		// ideally no database access should happen below

		$vm->csrf = $ctx->csrf->getCSRF("user details"); // this should probably be done in the base/root VM

		$vm->idea = new IdeaViewVM($ctx, $ideaDM);
		$vm->setPrimaryMenuItem("ideas");

		$vm->app = $appDM;

		// app thumbnail
		if($vm->app->thumbnailAssetId)
			$vm->app->imgUrl = $ctx->assetManager->getResizeUrl($vm->app->assetId, $vm->app->originalFilename, 100, 100);
		else
			$vm->app->imgUrl = '/img/placeholders/200x150.gif';

		$vm->app->urlViewApp = $ctx->url->viewApp($vm->app->seoUrlTitle);

		// FIXME: don't inject random data into Context!
		$ctx->appId = $vm->appId;

		// create child VMs
		$vm->comments = BugCommentVM::createCollection($ctx, $comments);
		$vm->attachments = BugAttachmentVM::createCollection($ctx, $attachments);
		$vm->user = new UserVM($ctx, $userDM);
		$vm->schedules = CategoryVM::createCollection($ctx, $categories);

		$vm->numComments = count($vm->comments);
		$vm->hasComments = count($vm->comments) > 0;
		$vm->hasAttachments = count($vm->attachments) > 0;

		///////
		// generate URLs

		$urlBaseBug = '/i/' . $ctx->parameters->ideaId . '/' . $ctx->parameters->seoUrl;

		$vm->urlEditIdea = $urlBaseBug . "/edit";
		$vm->urlSubmitComment = $urlBaseBug . "/comment";
		$vm->discussionUrl = $vm->idea->seoUrl . "/talk";
		$vm->urlAdminIdea = $urlBaseBug . "/admin";
		$vm->urlDeleteIdea = $urlBaseBug . "/delete";
		$vm->urlUserProfileView = $ctx->url->createUserUrl($vm->userId);
		$vm->urlPlan = $urlBaseBug . '/plan';
		$vm->urlPostReschedule = $urlBaseBug . "/reschedule";

		///////
		// calculate permissions

		// FIXME: defunc, Privileges class should take care of all this on-demand
		$vm->canEditIdea = $ctx->permissions->CanModifySuggestion($vm->ideaId);
		$vm->canAdminIdea = $ctx->permissions->GetUserProjectPermissions($vm->userId, $vm->idea->appId) == 1001;

		$vm->privileges = new Privileges($ctx, $vm->userId, $vm->appId);

		$vm->suggestionType = SuggestionData::$suggestionTypes[$vm->idea->suggestionTypeId];
		$vm->suggestionState = SuggestionData::$suggestionStates[$vm->idea->suggestionStateId];
		$vm->suggestionProgress = $vm->idea->suggestionStateId * 20;

		$vm->secondaryNavTemplate = 'Idea/DetailsAdminPanel';
		$this->renderTemplate($vm, 'Site', 'Idea/Details');

		///////
		// push server-side analytics after rendering to client has finished and data has been handed off
		// ideally this should happen after PHP-FPM released data to NGINX, so no extra delay will appear
		// and any failure here will be completely silent

		//Analytics::track($vm->userId, "Viewed Page", ["Section" => "New Idea View", "Idea" => $vm->ideaUrlTitle]);
	}

	// POST: commit idea edit
	function update(Context $ctx)
	{
		$suggestionTinyId = $ctx->parameters->strings->ideaId;

		$suggestionTitle = $ctx->dal->fetchSingleValue("select seoUrlTitle from suggestions where seoUrlId = :id", [":id" => $suggestionTinyId], "seoUrlId->seoUrlTitle lookup");
		$suggestionUrl = UrlHelper::createIdeaUrl($suggestionTinyId, $suggestionTitle);

		$userId = $ctx->user->getUserId();

		// if user canceled edit, take us back to source
		if($ctx->request->action == 'cancel')
			return $ctx->redirect($suggestionUrl);

		$data = $this->getRequirements([
			'suggestionId' => ['type' => 'int'],
			'description' => ['type' => 'markdown', 'minLength' => 3, 'persistent' => true],
			'title' => ['type' => 'string', 'minLength' => 3, 'persistent' => true],
			'action' => ['type' => 'string', 'minLength' => 3],
			'reason' => ['type' => 'string', 'minLength' => 3, 'optional' => true, 'default' => ""],
			'tags' => ['type' => 'array', 'optional' => true],
			'attachments' => ['type' => 'file', 'optional' => true]
		]);

		// if form had some errors, redirect back to the form page (alerts will be shown)
		if(!$data)
			return $ctx->redirect($suggestionUrl . '/edit');

		$ctx->auditor->log("Updating suggestion #$data->suggestionId");

		// create assets
		for($i = 0; $i < count($data->attachments["name"]); $i++)
		{
			$name = $data->attachments["name"][$i];
			$type = $data->attachments["type"][$i];
			$tmp_name = $data->attachments["tmp_name"][$i];
			$error = $data->attachments["error"][$i];
			$size = $data->attachments["size"][$i];

			if(!$size)
				continue;

			$file = ["name" => $name, "type" => $type, "tmp_name" => $tmp_name, "error" => $error, "size" => $size];

			$ctx->log->write("File posted by browser: name=$name type=$type tmp_name=$tmp_name error=$error size=$size");

			$ctx->api->CreateSuggestionAttachment($userId, $data->suggestionId, $file);
		}

		/*
		// save the old revision into history
		$ctx->dal->updateSingleObj(
			"insert into suggestionRevisions (suggestionId, title, description, revisionDate, userId, revisionReason)
				(select suggestionId, title, suggestion as description, utc_timestamp(), userId, revisionReason from suggestions where suggestionId = :suggestionId)
			", [":suggestionId" => $data->suggestionId]
		);
		*/

		$urlSafeTitle = UrlHelper::seoUrl($data->title);

		// save new revision into history (this may be wasteful for the initial revision, but easier to work with)
		$ctx->dal->insert('suggestionRevisions')->set([
			'suggestionId' => $data->suggestionId,
			'description' => $data->description,
			'title' => $data->title,
			'userId' => $userId,
			'revisionDate' => $ctx->dal->getCurrentDateTimeIso8601(),
			'revisionReason' => $data->reason,
		]);

		// update head revision to point to the newest revision
		$ctx->dal->update('suggestions')->set([
			'suggestion' => $data->description,
			'title' => $data->title,
			'seoUrlTitle' => $urlSafeTitle,
			'revisionDate' => $ctx->dal->getCurrentDateTimeIso8601(),
			'revisionReason' => $data->reason,
		])->where(['suggestionId' => $data->suggestionId]);

		// invalidate cache
		$ctx->smartCache->invalidate("suggestions.suggestionId=$data->suggestionId");

		/* // whats prettier, programmatically above or raw sql below?
		$ctx->dal->updateSingleObj(
			"update suggestions
				set suggestion = :description, revisionReason = :reason, revisionDate = utc_timestamp()
				where suggestionId = :id",
			[":id" => $data->suggestionId, ":description" => $data->description, ":revisionReason" => $data->reason]
		);
		*/

		// create a new url with the (possibly) new title

		$suggestionUrl = UrlHelper::createIdeaUrl($suggestionTinyId, $urlSafeTitle);
		UrlHelper::invalidateUrlTitleFromUrlIdCache($ctx, $suggestionTinyId);

		return $ctx->redirect($suggestionUrl);
	}
}