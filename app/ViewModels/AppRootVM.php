<?php namespace Bugvote\ViewModels;

use Bugvote\Commons\UserRoles;
use Bugvote\DataModels\Privileges;
use Bugvote\Services\Context;

// deals with App specific URLs
trait AppUrlHelper
{
	function getAppUrlTitle(Context $ctx) {
		return $ctx->parameters->appUrl;
	}

	function getAppIdFromUrl(Context $ctx) {
		return $ctx->resolver->getAppIdFromAppUrlTitle($ctx, $ctx->parameters->appUrl);
	}

	function urlTo($ctx, $path, $variables = [])
	{
		$defaults = [
			'appUrl' => $this->getAppUrlTitle($this->ctx)
		];
		$variables = array_merge($defaults, $variables);

		return $ctx->router->generate($path, $variables);
	}
}

class AppRootVM extends BasePageVM
{
	public $appId;
	public $appUrlTitle;
	public $userId;
	public $urlViewApp;
	public $query;

	/**
	 * @var UserRoles users's role in the app (admin, developer, etc)
	 */
	public $userAppRole;

	protected $ctx;

	function __construct(Context $ctx, $menuItem = false)
	{
		$this->ctx = $ctx;
		parent::__construct($ctx);

		$p = $ctx->perf->start("AppRootVM");

		$this->appUrlTitle = $ctx->parameters->appUrl;
		$this->appId = $ctx->resolver->getAppIdFromAppUrlTitle($ctx, $this->appUrlTitle);
		$this->userId = $ctx->user->getUserId();
        $this->appUrlId = $ctx->url->compressId($this->appId);
		$this->urlViewApp = $ctx->url->viewApp($this->appUrlTitle);

		// TODO: permission check: get user's relationship to project (admin, developer, follower, lurker, etc)
		//$this->userAppRole = $ctx->permissions->GetUserProjectPermissions($this->userId, $this->appId);

		$this->privileges = new Privileges($ctx, $this->userId, $this->appId);

		// TODO: permission check: is user allowed to view this app?
		// TODO: sanity check: if appUrl is invalid show a not-found page (or attempt to find a similarly named project)
		// TODO: vanity check: clean up projectTitle (proper case, no funny stuff)

		$this->primaryMenu = new IdeaPrimaryNavVM($this->urlViewApp, $menuItem);
		$this->appHeader = new AppHeaderViewModel($ctx, $this->appId, $this->userId);

		$p->stop();
	}

	function urlTo($path, $variables = [])
	{
		$defaults = [
			'appUrl' => $this->appUrlTitle,
		];
		$variables = array_merge($defaults, $variables);

		return $this->ctx->router->generate($path, $variables);
	}

	function setPrimaryMenuItem($name)
	{
		$this->primaryMenu->setActiveItem($name);
	}
}