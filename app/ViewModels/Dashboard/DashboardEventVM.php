<?php namespace ViewModels\Dashboard;

use Bugvote\Commons\TimeHelper;
use Bugvote\Commons\UrlHelper;
use Bugvote\Services\Context;
use ViewModels\SuggestionEvents;
use ViewModels\UserTypes;

class DashboardEventVM extends ActivityVM
{
	public $creatorImg, $createdAge, $suggestionUrl, $eventType;
	public $eventString;
	public $eventTimeIso8601;
	public $urlViewProfile;
	public $userType;

	function extend(Context $ctx, $row)
	{
		$this->creatorImg = $ctx->images->getResizeCacheUrl($row->assetPath, 50, 50);
		//$this->createdAge = TimeHelper::SmartShortAge($dataModel->createdAgeSec);
		$this->eventTimeIso8601 = TimeHelper::MySQLTimestampToISO8601($row->eventTime);
		$this->suggestionUrl = UrlHelper::createIdeaUrl($row->seoUrlId, $row->seoUrlTitle);
		$this->userType = UserTypes::REGULAR_USER;
		$this->urlViewProfile = UrlHelper::createUserUrl($row->userId);

		$this->timestamp = $row->eventTime;

		switch($row->eventType)
		{
			case SuggestionEvents::CREATED:
				// suggestion created (eg: bug reported)
				$this->eventString = "created";
				break;

			case SuggestionEvents::FIXED:
				// suggestion implemented (eg: bug fixed)
				$this->eventString = "fixed";
				$this->userType = UserTypes::APP_DEVELOPER;
				break;
			case SuggestionEvents::UPDATED:
				// suggestion implemented (eg: bug fixed)
				$this->eventString = "updated";
				break;
		}
	}

	function isRegularUser() {
		return $this->userType == UserTypes::REGULAR_USER;
	}

	function isDeveloper() {
		return $this->userType == UserTypes::APP_DEVELOPER;
	}

	function isUserActivity() { return true; }

	function isBugReport() { return $this->eventType == SuggestionEvents::CREATED; }
	function isBugFix() { return $this->eventType == SuggestionEvents::FIXED; }
	function isUpdate() { return $this->eventType == SuggestionEvents::UPDATED; }

	// defunc -- just an idea
	/*
	function getTagTemplate() {
		switch($this->eventType)
		{
			case "created":
				return "{{> /Components/Dashboard/TagBugReport}}";
		}
	}

	function getTemplateName()
	{
		return function() {
			return "{{> /Components/Dashboard/UserActivityItem}}";
		};
	}
	*/
}