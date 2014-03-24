<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\FormGatherer;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\DAL;
use Bugvote\Core\FormVariables;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\BugAttachment;
use Bugvote\DataModels\ImageAsset;
use Bugvote\DataModels\Privileges;
use Bugvote\Services\Context;

use Bugvote\ViewModels\IdeaRootVM;
use Michelf\MarkdownExtra;

class ThumbnailAttachmentVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		$this->urlThumbnailImg = new ImageUrlGenerator($ctx, new ImageAsset($row));
	}
}

class EditController extends BaseController
{
    function show(Context $ctx)
    {
        $vm = new IdeaRootVM($ctx);

        $vm->idea = $ctx->dal->fetchSingleObj('
            select s.appId, s.suggestionId, u.*, r.description, r.title
            from suggestions s
            join users u using (userId)
            left join suggestionRevisions r using (suggestionId)
            where suggestionId = :suggestionId
            group by suggestionId, revisionId
            order by revisionId desc
        ', [':suggestionId' => $vm->ideaId]);

        // expand images
        $vm->idea->authorImg = $ctx->assetManager->GetWebPathForAsset($vm->idea->profileMediumAssetId);

	    //new Privileges($ctx, $vm->userId, $vm->appId)

	    $vm->canEditBug = $vm->idea->userId == $vm->userId;

        if(!$vm->canEditBug)
        {	// user is not allowed to be here
            // can show a permissions denied page, or just redirect, or both.
            //$this->renderTemplate($vm, 'Site', 'Idea/Errors/EditNotAllowed');
            //return;
        }

	    // fetch DMs

        $attachmentDMs = $ctx->dal->fetchMultipleObjs("
			select * from suggestionAttachments
			natural left join assets
			where suggestionId = :suggestionId
			"
            , [":suggestionId" => $vm->ideaId]
        );

	    // create VMs

	    $vm->attachments = ThumbnailAttachmentVM::createCollection($ctx, $attachmentDMs);
	    $vm->hasAttachments = count($vm->attachments) > 0;

        $this->renderTemplate($vm, 'Site', 'Idea/Edit');
    }

    function commit(Context $ctx)
    {
	    $userId = $ctx->user->getUserId();
	    $ideaId = $ctx->parameters->ideaId->decodeId();

	    //if(!$ctx->permissions->CanUserModifySuggestion($userId, $ideaId))
	    //{   // TODO: show permissions error
	    //}

	    $urlViewIdea = $ctx->url->createSuggestionUrl($ctx, $ideaId);

//	    $data = $this->getRequirements([
//		    'description' => ['type' => 'markdown', 'minLength' => 3, 'persistent' => true],
//		    'title' => ['type' => 'string', 'minLength' => 3, 'persistent' => true],
//		    'action' => ['type' => 'string', 'minLength' => 3],
//		    'suggestionId' => ['type' => 'int'],
//		    'reason' => ['type' => 'string', 'minLength' => 3, 'optional' => true, 'default' => ""],
//		    'tags' => ['type' => 'array', 'optional' => true],
//		    'attachments' => ['type' => 'file', 'optional' => true],
//		    'keepAttachments' => ['type' => 'array', 'optional' => true]
//	    ]);

	    $data = $this->gather()
		        ->keepAttachments->asArray()
	            ->title->isRequired()->asString()
	            ->description->isRequired()->asMarkdown()
	            ->attachments->asFileArray()
	            ->suggestionId->isRequired()->asInt()
	            ->action->asString()
	            ->reason->asString()
	            ->tags->asArray()
		    ->asObject();


//	    $data = $this->gather()
//	        ->required()
//			    ->title->asString()
//			    ->description->asMarkdown()
//			    ->suggestionId->asInt()
//		        ->action->asString()
//	        ->optional()
//			    ->keepAttachments->asIntArray()
//			    ->attachments->asFileArray()
//			    ->reason->asString()
//			    ->tags->asArrayFromString()
//		    ->asObject();

	    // if form had some errors, redirect back to the form page (alerts will be shown)
	    if(!$data)
		    return $ctx->redirect($urlViewIdea . '/edit');

	    // if user canceled edit, take us back to source
	    if($data->action == 'cancel')
		    return $ctx->redirect($urlViewIdea);


	    $ctx->log->write("Updating suggestion #$ideaId");

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

		    $bugAttachment = new BugAttachment($ctx);
		    $bugAttachment->createFromFileUpload($userId, $ideaId, $file);
	    }

	    // build new safe-url title
	    $urlSafeTitle = $ctx->url->seoUrl($data->title);

	    $formatted = MarkdownExtra::defaultTransform($data->description);

	    $revisionDate = $ctx->dal->getCurrentDateTimeIso8601();

	    // save new revision into history (this may be wasteful for the initial revision, but easier to work with)
	    $ctx->dal->insert('suggestionRevisions')->set([
		    'suggestionId' => $ideaId,
		    'description' => $data->description,
		    'title' => $data->title,
		    'userId' => $userId,
		    'revisionDate' => $revisionDate,
		    'revisionReason' => $data->reason,
	    ]);

	    // update head revision to point to the newest revision
	    $ctx->dal->update('suggestions')->set([
		    'suggestion' => $data->description,
		    'formattedDescription' => $formatted,
		    'title' => $data->title,
		    'seoUrlTitle' => $urlSafeTitle,
		    'revisionDate' => $revisionDate,
		    'revisionReason' => $data->reason,
	    ])->where(['suggestionId' => $ideaId]);

	    // create a new url with the (possibly) new title
	    $urlViewIdea = $ctx->url->createSuggestionUrl($ctx, $ideaId);

	    //$ctx->flash("idea updated!", 'info');
	    return $ctx->redirect($urlViewIdea);
    }
}