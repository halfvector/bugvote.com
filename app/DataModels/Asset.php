<?php namespace Bugvote\DataModels;

use Bugvote\Services\Context;
use Exception;

class AssetException extends Exception
{
	public $details;

	function __construct($message, $obj)
	{
		parent::__construct($message);
		$this->detail = $obj;
	}
}

class Asset extends ContextDataModel
{
	static function SanitizeAssetFilename($rawFilename)
	{
		$filename = iconv('UTF-8', 'ASCII//TRANSLIT', $rawFilename); // convert unicode to ascii
		$filename = trim($filename,"."); // trim leading and trailing periods

		// save the extension (could be a complex one like .tar.gz)

		$filename = preg_replace("/[^.0-9a-zA-Z_-]/", "_", $filename); // replace funny characters with _
		$filename = $filename ?: "default";

		if(strlen($filename) > 32)
			$filename = substr($filename, strlen($filename) - 32, 32);

		return $filename;
	}

	function createAsset($file)
	{
		$ctx = $this->ctx;
		$log = $ctx->log;

		if(!$file)
		{
			$log && $log->writeObject("Error: Asset not created, file object was null", $file);
			return false;
		}

		if($file['error'])
		{
			$log && $log->writeObject("Error: File upload had error-code: {$file['error']}", $file);
			return false;
		}

		// sanity checks
		if(!is_uploaded_file($file["tmp_name"]))
		{
			$log && $log->writeObject("Error: tmp file is not a valid uploaded file", $file);
			return false;
		}

		if(empty($file["name"]))
		{
			$log && $log->writeObject("Error: uploaded file does not have a valid name", $file);
			return false;
		}

		// sanitize filename
		$assetFilename = self::SanitizeAssetFilename($file["name"]);

		$ctx->dal->beginTransaction();

		try
		{
			// insert a placeholder
			$assetId = $ctx->dal->insert("assets")->set(['isValid' => false, 'originalFilename' => $assetFilename]);

			// get path
			$assetDir = $ctx->assetManager->getAbsoluteAssetStoragePath($assetId);

			// create path
			if(!file_exists($assetDir))
			{
				$log && $log->write("Creating asset (assetId=$assetId) folder: $assetDir");
				if(!mkdir($assetDir, 0775, true))
				{
					$ctx->dal->rollbackTransaction();
					$log && $log->write("Error chmoding asset folder: $assetDir");
					return false;
				}
			}

			// build final path
			$assetFilePath = $assetDir . $assetFilename;

			// go ahead and upload
			if( ! $ctx->assetManager->TryUploadAsset($file, $assetFilePath) )
			{
				$ctx->dal->rollbackTransaction();
				$log && $log->write("Asset update Failed: assetId=$assetId filePath=$assetFilePath");
				return false;
			}

			// TODO: classify asset-type

			// enable asset
			$ctx->dal->update('assets')->set(['isValid' => true, 'mimeType' => $file["type"]])->where(['assetId' => $assetId]);

			// save
			$ctx->dal->commitTransaction();
		}
		catch(Exception $err)
		{
			$log && $log->write("Error  creating asset: " . $err->getMessage());
			$ctx->dal->rollbackTransaction();
			return false;
		}

		$log && $log->write("Asset created=$assetFilePath with assetId=$assetId");

		return $assetId;
	}
}