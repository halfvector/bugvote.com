<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Core\DAL;
use Bugvote\Services\Context;

class DetailsController extends BaseController
{
	function show(Context $ctx)
	{
		$ideaUrlId = $ctx->parameters->ideaId;
		$ideaId = $ctx->url->decompressId($ideaUrlId);

		// grab suggestion details
		$suggestion = $ctx->dal->fetchSingleObj(
			"select appId, suggestionStateId from suggestions where suggestionId = :id",
			[":id" => $ideaId],
			"appId of suggestion"
		);

		// choose view based on current state of the suggestion
		switch($suggestion->suggestionStateId)
		{
			case 1:
			case 3:
				// under discussion (new)
				$ctx->log->write("Suggestion in discussion");
				$ctx->log->write("Forwarding to NewBugDetailsController::Show()");
				$controller = new NewBugDetailsController($ctx);
				$controller->show($ctx);
				break;

			case 4:
				// under construction (in garage)
				$ctx->log->write("Suggestion under development");
				$ctx->log->write("Forwarding to GarageIdeaController::Show()");
				$controller = new GarageIdeaController($ctx);
				$controller->show($ctx);
				break;

			case 5:
				// completed (hooray!)
				$ctx->log->write("suggestion is completed");
				break;

			default: // unhandled
				$ctx->log->write("suggestionStateId Unknown: $suggestion->suggestionStateId");
		}
	}

	// ajax call
	function vote(Context $ctx)
	{
		$ideaId = $ctx->url->decompressId($ctx->parameters->strings->ideaId);
		$userId = $ctx->user->getUserId();

		$urlViewIdea = $ctx->url->createSuggestionUrl($ctx, $ideaId);

		$data = $this->getRequirements([
			'idea' => ['type' => 'int'],
			'vote' => ['type' => 'int'],
		], false);

		// if form had some errors, redirect back to the form page (alerts will be shown)
		if(!$data)
		{
			return $this->renderJson(['success' => false]);
		}

		// sanity check
		if($data->idea != $ideaId)
		{
			$ctx->auditor->log("Sanity failure: \$data->idea ({$data->idea}) != \$ideaId ($ideaId)");
			return $this->renderJson(['success' => false]);
		}

		$voteValue = $data->vote; // expected: -1, 0, 1

		// create new revision, set it to head
		$ctx->dal->replace('suggestionVotes', "replacing suggestionVote")->set([
			'suggestionId' => $data->idea,
			'userId' => $userId,
			'vote' => $voteValue
		]);

		$ctx->dal->insert('suggestionVoteHistory', "growing voteHistory")->set([
			'suggestionId' => $data->idea,
			'userId' => $userId,
			'vote' => $voteValue,
			'votedAt' => $ctx->dal->getCurrentDateTimeIso8601()
		]);

		$ctx->auditor->log("user $userId voted $voteValue on bug $data->idea");

		$result = [
			'success' => true
		];

		return $this->renderJson($result);
	}
}