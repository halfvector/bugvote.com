<?php

namespace Bugvote\Commons;

use Bugvote\Services\Context;

interface IDataModel
{
	function setup(IPageModel $context);
}