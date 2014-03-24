<?php namespace Bugvote\Controllers\App;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\FormVariables;
use Bugvote\Services\Context;
use Bugvote\ViewModels\AppRootVM;
use Michelf\MarkdownExtra;

class AjaxBuglistItem extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		$this->description = substr(strip_tags($row->description), 0, 140);
		$this->shortLabel = "<strong>{$this->title}</strong>" . " by " . $this->name . "(" . $this->postedAt . ")";
	}
}

class DevlogEntryVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{

	}
}

/*
abstract class Types
{
	const Int = 0x01;
	const String = 0x02;
	const IntArray = 0x04;
	const Required = 0x10;
}

class FormData
{
	function __construct(Context $ctx) {

	}
}

class DevlogCommit
{
	var $appId = Types::Int;
	var $csrf = Types::String;
	var $relatedIdeaIds = Types::IntArray;
	var $title = Types::String;
	var $details = Types::String;
}
*/

class DevlogController extends BaseController
{
	function show(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "devlog");

		$vm->urlViewDevlog = $vm->urlTo('App\Devlog#show');
		$vm->urlNewPost = $vm->urlTo('App\Devlog#create');

		$entriesDMs = $ctx->dal->fetchMultipleObjs('
			select *
			from devlogs
			', ['projectId' => $vm->appId]
		);

		$vm->entries = DevlogEntryVM::createCollection($ctx, $entriesDMs);

		$this->renderTemplate($vm, 'Site', 'App/Devlog/Index');
	}

	function create(Context $ctx)
	{
		$vm = new AppRootVM($ctx, "devlog");

		$vm->urlViewDevlog      = $vm->urlTo('App\Devlog#show');
		$vm->urlNewPost         = $vm->urlTo('App\Devlog#create');
		$vm->urlAjaxBugList     = $vm->urlViewDevlog . "/buglist.json";

		$this->renderTemplate($vm, 'Site', 'App/Devlog/NewPost');
	}

	function commit(Context $ctx)
	{
		$form = new FormVariables();

		$appId          = $form->appId->asInt();
		$bodyMarkup     = $form->details;
		$title          = $form->title;
		$relatedIdeaIds = $form->relatedIdeaIds;

		if(strlen($bodyMarkup) < 2) {
			$ctx->session->setFlash('error', 'Devlog entry details must be at least 2 characters long');
			return $ctx->redirect($ctx->router->generate('App\Devlog#create', $ctx->parameters));
		}

		$bodyHTML = MarkdownExtra::defaultTransform($bodyMarkup);

		$devlogId = $ctx->dal->insert('devlogs')->set([
			'projectId' => $appId,
			'title'     => $title,
			'userId'    => $ctx->user->getUserId(),
			'logHTML'   => $bodyHTML,
			'logMarkup' => $bodyMarkup
		]);

		foreach($relatedIdeaIds as $suggestionId)
		{
			$ctx->dal->insert('suggestionDevlog')->set([
				'devlogId'      => $devlogId,
				'suggestionId'  => $suggestionId,
			]);
		}
	}

	function ajaxBuglistJson(Context $ctx)
	{
		$q = $ctx->parameters->q;
		$appId = $ctx->parameters->appId->asInt();

		$buglistDMs = $ctx->dal->fetchMultipleObjs('
			select
				s.suggestionId as id, title, formattedDescription as description, u.fullName as name, postedAt,
				coalesce(v.votes,0) AS votes
			from suggestions s
				LEFT JOIN (SELECT sum(vote) AS votes, suggestionId FROM suggestionVotes GROUP BY suggestionId) AS v ON (v.suggestionId = s.suggestionId)
				left join users u using (userId)
			where appId = :appId
			order by postedAt desc
			limit 50
			', ['appId' => $appId]
		);

		$buglistVMs = AjaxBuglistItem::createCollection($ctx, $buglistDMs);

		$this->renderJson(
			$buglistVMs->getArrayCopy()
		);
	}

}