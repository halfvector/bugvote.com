<?php namespace Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\UrlHelper;
use Bugvote\Services\Context;

class DevLogController extends BaseController
{
	function commit(Context $ctx)
	{
		$data = $this->getRequirements([
			'id' => ['type' => 'string', 'minLength' => 3],
			'message' => ['type' => 'markdown', 'minLength' => 3, 'persistent' => true],
			'csrf' => ['type' => 'string', 'minLength' => 3, 'optional' => true],
			//'action' => ['type' => 'string', 'minLength' => 3],
			//'reason' => ['type' => 'string', 'minLength' => 3, 'optional' => true, 'default' => ""],
			//'tags' => ['type' => 'array', 'optional' => true],
			//'attachments' => ['type' => 'file', 'optional' => true]
		]);

		$suggestionId = $ctx->dal->fetchSingleValue("select suggestionId from suggestions where seoUrlId = :id", ["id" => $data->id]);
		$userId = $ctx->user->getUserId();

		$devLogId = $ctx->dal->insertSingleObj("
			insert into devLogs
			set suggestionId = :id, body = :message, postedAt = utc_timestamp(), userId = :userId",
			["id" => $suggestionId, "message" => $data->message, "userId" => $userId],
			"created new devlog"
		);

		$url = UrlHelper::buildSuggestionUrlFromTinyId($ctx, $data->id);

		$ctx->redirect($url);
	}
}