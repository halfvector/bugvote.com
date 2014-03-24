<?php

namespace Bugvote\Controllers\Admin;

use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;
use Symfony\Component\Yaml\Yaml;

class Apps extends Scaffolding
{
	protected function getDefinition(Context $context)
	{
		return new DataModelDefinition("apps", "appId", "/admin/apps");
	}
}
