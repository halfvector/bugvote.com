<?php namespace Bugvote\Controllers;

use Bugvote\Services\Context;
use Bugvote\Commons\BaseController;
use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\SimplePrimaryMenuVM;
use Bugvote\Commons\ViewModelBase;
use Bugvote\ViewModels\BasePageVM;
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