<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\DataModels\Activity;
use Bugvote\Services\Context;
use Bugvote\ViewModels\AppRootVM;
use Exception;
use Michelf\MarkdownExtra;

class CreateController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "ideas");
		$this->renderTemplate($vm, 'Site', 'App/NewIdea');
	}

	function commit(Context $ctx)
	{
		// if user canceled edit, take us back to source
		if ($ctx->parameters['action'] == 'cancel')
			return $ctx->redirect($ctx->url->viewApp($ctx->parameters->strings->appUrl));

		$data = $this->getRequirements([
			'appId' => ['type' => 'string'],
			'details' => ['type' => 'string', 'minLength' => 3],
			'title' => ['type' => 'string', 'minLength' => 3],
			'tags' => ['type' => 'string', 'optional' => true],
		]);

		// if form had some errors, redirect back to the form page (alerts will be shown)
		if(!$data)
			return $ctx->redirect($ctx->url->createAppNewIdeaUrl($ctx->parameters->strings->appUrl));

        $appId = $data->appId;
		$userId = $ctx->user->getUserId();
		$date = $ctx->dal->getCurrentDateTimeIso8601();
		$formatted = MarkdownExtra::defaultTransform($data->details);

        // create a nice-url title
		$seoTitle = $ctx->url->seoUrl($data->title);

		// we have a few built-in system-tags
		// * bug-report (defect)
		// * feature-request (new idea)

		// app-devs should be able to define additional tags
		// and users to define arbitrary tags

		// so the heirarchy of tags is 3 levels deep: system, developer-specified, user-specified

		$suggestionTypeId = 0;

		$tags = explode(',', $data->tags);

		if(in_array("bug", $tags))
			$suggestionTypeId = 1;
		if(in_array("bug", $tags))
			$suggestionTypeId = 2;

		$tags = array_filter($tags, function($tag){
			return strstr($tag, "system.") === false;
		});


		$ctx->dal->beginTransaction();

		try
		{
			// insert suggestion
			$ideaId = $ctx->dal->insert('suggestions')->set([
				'appId' => $appId, 'title' => $data->title, 'suggestion' => $data->details, 'formattedDescription' => $formatted,
				'postedAt' => $date, 'userId' => $userId, 'seoUrlTitle' => $seoTitle,
	            'suggestionStateId' => 1,
	            'suggestionTypeId' => $suggestionTypeId,
			]);

			// insert tags
			foreach($tags as $tag) {
				$tagId = $ctx->dal->insertSingleObj('insert into tags set tag = :tag', ['tag' => $tag]);
				$ctx->dal->insert('suggestionTag')->set(['suggestionId' => $ideaId, 'tagId' => $tagId]);
			}

	        // update suggestion with an seo-friendly tiny-id
			$seoId = $ctx->url->encodeIdeaId($ideaId);
			$ctx->dal->update("suggestions")->set(['seoUrlId' => $seoId])->where(['suggestionId' => $ideaId]);

			// insert the initial revision
			$ctx->dal->insert('suggestionRevisions')->set([
				'suggestionId' => $ideaId, 'title' => $data->title, 'description' => $data->details,
				'revisionDate' => $date, 'userId' => $userId
			]);

			// and automatically vote for our new suggestion (reddit style)
			$ctx->dal->insert("suggestionVotes")->set(["vote" => 1, "userId" => $userId, "suggestionId" => $ideaId]);

			$ctx->dal->commitTransaction();
		}
		catch(Exception $err)
		{
			$ctx->dal->rollbackTransaction();
			$ctx->klein->flash("Error commiting to database. Oops!", "error");
			return $ctx->redirect($ctx->url->createAppNewIdeaUrl($ctx->parameters->strings->appUrl));
		}

		// record activity (non fatal)
		$activity = new Activity($ctx);
		$activity->recordNewBug($appId, $userId, $ideaId, $date);

		$newIdeaUrl = $ctx->url->createIdeaUrl($seoId, $seoTitle);
		return $this->context->redirect($newIdeaUrl, 303);
	}
}