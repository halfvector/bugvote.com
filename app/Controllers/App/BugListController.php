<?php namespace Bugvote\Controllers\App;

use Bugvote\Commons\BaseController;
use Bugvote\DataModels\HottestIdeasDataModel;
use Bugvote\DataModels\NewestIdeasDataModel;
use Bugvote\Services\Context;
use Bugvote\ViewModels\AppRootVM;
use Bugvote\ViewModels\SuggestionItemViewModel;

class BugListController extends BaseController
{
	/** @route /a/[:appUrl]/ideas/vote */
	function vote(Context $ctx)
	{
		$ctx->log->writeObject("post data", $_POST);
		$userId = $ctx->user->getUserId();

		$appUrlTitle = $ctx->parameters->strings->appUrl;
		$urlViewApp = $ctx->url->viewApp($appUrlTitle);

		$data = $this->getRequirements([
			'idea' => ['type' => 'int'],
			'vote' => ['type' => 'int'],
		]);

		// if form had some errors, redirect back to the form page (alerts will be shown)
		if (!$data)
			return $ctx->redirect($urlViewApp);

		// sanity check
		if ($data->idea <= 0) {
			$ctx->auditor->log("Sanity failure: \$data->idea ({$data->idea}) <= 0");
			return $ctx->redirect($urlViewApp);
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

		$ctx->redirect($urlViewApp);
	}

	/** @route /a/[:appUrl]/ideas/newest */
	function showLatestBugs(Context $ctx)
	{
		$vm = new AppRootVM($ctx);
		$vm->setPrimaryMenuItem("ideas");

		$vm->urlCreateSuggestion = $vm->urlViewApp . "/submit";

		$bugsDM = new NewestIdeasDataModel($ctx);
		$bugsList = $bugsDM->getBugs($vm->appId, $vm->userId);

		$vm->suggestions = SuggestionItemViewModel::createCollection($ctx, $bugsList);

		$vm->newestClass = "active";
		$vm->hottestIdeas = $vm->urlViewApp . "/ideas";
		$vm->newestIdeas = $vm->urlViewApp . "/ideas/newest";
		$vm->searchUrl = $vm->urlViewApp . "/search";

		$this->renderTemplate($vm, 'Site', 'App/Ideas');
	}

	/** @route /a/[:appUrl]/ideas */
	function showHottestBugs(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "ideas");

		$vm->urlCreateSuggestion = $vm->urlViewApp . "/submit";

		$p = $ctx->perf->start("Fetch Hottest Ideas");

		$suggestionDMs = (new HottestIdeasDataModel($ctx))->getBugs($vm->appId, $vm->userId);

		$p->next("Transform DMs to VMs");

		$vm->suggestions = SuggestionItemViewModel::createCollection($ctx, $suggestionDMs);

		$vm->hottestClass = "active";
		$vm->hottestIdeas = $vm->urlViewApp . "/ideas";
		$vm->newestIdeas = $vm->urlViewApp . "/ideas/newest";
		$vm->searchUrl = $vm->urlViewApp . "/search";
		$vm->urlVoteShortcut = $vm->urlViewApp . "/ideas/vote";

		$p->next("Render");

		$this->renderTemplate($vm, 'Site', 'App/Ideas');

		$p->stop();
	}
}

