<?php namespace Controllers;

use Bugvote\Services\Context;
use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\UserRoles;
use Exception;
use ViewModels\BasePageVM;

class AppManagerController extends BaseController
{
	function showAppCreationScreen(Context $ctx)
	{
		$vm = new BasePageVM($ctx);
		$vm->primaryMenu = new SimplePrimaryMenuVM(
			[ new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true) ]
		);

		$this->renderTemplate($vm, 'Site', 'App/Create');
	}

	function handleAppCreationScreen(Context $ctx)
	{
		$data = $this->getRequirements([
			'title' => ['type' => 'string', 'minLength' => 3],
			'projectImage' => ['type' => 'file', 'optional' => true],
		]);

		// if form had some errors, redirect back to the form page (alerts will be shown)
		if(!$data)
			return $ctx->redirect("/new");

		// app creator and date
		$userId = $ctx->user->getUserId();
		$date = $ctx->dal->getCurrentDateTimeIso8601();

		// a nice clean url
		$appUrlTitle = UrlHelper::seoUrl($data->title);

		// upload asset
		$thumbnailAssetId = $ctx->api->CreateAsset($data->projectImage);
		if(!$thumbnailAssetId)
		{
			$ctx->flash("Error uploading asset", 'error');
			return $ctx->redirect("/new");
		}

		try
		{
			$ctx->dal->beginTransaction();

			$projectId = $ctx->dal->insert('projects')->set([
				'projectTitle' => $data->title,
				'seoUrlTitle' => $appUrlTitle,
				'createdAt' => $date,
				'thumbnailAssetId' => $thumbnailAssetId,
			]);

			$ctx->dal->update("projects")
					 ->set(["seoUrlId" => UrlHelper::encodeIdeaId($projectId)])
					 ->where(["projectId" => $projectId]);

			$ctx->dal->insert('projectOwners')->set([
				'projectId' => $projectId,
				'userId' => $userId,
				'role' => UserRoles::Admin,
			]);

			$ctx->dal->commitTransaction();
		}
		catch(Exception $err)
		{
			$ctx->dal->rollbackTransaction();
			$ctx->log->write("Failed to create new app: " . $err->getMessage());

			$ctx->flash("Oops, there was an error creating the project!", 'error');
			return $ctx->redirect("/new");
		}

		// everything succeeded, redirect to the new app

		$newAppUrl = UrlHelper::createAppUrl($appUrlTitle);

		$ctx->flash("Yey project!", 'info');
		$ctx->redirect($newAppUrl);
	}
}