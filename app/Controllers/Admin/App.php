<?php

namespace Bugvote\Controllers\Admin;

use Bugvote\Commons\PageModel;
use Bugvote\Services\Context;
use Bugvote\Models\Developer\AppDataModel;

class App
{
	public function show(Context $context)
	{
		// setup data models
		$main = new PageModel($context);
		$main->app = new AppDataModel($main);

		$appId = $context->request->app_id;

		// configure view
		$main->activateSections("Admin/App", "News");
		$main->setButtons("/admin/app/$appId", "News");

		// render
		$context->render("App", $main);
	}

	public function index(Context $context)
	{

	}

	// GET /bugs[:bug_id]/comments/new
	public function design(Context $context)
	{

	}

	// POST /bugs[:bugs_id]/comments
	public function create(Context $context)
	{
		$bugId = $context->request->bugs_id;
		$message = $context->request->message;
		$userId = 15;

		// create comment
		$context->api->AddSuggestionComment($bugId, $userId, $message);
		$context->response->flash("Comment Created!", 'info');

		$context->redirect("/bugs/$bugId");
	}

	public function edit(Context $context)
	{
	}

	public function editPosted(Context $context)
	{
	}

	public function destroy(Context $context)
	{
	}
}