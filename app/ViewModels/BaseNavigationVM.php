<?php namespace Bugvote\ViewModels;

class BaseNavigationVM
{
	/** @var PrimaryMenuItem[] */
	public $menuItems = [];

	function __construct($currentItemName = false)
	{
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