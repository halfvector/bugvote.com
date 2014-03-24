<?php namespace Bugvote\ViewModels;

use Bugvote\Services\Context;

/**
 * Class AppHeaderViewModel
 * ViewModel to power the AppHeader View
 */
class AppHeaderViewModel
{
	public $urlViewApp;
	public $imgUrl;
	public $title;

	function __construct(Context $ctx, $appId, $userId)
	{
		$p = $ctx->perf->start("AppHeader VM");

		assert(intval($appId), "valid appId integer");

		// app data for header
		$appDM = $ctx->dal->fetchSingleObj("
			select *
				from projects
				left join assets on (assetId = thumbnailAssetId)
			where projectId = :id",
			[':id' => $appId],
			"project from projectId"
		);

		$this->title = $appDM->projectTitle;

		$this->urlViewApp = $ctx->url->viewApp($appDM->seoUrlTitle);
		$this->urlUniversalSearch = $this->urlViewApp . "/search";

		$p->stop();
	}
}
