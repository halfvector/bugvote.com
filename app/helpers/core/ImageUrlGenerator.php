<?php namespace Bugvote\Core;

use Bugvote\DataModels\ImageAsset;
use Bugvote\Services\Context;
use Bugvote\Commons\ImageManager;
use Exception;

class ImageUrlGeneratorException extends Exception
{
}

class ImageUrlGenerator
{
	protected $ctx;
	protected $assetData;

	function __construct(Context $ctx, ImageAsset $assetData)
	{
		$this->ctx = $ctx;
		$this->assetData = $assetData;
	}

	function __invoke($params = null)
	{
		if($params == null)
		{   // throw a nice error
			var_dump("Hey you, go specify an image resolution in the template!");
			throw new ImageUrlGeneratorException("ImageUrlGenerator not configured in template");
		}

		if(strstr($params, ':'))
			list($size, $aspect) = explode(':', $params);
		else
		{
			$size = $params;
			$aspect = "";
		}

		// parse size
		$array = explode('x', $size);
		list($width, $height) = array_map( function($int) { return intval($int); }, $array);
		if($width <= 0 && $height <= 0)
		{
			var_dump("failed to parse {width}x{height} string: $size");
			return false;
		}

		switch($aspect)
		{
			// beautiful but crops
			case 'cover':
				$resizeFormat = ImageManager::FIT_COVER;
				break;

			// no cropping, preserves aspect ratio
			case 'preserve':
			case 'aspect':
				$resizeFormat = ImageManager::FIT_ASPECT;
				break;

			// ugly, doesn't preserve aspect ratio, doesn't crop
			case 'stretch':
				$resizeFormat = ImageManager::FIT_STRETCH;
				break;

			default:
				$resizeFormat = ImageManager::FIT_COVER;
		}

		$url = $this->ctx->assetManager->getResizeUrl($this->assetData->assetId, $this->assetData->originalFilename, $width, $height, $resizeFormat);

		$this->ctx->log->write("Built '$url' from '$params'");

		return $url;
	}
}