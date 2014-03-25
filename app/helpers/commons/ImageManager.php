<?php namespace Bugvote\Commons;

class ImageManager
{
	const FIT_COVER = 2; // may result in cropping. fits exactly requested width/height.
	const FIT_STRETCH = 3; // not pretty. don't use.
	const FIT_ASPECT = 1; // no cropping, preserves aspect ratio, may not be exactly the requested width/height.

	function getResizeCacheUrl($assetPath, $width, $height, $fitMode = self::FIT_COVER)
	{
		$formatter = "/{$width}x{$height}-{$fitMode}/";
		return APP_STATIC_IMG_CACHE . $formatter . $assetPath;
	}
}
