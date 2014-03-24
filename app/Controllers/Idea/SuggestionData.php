<?php namespace Bugvote\Controllers\Idea;

class SuggestionData
{
	static $suggestionTypes = [
		0 => "Unset Type",
		1 => "Bug",
		2 => "Suggestion",
		3 => "Change",
		4 => "Q&A",
	];

	static $suggestionStates = [
		0 => "Unset State",
		1 => "New",
		3 => "Scheduled",
		4 => "Under Development",
		5 => "Done"
	];
}