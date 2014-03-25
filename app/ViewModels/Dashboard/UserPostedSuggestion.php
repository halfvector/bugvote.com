<?php namespace Bugvote\ViewModels\Dashboard;

use Bugvote\Commons\TimeHelper;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\ViewModels\SuggestionEvents;
use Bugvote\ViewModels\UserTypes;

class UserPostedSuggestion extends ActivityVM
{
	function extend(Context $ctx, $row)
	{
		$this->urlAvatarImg = new ImageUrlGenerator($ctx, new ImageAsset($row));

		$this->eventTimeIso8601 = TimeHelper::MySQLTimestampToISO8601($row->happenedAt);
		$this->suggestionUrl = $ctx->url->createIdeaUrl($row->seoUrlId, $row->seoUrlTitle);
		$this->userType = UserTypes::REGULAR_USER;
		$this->urlViewProfile = $ctx->url->createUserUrl($row->userId);

		$this->timestamp = $row->happenedAt;

		if ($this->projectRole == 1001)
			$this->userType = UserTypes::APP_DEVELOPER;

		switch ($row->type) {
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

	function isUserActivity()
	{
		return true;
	}

	function isRegularUser()
	{
		return $this->userType == UserTypes::REGULAR_USER;
	}

	function isDeveloper()
	{
		return $this->userType == UserTypes::APP_DEVELOPER;
	}

	function isBugReport()
	{
		return $this->type == SuggestionEvents::CREATED;
	}

	function isBugFix()
	{
		return $this->type == SuggestionEvents::FIXED;
	}

	function isUpdate()
	{
		return $this->type == SuggestionEvents::UPDATED;
	}
}

