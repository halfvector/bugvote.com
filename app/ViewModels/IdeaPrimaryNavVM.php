<?php namespace Bugvote\ViewModels;

use Bugvote\Commons\PrimaryMenuItem;
use Bugvote\Commons\UrlHelper;

class IdeaPrimaryNavVM
{
	public $menuItems = [];

	function __construct($appUrl, $currentItemName = false)
	{
		$this->menuItems []= new PrimaryMenuItem("home", 		$appUrl, 				"Home", 		"fa fa-bullhorn", 0, false);
		$this->menuItems []= new PrimaryMenuItem("ideas", 		$appUrl . "/ideas", 	"Bugs and Ideas", 	        "fa fa-flask", 0, false);
		$this->menuItems []= new PrimaryMenuItem("roadmap", 	$appUrl . "/roadmap", 	"Roadmap", 	    "fa fa-calendar", 0, false);
		$this->menuItems []= new PrimaryMenuItem("devlog", 		$appUrl . "/devlog", 	"Dev Log", 		"fa fa-wrench", 0, false);
		//$this->menuItems []= new PrimaryMenuItem("search", 		$appUrl . "/search", 	"Search", 		"fa fa-search", 0, false);

		//$this->menuItems []= new PrimaryMenuItem("learned",		$appUrl . "/ideas", 	"", 	"icon-question-sign", 0, false);
		//$this->menuItems []= new PrimaryMenuItem("features",	$appUrl . "/features", 	"Done", 	"icon-star", 0, false);
		//$this->menuItems []= new PrimaryMenuItem("learned",		$appUrl . "/kb", 	"Q/A", 	"icon-book", 0, false);

		/*
		$this->menuItems[0]->numOfItems = 0;
		$this->menuItems[0]->hasCount = false;
		$this->menuItems[1]->numOfItems = "1.4k";
		$this->menuItems[1]->hasCount = true;
		$this->menuItems[2]->numOfItems = 145;
		$this->menuItems[2]->hasCount = true;
		$this->menuItems[3]->numOfItems = 25;
		$this->menuItems[3]->hasCount = true;
		$this->menuItems[4]->numOfItems = 3;
		$this->menuItems[4]->hasCount = true;
		$this->menuItems[5]->numOfItems = 57;
		$this->menuItems[5]->hasCount = true;
		*/

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