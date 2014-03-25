<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Services\Context;
use Bugvote\ViewModels\BasePageVM;

class RegisterController extends BaseController
{
	/** @route GET /register */
	function show(Context $ctx)
	{
		$vm = new BasePageVM($ctx);
		$vm->primaryMenu = new SimplePrimaryMenuVM(
			[new PrimaryMenuItem("home", "/", "home", "icon-home", 0, true)]
		);

		$this->renderTemplate($vm, 'Site', 'Auth/Register');
	}
}