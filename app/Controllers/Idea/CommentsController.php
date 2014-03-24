<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Core\FormVariables;
use Bugvote\Services\Context;
use Michelf\MarkdownExtra;

class CommentsController extends BaseController
{
	function commit(Context $ctx)
	{
		$form = new FormVariables();

		$ideaId         = $form->ideaId->asInt();
		$commentMarkup  = $form->comment;
		$userId         = $ctx->user->getUserId();
		$postedAt       = $ctx->dal->getCurrentDateTimeIso8601();

		$commentHTML = MarkdownExtra::defaultTransform($commentMarkup);

		$ctx->dal->insert('suggestionComments')->set([
			'suggestionId'  => $ideaId,
			'comment'       => $commentHTML,
			'userId'        => $userId,
			'postedAt'      => $postedAt,
		]);

		$url = $ctx->url->createSuggestionUrl($ctx, $ideaId);
		$ctx->redirect($url);
	}
}
