<?php
namespace Bugvote\Commons;

interface IPageModule
{
	public function configurePageModel(IPageModel $model);
}