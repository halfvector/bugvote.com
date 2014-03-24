<?php

namespace Bugvote\Controllers\Admin;

use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;

class RoadmapEntries extends Scaffolding
{
	protected function getDefinition(Context $context)
	{
		return new DataModelDefinition("roadmapEntries", "todoId", "/admin/roadmap/[:todoId]/entries");
	}
}
