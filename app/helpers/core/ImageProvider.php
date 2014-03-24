<?php

# ========================================================================#
#
#  Author:    Jarrod Oberto
#  Version:	 1.0
#  Date:      17-Jan-10
#  Purpose:   Resizes and saves image
#  Requires : Requires PHP5, GD library.
#  Usage Example:
#                     include("classes/resize_class.php");
#                     $resizeObj = new resize('images/cars/large/input.jpg');
#                     $resizeObj -> resizeImage(150, 100, 0);
#                     $resizeObj -> saveImage('images/cars/large/output.jpg', 100);
#
#
# ========================================================================#

namespace Bugvote\Core;

class ImageProvider
{
	// quite handy
	public static function GetResizedAssetWebPath($assetId, $maxWidth, $maxHeight, $cropStyle = "auto")
	{
		$appRelativePath = AssetManager::GetAppRelativeFilePathForAsset($assetId);
        if(!$appRelativePath)
            return false;

		$resizedFilename = $appRelativePath . ".cache.{$maxWidth}x{$maxHeight}.$cropStyle";

		if( ! file_exists($resizedFilename) )
		{
			$timer = \Timer::start();

			$resizeObj = ImageResizer::Open($appRelativePath);
			if( ! $resizeObj ) {
				\Log::Write("Error: failed to open image for resizing: [$appRelativePath]");
				return false;
			}

			// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
			$resizeObj->resizeImage($maxWidth, $maxHeight, $cropStyle);
			$resizeObj->saveImage($resizedFilename, 90);

			\Log::Write("Perf: Resizing took {$timer->getNiceSpan()} for image=[$resizedFilename]");
		}

		return AssetManager::FixupWebAssetPath($resizedFilename);
	}

	// converts an app-relative or absolute-path to a (possibly new) asset
	// this does NOT append width/height parameters.
	// use this to handle new uploads (imgSourcePath will be the file tmpname)
	public static function SaveAs($imgSourcePath, $assetId, $maxWidth, $maxHeight, $cropStyle = "auto", $overwrite = false)
	{
		$resizedFilename = AssetManager::GetAppRelativeFilePathForAsset($assetId);

		if( ! file_exists($resizedFilename) || $overwrite )
		{
			$resizeObj = new ImageResizer($imgSourcePath);

			// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
			$resizeObj->resizeImage($maxWidth, $maxHeight, $cropStyle);
			$resizeObj->saveImage($resizedFilename, 90);
		}

		return true;
	}
}


Class ImageResizer
{
	// *** Class variables
	private $image;
	private $width;
	private $height;
	private $imageResized;
	private $type;

	function __construct($img)
	{
		$this->image = $img;
	}

	public static function Open($filepath)
	{
		if( ! file_exists($filepath) )
			return false;

		$img = @imagecreatefromstring(file_get_contents($filepath));
		if( ! $img )
			return false;

		$resizer = new ImageResizer($img);

		list($resizer->width, $resizer->height, $resizer->type, $attr) = getimagesize($filepath);

		return $resizer;
	}


	## --------------------------------------------------------

	public function resizeImage($newWidth, $newHeight, $option="auto")
	{
		// *** Get optimal width and height - based on $option
		$optionArray = $this->getDimensions($newWidth, $newHeight, $option);

		$optimalWidth  = $optionArray['optimalWidth'];
		$optimalHeight = $optionArray['optimalHeight'];


		// *** Resample - create image canvas of x, y size
		$this->imageResized = imagecreatetruecolor($optimalWidth, $optimalHeight);
		imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);


		// *** if option is 'crop', then crop too
		if ($option == 'crop') {
			$this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight);
		}
	}

	## --------------------------------------------------------

	private function getDimensions($newWidth, $newHeight, $option)
	{

		switch ($option)
		{
			case 'exact':
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
				break;
			case 'portrait':
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
				break;
			case 'landscape':
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
				break;
			case 'auto':
				$optionArray = $this->getSizeByAuto($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			case 'crop':
				$optionArray = $this->getOptimalCrop($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
		}
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function getSizeByFixedHeight($newHeight)
	{
		$ratio = $this->width / $this->height;
		$newWidth = $newHeight * $ratio;
		return $newWidth;
	}

	private function getSizeByFixedWidth($newWidth)
	{
		$ratio = $this->height / $this->width;
		$newHeight = $newWidth * $ratio;
		return $newHeight;
	}

	private function getSizeByAuto($newWidth, $newHeight)
	{
		if ($this->height < $this->width)
			// *** Image to be resized is wider (landscape)
		{
			$optimalWidth = $newWidth;
			$optimalHeight= $this->getSizeByFixedWidth($newWidth);
		}
		elseif ($this->height > $this->width)
			// *** Image to be resized is taller (portrait)
		{
			$optimalWidth = $this->getSizeByFixedHeight($newHeight);
			$optimalHeight= $newHeight;
		}
		else
			// *** Image to be resizerd is a square
		{
			if ($newHeight < $newWidth) {
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
			} else if ($newHeight > $newWidth) {
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
			} else {
				// *** Sqaure being resized to a square
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
			}
		}

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function getOptimalCrop($newWidth, $newHeight)
	{

		$heightRatio = $this->height / $newHeight;
		$widthRatio  = $this->width /  $newWidth;

		if ($heightRatio < $widthRatio) {
			$optimalRatio = $heightRatio;
		} else {
			$optimalRatio = $widthRatio;
		}

		$optimalHeight = $this->height / $optimalRatio;
		$optimalWidth  = $this->width  / $optimalRatio;

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight)
	{
		// *** Find center - this will be used for the crop
		$cropStartX = ( $optimalWidth / 2) - ( $newWidth /2 );
		$cropStartY = ( $optimalHeight/ 2) - ( $newHeight/2 );

		$crop = $this->imageResized;
		//imagedestroy($this->imageResized);

		// *** Now crop from center to exact requested size
		$this->imageResized = imagecreatetruecolor($newWidth , $newHeight);
		imagecopyresampled($this->imageResized, $crop , 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight , $newWidth, $newHeight);
	}

	## --------------------------------------------------------

	public function saveImage($savePath, $imageQuality="100")
	{
		$extension = image_type_to_extension($this->type);

		switch($extension)
		{
			case '.jpg':
			case '.jpeg':
				if (imagetypes() & IMG_JPG) {
					imagejpeg($this->imageResized, $savePath, $imageQuality);
				}
				break;

			case '.gif':
				if (imagetypes() & IMG_GIF) {
					imagegif($this->imageResized, $savePath);
				}
				break;

			case '.png':
				// *** Scale quality from 0-100 to 0-9
				$scaleQuality = round(($imageQuality/100) * 9);

				// *** Invert quality setting as 0 is best, not 9
				$invertScaleQuality = 9 - $scaleQuality;

				if (imagetypes() & IMG_PNG) {
					imagepng($this->imageResized, $savePath, $invertScaleQuality);
				}
				break;

			// ... etc

			default:
				// *** No extension - No save.
				Log::Write("Could not figure out image type; Not saving: $savePath");
				break;
		}

		imagedestroy($this->imageResized);
	}


	## --------------------------------------------------------

}
?>
