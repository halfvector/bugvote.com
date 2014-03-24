<?php

namespace Bugvote\Controllers\Admin;

use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;

class Roadmap extends Scaffolding
{
	protected function getDefinition(Context $context)
	{
		return new DataModelDefinition("roadmap", "todoId", "/admin/roadmap");
	}
}
