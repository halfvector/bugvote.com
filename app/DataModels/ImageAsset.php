<?php namespace Bugvote\DataModels;

class ImageAsset
{
	public $assetId;
	public $originalFilename;

	function __construct($row)
	{
		$this->assetId = $row->assetId;
		$this->originalFilename = $row->originalFilename;
	}

	static function create($assetId, $filename)
	{
		return new ImageAsset((object)['assetId' => $assetId, 'originalFilename' => $filename]);
	}
}