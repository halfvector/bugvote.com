<?php namespace Bugvote\Commons;

use Bugvote\Services\Context;

/**
 * Class ViewModelBase
 * @package AppTogether\Commons
 * derive for magic happy time
 * loads all properties of a row/datamodel into the VM and then calls extend()
 * to format the data as necessary for the view
 *
 * Sample usage:
 *
	class BugCommentVM extends ViewModelBase
	{
		function extend(Context $ctx, $comment)
		{
			$this->authorImg = $ctx->assetManager->GetWebPathForAsset($comment->profileMediumAssetId);
			$this->postAge = $ctx->api->SmartShortAge($comment->postAgeSec);
			$this->comment = nl2br($comment->comment);
		}
	}
 *
 */

abstract class ViewModelBase
{
	function __construct(Context $ctx, $dataModel)
	{
		if(!$dataModel)
		{
			$ctx->log->write("Warning: could not create VM \"".get_class($this)."\" from empty DataModel");
			return;
		}

		foreach (get_object_vars($dataModel) as $key => $value) {
			$this->$key = $value;
		}

		$this->extend($ctx, $dataModel);
	}

	abstract function extend(Context $ctx, $row);

	/**
	 * creates a collection of Derived class objects
	 * based on array of data in $rows
	 * so each row becomes one Derived class
	 * therefore whatever class extends this one must implement extend() to convert ONE row into ONE VM
	 * hint: if you have a forloop and expect an array IN extend(), you are doing it wrong
	 * @param $ctx
	 * @param array $rows
	 * @return AutoVMCollection
	 */
	static function createCollection($ctx, Array $rows)
	{
		return new AutoVMCollection($ctx, $rows, get_called_class());
	}
}

class AutoVMCollection extends ViewModelCollection
{
	protected $vmType;

	function __construct(Context $ctx, Array $rows, $type)
	{
		$ctx->log->write("spawning auto collection for: $type");
		$this->vmType = $type;
		parent::__construct($ctx, $rows);
	}

	function createVM(Context $ctx, $dataModel)
	{
		return new $this->vmType($ctx, $dataModel);
	}
}