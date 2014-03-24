<?php

namespace Bugvote\Core;

use Bugvote\Commons\ImageManager;
use Bugvote\Core\AuditFacade;
use Bugvote\Core\Logging\ILogger;
use Exception;

/**
 * Class AssetManager
 * @package AppTogether\Core
 *
 * Various kinds of paths:
 * + Asset Path - minimal path necessary to identify an asset: a multi-level folder + filename
 * + Asset Storage Path - contains Asset Path and the necessary
 */

class AssetManager
{
	/** @var ILogger */
	protected $log;

	/** @var Paths */
	protected $paths;

	/** @var ImageManager */
	protected $image;

	/**
	 * @param $paths Paths
	 * @param $logger ILogger
	 * @param $imageManager ImageManager
	 */
	function __construct($paths, $logger, $imageManager)
	{
		$this->paths = $paths;
		$this->log = $logger;
		$this->image = $imageManager;
	}

	// convert any asset path into an app-relative path
	function FixupRelativeAssetPath($assetPath)
	{
		return $this->paths->RelativeStorageRoot . "/" . $assetPath;
	}

	// convert any asset-path into a web-relative path
	function FixupWebAssetPath($assetPath)
	{
		return "/" . $assetPath;
	}

	function GetWebPathFromRelativeFilePath($relativeFilePath)
	{
		return $this->paths->WebStorageRoot . "/" . $relativeFilePath;
	}

	const SCALE_FIT = 1;
	const SCALE_CROP = 2;

