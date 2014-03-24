<?php namespace Bugvote\Commons;

class SimplePrimaryMenuVM
{
	public $menuItems = [];

	function __construct(Array $items)
	{
		$this->menuItems = $items;
	}
}