<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Services\Context;
use Clockwork\Storage\FileStorage;


class ClockworkController extends BaseController
{
	function show(Context $ctx)
	{
		$clockworkPath = $ctx->paths->AbsoluteTmpPath . "/clockwork";
		$storage = new FileStorage($clockworkPath);
		$data = $storage->retrieve($ctx->parameters->id);
		if($data)
			echo $data->toJson();
	}
}