	function GetWebAssetPath($assetId, $width, $height, $scaleMode = self::SCALE_FIT)
	{
		$compressedId = base_convert($assetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $this->paths->AbsoluteWebCacheRoot . "/{$width}x{$height}-$scaleMode/" . $relativePath;
	}

	function GetWebPathForAsset($asset_id)
	{
		$partial = $this->GetRelativeAssetsPath($asset_id);
		if( ! $partial )
			return false;
		return $this->paths->WebStorageRoot . "/" . $partial;
	}

	//$ctx->images->getResizeCacheUrl($ctx->assetManager->getPartialAssetPath($user->assetId, $user->originalFilename), 30, 30);

	function getResizeUrl($assetId, $filename, $width, $height, $fitMode = ImageManager::FIT_COVER)
	{
		$path = $this->getPartialAssetPath($assetId, $filename);
		$url = $this->image->getResizeCacheUrl($path, $width, $height, $fitMode);

		return $url;
	}

	// asset_id must be a positive integer
	// returns a valid absolute asset path on the server
	// else false
	function GetAbsoluteFilePathForAsset($asset_id)
	{
		$partial = $this->GetRelativeAssetsPath($asset_id);
		if( ! $partial )
			return false;
		return $this->paths->AbsoluteStorageRoot . "/" . $partial;
	}

	/**
	 * @param $rawAssetId int original asset id from db, not yet base-converted or otherwise encoded
	 *
	 * @return string absolute folder for asset. folder may need to be created. append filename to it. prefixed and suffixed by "/"
	 */
	function getAbsoluteAssetStoragePath($rawAssetId)
	{
		$compressedId = base_convert($rawAssetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $this->paths->AbsoluteStorageRoot . '/' . $this->paths->RelativeStorageAssets . "/" . $relativePath;
	}

	/**
	 * @param $rawAssetId
	 * @return string an asset folder path relative to the storage root. suffixed but not prefixed by "/".
	 */
	function GetRelativeAssetsPath($rawAssetId)
	{
		$compressedId = base_convert($rawAssetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $this->paths->RelativeStorageAssets . '/' . $relativePath;
	}

	function createFolderPath($rawAssetId)
	{
		$compressedId = base_convert($rawAssetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $relativePath;
	}

	/**
	 * builds the minimal part of the asset path, which can be used by the image-cache and image-resizer
	 * @param $rawAssetId
	 * @param $filename
	 * @return string
	 */
	function getPartialAssetPath($rawAssetId, $filename)
	{
		$compressedId = base_convert($rawAssetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $relativePath . $filename;
	}

	/**
	 * builds a PARTIAL web-friendly url for accessing the asset.
	 * must attach the friendly filename.extension to the url to complete it
	 * @param int $rawAssetId id of the asset
	 * @param string $filename filename of the asset
	 * @return string publicly accessible web-url of the asset
	 */
	function getWebFullPath($rawAssetId, $filename = "")
	{
		$compressedId = base_convert($rawAssetId, 10, 36);
		$compressedId = str_pad($compressedId, 6, 0, STR_PAD_LEFT);
		$relativePath = chunk_split($compressedId, 3, "/");

		return $this->paths->WebStorageRoot . '/' .  $this->paths->RelativeStorageAssets . '/' . $relativePath . $filename;
	}

	// accepts a $_FILES object and an asset_id
	// if asset_id is of an existing item, it will be overwritten
	// filePath must have an existing folder-structure
	// returns destination path file on success
	// else false if there were any problems moving the uploaded file
	function TryUploadAsset($file, $filePath)
	{
		$this->log->writeObject("file:", $file);

		// verify file is an uploaded file
		if( ! is_uploaded_file($file["tmp_name"]) )
		{	// reject and log bad files
			$this->log->write("Error: tmp file is not a valid uploaded file");
			return false;
		}

		$actual_file_size = filesize($file["tmp_name"]);
		if( $actual_file_size != $file["size"] )
		{	// file is not as big as it was supposed to be. possibly failed upload?
			$this->log->write("Error: tmp file is not correct size");
			return false;
		}

		$this->log->write("Moving uploaded file to: $filePath");

		// this will overwrite any existing files
		if( ! move_uploaded_file($file["tmp_name"], $filePath) ) {
			$this->log->write("Error: failed to move file: '{$file["tmp_name"]}' to '$filePath'");
			return false;
		}

		$this->log->write("Moved uploaded file: '{$file["tmp_name"]}' to '$filePath'");

		return $filePath;
	}

	// downloads a blob and returns it
	function TryDownloadAsset($assetUrl)
	{
		try
		{
			$ch = curl_init($assetUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
		}
		catch(Exception $e)
		{
			$this->auditFacade->log("Error downloading asset $assetUrl:", $e);
		}

		return false;
	}
}

class AssetManager2
{
	// absolute storage root
	const AbsoluteStorageRoot = "/var/www/apptogether/storage"; // absolute for webapp
	const WebStorageRoot = "/storage"; // relative to domain
	const RelativeStorageRoot = "storage"; // relative to app

	// convert any asset path into an app-relative path
	static function FixupRelativeAssetPath($assetPath)
	{
		return self::RelativeStorageRoot . "/" . $assetPath;
	}

	// convert any asset-path into a web-relative path
	static function FixupWebAssetPath($assetPath)
	{
		return "/" . $assetPath;
	}

	static function GetWebPathFromRelativeFilePath($relativeFilePath)
	{
		return self::WebStorageRoot . "/" . $relativeFilePath;
	}

	static function GetWebPathForAsset($asset_id)
	{
		$partial = self::GetRelativeFilePathForAsset($asset_id);
		if( ! $partial )
			return false;
		return self::WebStorageRoot . "/" . $partial;
	}

	// asset_id must be a positive integer
	// returns a valid absolute asset path on the server
	// else false
	static function GetAbsoluteFilePathForAsset($asset_id)
	{
		$partial = self::GetRelativeFilePathForAsset($asset_id);
		if( ! $partial )
			return false;
		return self::AbsoluteStorageRoot . "/" . $partial;
	}

	static function GetAppRelativeFilePathForAsset($assetId)
	{
        $path = self::GetRelativeFilePathForAsset($assetId);
        if(!$path)
            return false;
		return self::RelativeStorageRoot . "/" . $path;
	}

	// asset_id must be a positive integer
	// returns a valid asset path relative to the storage folder
	// else false
	static function GetRelativeFilePathForAsset($asset_id)
	{
		// only store a few thousand files per folder

		// 2 million apps = easily 20 million images
		// 20,000,000

		// test
		$decimation = filter_var($asset_id, FILTER_VALIDATE_INT, 0);

		if( ! $decimation )
		{
			ErrorManager::OnLibraryError("Error: asset_id is not a valid integer: [$asset_id]");
			return false;
		}

		$part1 = $decimation % 1000; // 789
		$decimation = floor($decimation/1000);
		$part2 = $decimation % 1000; // 456
		$decimation = floor($decimation/1000);
		$part3 = $decimation % 1000; // 123

		// $part3/$part2/$part1 = 123/456/789

		$assetFolder = self::AbsoluteStorageRoot . "/$part3/$part2";

		// make sure folder exists
		if( ! is_dir($assetFolder) && ! mkdir($assetFolder, 0775, true) ) {
			ErrorManager::OnLibraryError("Error: failed to create needed folder: $assetFolder");
			return false;
		}

		return "$part3/$part2/$part1";
	}

	// accepts a $_FILES object and an asset_id
	// if asset_id is of an existing item, it will be overwritten
	// filePath must have an existing folder-structure
	// returns destination path file on success
	// else false if there were any problems moving the uploaded file
	static function TryUploadAsset($file, $filePath)
	{
		ErrorManager::getLogger()->writeObject("file:", $file);

		// verify file is an uploaded file
		if( ! is_uploaded_file($file["tmp_name"]) )
		{	// reject and log bad files
			ErrorManager::getLogger()->write("Error: tmp file is not a valid uploaded file");
			return false;
		}

		$actual_file_size = filesize($file["tmp_name"]);
		if( $actual_file_size != $file["size"] )
		{	// file is not as big as it was supposed to be. possibly failed upload?
			ErrorManager::getLogger()->write("Error: tmp file is not correct size");
			return false;
		}

		// this will overwrite any existing files
		if( ! move_uploaded_file($file["tmp_name"], $filePath) ) {
			ErrorManager::getLogger()->write("Error: failed to move file: '{$file["tmp_name"]}' to '$filePath'");
			return false;
		}

		return $filePath;
	}

	// downloads a blob and returns it
	static function TryDownloadAsset($assetUrl)
	{
		try
		{
			$ch = curl_init($assetUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$data = curl_exec($ch);
			curl_close($ch);
			return $data;
		}
		catch(Exception $e)
		{
			ErrorManager::getLogger()->writeObject("Error downloading asset $assetUrl:", $e);
		}

		return false;
	}
}
