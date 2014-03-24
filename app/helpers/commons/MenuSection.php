<?php

namespace Bugvote\Commons;

class MenuSection
{
	public $sectionName = "Section Name";
	public $sectionLinks = [];
	public $priority = 0;

	public function links()
	{
		return new \ArrayIterator($this->sectionLinks);
	}
}