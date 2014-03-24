<?php

namespace Bugvote\Core;

use Bugvote\Services\Context;
use Exception;

class UserManager
{
	protected $ctx;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}
	
	function CreateNewUser($socialUserData)
	{
		try
		{
			// create new user
			$this->ctx->dal->beginTransaction();

			$userId = $this->ctx->dal->insertSingleObj(
				"insert into users set fullName = :fullName",
				array(":fullName" => $socialUserData["fullName"])
			);

			$this->UpdateUserMetadata($socialUserData, $userId);

			$this->ctx->dal->commitTransaction();

			// start cookie backed session..
			$this->ctx->user->login($userId);

			return $userId;
		}
		catch(Exception $e)
		{
			$this->ctx->log->Write("Error creating new user; Rolling back transaction.");
			$this->ctx->dal->rollbackTransaction();
			return false;
		}
	}

	// update the specified user with possibly new partials data
	function UpdateUserMetadata($socialUserData, $userId)
	{
		// grab an existing profile image asset-id
		$assetId = $this->ctx->dal->fetchSingleValue(
			"select profilePicAssetId from socialAccounts where userId = :userId and socialProviderId = :socialProviderId",
			array(":userId" => $userId, ":socialProviderId" => $socialUserData["providerId"])
		);

		// if no assetId, create a new one
		if( ! $assetId )
		{
			$this->ctx->log->Write("Creating new assetId for socialAccount userId=$userId and socialProviderId={$socialUserData["providerId"]}");
			$assetId = $this->ctx->dal->insertSingleObj("insert into assets set isValid = false");
		}

		// sanitize the url
		$profilePicUrl = $socialUserData["profileImage"];

		// download and sanitize the raw data (make sure its a clean image, nothing fishy)
		$rawImage = $this->ctx->assetManager->TryDownloadAsset($profilePicUrl);

		if( $rawImage )
		{
			// get the path for the asset and copy the contents
			$assetFilePath = $this->ctx->assetManager->GetAbsoluteFilePathForAsset($assetId);
			if( $assetFilePath ) {
				file_put_contents($assetFilePath, $rawImage);
				chmod($assetFilePath, 0664);
			}

			$this->ctx->dal->updateSingleObj(
				"update assets set isValid = true, assetPath = :assetPath where assetId = :assetId",
				[":assetPath" => $assetFilePath, ":assetId" => $assetId]
			);
		}

		// there shouldn't be any duplicates, unless a race-condition occured
		// in which case this whole thing should fail and rollback
		$socialUserId = $this->ctx->dal->insertSingleObj(
			"insert into socialAccounts set ".
			"socialUserId = :socialUserId, fullName = :fullName, userId = :userId, profilePicAssetId = :profilePicAssetId, ".
			"socialProviderId = :socialProviderId, credentials = :credentials ".
			"on duplicate key update ".
			"fullName = :fullName, profilePicAssetId = :profilePicAssetId, socialUserId=last_insert_id(socialUserId), ".
			"credentials = :credentials"
			,
			[
				":socialUserId" => $socialUserData["id"],
				":fullName" => $socialUserData["fullName"],
				":userId" => $userId,
				":profilePicAssetId" => $assetId,
				":socialProviderId" => $socialUserData["providerId"],
				":credentials" => json_encode($socialUserData['credentials'])
			]
		);
	}

	// figure out what to do with this data based on user's current login-status
	function OnUserAuthenticated($socialUserData)
	{
		$ctx = $this->ctx;

		$ctx->log->WriteObject("Processing socialUserData", $socialUserData);

		$currentUserId = $ctx->user->GetUserId();

		// is user already logged in?
		if( ! $currentUserId )
		{	// user is not logged in according to her cookies.
			// see if we can log her in using her partials-account

			$userId = $this->GetUserIdFromSocialMediaAccount($socialUserData["id"], $socialUserData["providerId"]);

			// is this social-oauth user already in our system?
			if( ! $userId )
			{	// new user!
				return $this->CreateNewUser($socialUserData);
			} else
			{	// oauthed user already has an account with us but isn't logged in
				// log them in
				$ctx->user->login($userId);
			}
		} else
		{	// user is logged in

			// check if this partials-media account is associated in any way
			$userId = $this->GetUserIdFromSocialMediaAccount($socialUserData["id"], $socialUserData["providerId"]);

			if( $userId && $userId != $currentUserId )
			{	// we have a mismatch: user logged in using account A but partials-media is already associated with account B
				// user may have duplicate accounts, or something funny is going on
				// either way this is a sanity failure, tell the user they must disconnect the partials-media integration from account B first
				header("Location: /error/duplicate_account", true, 303);
				exit;
			} else
			{	// user is already logged in, so update their existing account with this partials-media association
				$this->UpdateUserMetadata($socialUserData, $userId);
			}
		}

		$userId = $ctx->url->createUserUrl($userId);

		// now redirect user to whatever page they wanted
		header("Location: /u/$userId", true, 303);
		exit;
	}

	function GetUserIdFromSocialMediaAccount($socialMediaId, $socialMediaProviderId)
	{
		$userId = $this->ctx->dal->fetchSingleValue(
			"select userId from socialAccounts where socialUserId = :socialUserId and socialProviderId = :socialProviderId",
			array(":socialUserId" => $socialMediaId, ":socialProviderId" => $socialMediaProviderId)
		);

		return $userId;
	}
}