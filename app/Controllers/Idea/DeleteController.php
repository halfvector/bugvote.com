<?php namespace Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\UrlHelper;
use Bugvote\Services\Context;
use Bugvote\Core\DAL;
use Bugvote\Core\PerformanceLog;
use dflydev\markdown\MarkdownExtraParser;
use ViewModels\IdeaRootVM;

class DeleteController extends BaseController
{
    function confirm(Context $ctx)
    {
        $vm = new IdeaRootVM($ctx);

        $this->renderTemplate($vm, 'App', 'Idea/ConfirmDelete');
    }

    function commit(Context $ctx)
    {
        $ideaId = UrlHelper::decompressId($ctx->parameters->strings->ideaId);

        if($ctx->request->action == 'cancel')
            return $ctx->redirect(UrlHelper::createSuggestionUrl($ctx, $ideaId));

        $appId = $ctx->dal->fetchSingleValue("select appId from suggestions where suggestionId = :id", ["id" => $ideaId]);

        // TODO: assign suggestion to the delete-request-queue for approval by moderators

        // HACK: for now, actually delete suggestion and all associated data here
        $ctx->dal->deleteSingleObj("delete from suggestionComments where suggestionId = :id", ["id" => $ideaId]);
        $ctx->dal->deleteSingleObj("delete from suggestionVotes where suggestionId = :id", ["id" => $ideaId]);
        $ctx->dal->deleteSingleObj("delete from suggestions where suggestionId = :id", ["id" => $ideaId]);

        return $ctx->redirect(UrlHelper::appUrl($ctx, $appId));
    }
}