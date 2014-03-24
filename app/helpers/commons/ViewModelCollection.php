<?php namespace Bugvote\Commons;

use Bugvote\Services\Context;
use ArrayObject;

abstract class ViewModelCollection extends ArrayObject
{
	function __construct(Context $ctx, Array $rows)
	{
		$vms = [];
		foreach($rows as $dm)
			$vms []= $this->createVM($ctx, $dm);

		parent::__construct($vms);
	}

	abstract function createVM(Context $ctx, $dataModel);
}