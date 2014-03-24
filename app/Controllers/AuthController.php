<?php namespace Bugvote\Controllers;

use Bugvote\Commons\BaseController;
use Bugvote\Services\Context;
use Bugvote\Core\Auth\OpAuthParser;
use Bugvote\Core\UserManager;

class OAuthSetup
{
	public static function GetConfig()
	{
		return json_decode(file_get_contents(BUGVOTE_APP . '/oauth.conf'), true);
	}
}

class AuthController extends BaseController
{

	/**
	 * @param Context $ctx
	 * @route GET /auth/request/facebook[.*]
	 * @route GET /auth/request/twitter[.*]
	 */
	public function requestFacebook(Context $ctx)
	{
		$opauth = new \Opauth(OAuthSetup::GetConfig(), true);
	}

	public function requestTwitter(Context $ctx)
	{
		$opauth = new \Opauth(OAuthSetup::GetConfig(), true);
	}

	/**
	 * @param Context $ctx
	 * @route POST /auth/response
	 */
	public function response(Context $ctx)
	{
		$opauth = new \Opauth(OAuthSetup::GetConfig(), false);

		$response = unserialize(base64_decode($_POST['opauth']));

		if(array_key_exists("error", $response))
		{
			var_dump("opauth has error");
			var_dump($response["error"]);

			// TODO: show oauth error screen

			return;
		}

		$valid = $opauth->validate(sha1(print_r($response['auth'], true)), $response['timestamp'], $response['signature'], $reason);

		$socialUserData = null;

		if( $response['auth']['provider'] == 'Facebook' )
			$socialUserData = OpAuthParser::ParseFacebookResponse($response);

		if( $response['auth']['provider'] == 'Twitter' )
			$socialUserData = OpAuthParser::ParseTwitterResponse($response);

		$ctx->log->writeObject("got social user data:", $socialUserData);

		// user info arrived from a social oauth provider
		// let UserManager figure out what to do with it
		$userManager = new UserManager($ctx);
		$userManager->OnUserAuthenticated($socialUserData);
	}
}