<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Services\Context;
use Bugvote\ViewModels\BasePageVM;

class LoginController extends BaseController
{
	/** @route GET /login */
	function showLogin(Context $ctx)
	{
		// if already logged in, show user's profile
		if ($ctx->user->getUserId())
			return $ctx->redirect("/profile");

		$vm = new BasePageVM($ctx);
		$vm->primaryMenu = new SimplePrimaryMenuVM(
			[new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true)]
		);

		$this->renderTemplate($vm, 'Site', 'Auth/Login');
	}

	function commitLogout(Context $ctx)
	{
		$ctx->user->logout();
		$ctx->redirect("/"); // go home
	}
}