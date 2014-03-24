<?php namespace Bugvote\ViewModels;

use Bugvote\Core\SignedUrl;
use Bugvote\Services\Context;
use Bugvote\Commons\PageViewModel;



class BasePageVM extends PageViewModel
{
	public $userStatus;
	public $signedUrl;

	function __construct(Context $ctx)
	{
		$p = $ctx->perf->start("BasePageVM");

		$this->userStatus = new UserStatusVM($ctx);
		$this->signedUrl = new SignedUrl($_SERVER['REQUEST_URI'], $ctx->user->getUserId());

		//$this->errors = $ctx->klein->flashes('error');
		//$this->hasErrors = count($this->errors) > 0;

		$this->alerts = [];

		// FIXME: restore flash support without klein
		/*
		foreach($ctx->klein->flashes() as $type => $flashes)
		{
			foreach($flashes as $flash)
				$this->alerts []= ['type' => $type, 'message' => $flash];
		}
		*/

		$flashes = $ctx->session->getAllFlashes();
		foreach($flashes as $type => $flash) {
			$this->alerts []= ['type' => $type, 'message' => $flash];
		}

		$ctx->session->clearFlash();

		$this->hasAlerts = count($this->alerts) > 0;

		$p->stop();
	}
}
