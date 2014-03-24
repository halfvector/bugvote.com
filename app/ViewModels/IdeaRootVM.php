<?php namespace Bugvote\ViewModels;

use Bugvote\Services\Context;

class IdeaRootVM extends BasePageVM
{
	public $appId;
	public $appUrlTitle;
	public $userId;
	public $urlViewApp;
	public $ideaId;
	public $ideaUrlId;
	public $urlViewIdea;
	public $ideaUrlTitle;

	function __construct(Context $ctx)
	{
		parent::__construct($ctx);

		$this->ideaUrlId = $ctx->parameters->ideaId;
		$this->ideaId = $ctx->url->decompressId($this->ideaUrlId);

		$this->userId = $ctx->user->getUserId();

		// figure out appid from suggestionid
		list($this->appId, $this->appUrlTitle, $this->ideaUrlTitle) = $ctx->dal->fetchSingleRow("
			select s.appId, p.seoUrlTitle, s.seoUrlTitle as 'suggestionTitle'
				from suggestions s
				left join projects p on (appId = projectId)
			where suggestionId = :id",
			["id" => $this->ideaId],
			"appId from suggestionId"
		);

		$ctx->log->write("appId: $this->appId");
		$ctx->log->write("ideaId: $this->ideaId");

        // if we failed to grab suggestion details, suggestion does not exist.
        // this should have been handled already
        assert($this->appId, "ideaId ($this->ideaId) has associated appId");

		$this->urlViewIdea = $ctx->url->viewBugManual($this->ideaUrlId, $this->ideaUrlTitle);
		$this->urlViewApp = $ctx->url->viewApp($this->appUrlTitle);

		$this->primaryMenu = new IdeaPrimaryNavVM($this->urlViewApp);
		$this->appHeader = new AppHeaderViewModel($ctx, $this->appId, $this->userId);
	}

	function setPrimaryMenuItem($name)
	{
		$this->primaryMenu->setActiveItem($name);
	}
}