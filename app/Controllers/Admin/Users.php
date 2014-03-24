<?php

namespace Bugvote\Controllers\Admin;

use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;

class Users extends Scaffolding
{
	protected function getDefinition(Context $context)
	{
		return new DataModelDefinition("users", "userId", "/admin/users");
	}
}
