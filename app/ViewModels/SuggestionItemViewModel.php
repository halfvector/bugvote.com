<?php namespace Bugvote\ViewModels;

use Bugvote\Commons\TimeHelper;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Controllers\Idea\SuggestionData;

class SuggestionItemViewModel extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
		$this->urlPosterProfileImg = new ImageUrlGenerator($ctx, ImageAsset::create($this->posterImgId, $this->posterImgFilename));
		$this->postedTimeIso8601 = TimeHelper::MySQLTimestampToISO8601($row->postedAt);

		$this->progressPercentage = number_format(rand(0, 100), 0) . "%";

		$this->suggestionUrl = $ctx->url->viewBugManual($this->seoUrlId, $this->seoUrlTitle);
		$this->urlVote = $this->suggestionUrl . "/vote";

		$this->isBugFix = $this->suggestionTypeId == 1 ? true : false;
		$this->numOfComments = $row->comments;
		$this->hasComments = $this->numOfComments > 0;

		$this->hasTag = 0;
		$this->tag = "COMMENTARY";

		if ($this->suggestionTypeId == 1) {
			$this->tagType = "bug-report";
			$this->hasTag = 1;
			$this->tag = SuggestionData::$suggestionTypes[$this->suggestionTypeId];
		}

		if ($this->suggestionTypeId == 2) {
			$this->tagType = "new-feature";
			$this->hasTag = 1;
			$this->tag = SuggestionData::$suggestionTypes[$this->suggestionTypeId];
		}

		// TEMP: disabled Q/A tags, just let them be on their own
		if ($this->suggestionTypeId == 4) {
			$this->tagType = "qa";
			$this->hasTag = 0;
			$this->tag = SuggestionData::$suggestionTypes[$this->suggestionTypeId];
		}
	}
}