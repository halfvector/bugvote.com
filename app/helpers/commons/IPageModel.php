<?php

namespace Bugvote\Commons;

interface IPageModel
{
	public function addMenuSection(MenuSection $menu);
	public function setSecondaryNav($menu);

	/** @return \Bugvote\Context */
	public function getContext();
}