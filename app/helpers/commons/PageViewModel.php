<?php namespace Bugvote\Commons;

/**
 * Class PageViewModel
 * @package Bugvote\Commons
 * all pages are derived from this core ViewModel
 */
class PageViewModel
{
	public $author = "Alex";
	public $description = "social bug-tracking";
	public $title = "Bugvote";

	public $layout;
	public $template;
	public $primaryMenu;

	// used to inject styles
	public $headerTemplate;

	// used to inject scripts
	public $footerTemplate = "CommonFooter.mustache";

	public $secondaryNavTemplate;


	// mustache template bindings
	function ContentTemplate() {
		return function() {
			return "{{>$this->template}}";
		};
	}

	function SecondaryNavigationTemplate() {
		return function() {
			return "{{>$this->secondaryNavTemplate}}";
		};
	}

	function HeaderTemplate() {
		if(!$this->headerTemplate)
			return "";

		return function() {
			return "{{>$this->headerTemplate}}";
		};
	}

	function FooterTemplate() {
		if(!$this->footerTemplate)
			return "";

		return function() {
			return "{{>$this->footerTemplate}}";
		};
	}
}