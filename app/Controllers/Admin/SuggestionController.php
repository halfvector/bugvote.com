<?php

namespace Controllers\Admin;

use Bugvote\Commons\DataModelDefinition;
use Bugvote\Commons\Scaffolding;
use Bugvote\Services\Context;

class SuggestionController extends Scaffolding
{
	protected function getDefinition(Context $context)
	{
		return new DataModelDefinition("suggestions", "suggestionId", "/admin/bugs");
	}
}
