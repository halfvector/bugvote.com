<?php namespace Bugvote\ViewModels;

use Bugvote\Commons\PrimaryMenuItem;

class ProfilePrimaryNavVM
{
	public $menuItems = [];

	function __construct($profileUrl, $currentItemName = false)
	{
		$this->menuItems []= new PrimaryMenuItem("home", 	"/u/", 	"Home", 	"icon-user", 0, false);

		if($currentItemName)
			foreach($this->menuItems as $item)
				if($item->name == $currentItemName)
					$item->active = true;
	}

	function setActiveItem($name)
	{
		foreach($this->menuItems as $menuItem)
		{
			if($menuItem->name == $name)
				$menuItem->active = true;
			else
				$menuItem->active = false;
		}
	}
}