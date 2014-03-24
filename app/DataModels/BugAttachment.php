<?php namespace Bugvote\DataModels;

use Bugvote\Services\Context;
use Exception;

class PermissionsError extends Exception
{
}
class BugAttachment
{
	protected $ctx;
	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}

	// upload a file and creating an attachment
	function createFromFileUpload($userId, $suggestionId, $file)
	{
		if( ! $this->ctx->permissions->CanUserModifySuggestion($userId, $suggestionId) )
		{
			$this->ctx->log->writeObject("Error: user $userId not allowed to modify $suggestionId", $file);
			return false;
		}

		$asset = new Asset($this->ctx);
		$assetId = $asset->createAsset($file);
		if(!$assetId)
		{
			$this->ctx->log->writeObject("Error: failed to create asset for attachment", $file);
			return false;
		}

		$fileExtension = pathinfo($file["name"], PATHINFO_EXTENSION);

		// TODO: classify attachment mime-type

		// add suggestion-attachment entry
		$suggestionAttachmentId = $this->ctx->dal->insert("suggestionAttachments")->set([
			'assetId' => $assetId, 'attachmentName' => $file["name"],
			'extension' => $fileExtension, 'suggestionId' => $suggestionId, 'attachmentType' => 1
		]);

		$this->ctx->log->write("Asset created; \$suggestionAttachmentId=$suggestionAttachmentId of type {$file["type"]} with assetId=$assetId");

		return true;
	}
}