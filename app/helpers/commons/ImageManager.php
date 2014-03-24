<?php namespace Bugvote\Commons;

class ImageManager
{
	const FIT_COVER = 2; // may result in cropping. fits exactly requested width/height.
	const FIT_STRETCH = 3; // not pretty. don't use.
	const FIT_ASPECT = 1; // no cropping, preserves aspect ratio, may not be exactly the requested width/height.

	protected $root = "//static.dev.bugvote.com/cache";

	function getResizeCacheUrl($assetPath, $width, $height, $fitMode = self::FIT_COVER)
	{
		$formatter = "/{$width}x{$height}-{$fitMode}/";
		return $this->root . $formatter . $assetPath;
	}
}
