<?php namespace Bugvote\Commons;

class PrimaryMenuItem
{
	public $name, $url, $title, $icon, $notifications, $active;

	function __construct($name, $url, $title, $icon, $notifications, $active = false)
	{
		$this->name = $name;
		$this->url = $url;
		$this->title = $title;
		$this->icon = $icon;
		$this->notifications = $notifications;
		$this->active = $active;
		$this->hasNotifications = $notifications > 0;
		//$this->numOfItems = rand(0,9);
	}
}