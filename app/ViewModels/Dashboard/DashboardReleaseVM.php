<?php
namespace ViewModels\Dashboard;

use Bugvote\Commons\TimeHelper;
use Bugvote\Commons\UrlHelper;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Services\Context;

class DashboardReleaseVM extends ActivityVM
{
	function extend(Context $ctx, $row)
	{
	}

	function isReleaseNews() { return true; }
}

