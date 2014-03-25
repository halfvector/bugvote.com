<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Core\SignedUrl;
use Bugvote\Services\Context;
use Bugvote\ViewModels\BasePageVM;

class UserSwitchVM extends BasePageVM
{
	function __construct(Context $ctx, $menuItem = false)
	{
		parent::__construct($ctx);
	}
}

class UserSwitchItemVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		$this->profileImg = $ctx->assetManager->getResizeUrl($row->assetId, $row->originalFilename, 50, 50);
	}
}

class UserSwitchController extends BaseController
{
	/** @route GET /switch */
	function show(Context $ctx)
	{
		$vm = new BasePageVM($ctx);
		$vm->primaryMenu = new SimplePrimaryMenuVM(
			[new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true)]
		);

		$users = $ctx->dal->fetchMultipleObjs("
			SELECT * FROM users u
				left join socialAccounts s using (userId)
				LEFT JOIN assets ON (assetId = ifnull(profileMediumAssetId, profilePicAssetId))
				group by u.userId
			"
		);

		$vm->users = UserSwitchItemVM::createCollection($ctx, $users);

		$this->renderTemplate($vm, 'Site', 'UserSwitch');
	}

	/** @route POST /switch */
	function change(Context $ctx)
	{
		$data = $this->getRequirements([
			'userId' => ['type' => 'int'],
			//'redirect' => ['type' => 'string'],
			//'signature' => ['type' => 'string'],
		]);

		if (!$data)
			$ctx->redirect("/switch");

		// verify signature
		$redirect = urldecode($_GET['redirect']);
		$signature_received = $_GET['signature'];

		$signed = new SignedUrl($redirect, $ctx->user->getUserId());

		$ctx->log->write("Testing redirect signature: {$signature_received} vs {$signed->signature}");

		if ($signed->signature != $signature_received) {
			$ctx->log->write("Redirect signature '{$signature_received}' doesn't match expected '{$signed->signature}'");
			$ctx->redirect("/switch");
		}

		$ctx->log->write("Switching to userId={$data->userId}");

		$ctx->user->login($data->userId);
		$ctx->redirect($redirect);
	}
} 