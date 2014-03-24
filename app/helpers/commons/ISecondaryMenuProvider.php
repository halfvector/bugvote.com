<?php
namespace Bugvote\Commons;

use Bugvote\Services\Context;

interface ISecondaryMenuProvider
{
	function getSecondaryMenu(Context $context);
}