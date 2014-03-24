<?php namespace Bugvote\Services;

use Bugvote\Core\SessionSegment;

/**
 * Class UserSession
 * @package App\DataModels
 * Manages data relating to Users and Sessions
 */

class User
{
	function getUserId()
	{
	}
}

class CookieProvider
{

}

class SessionProvider
{
	function get($key, $default = null)
	{
		if(!isset($_SESSION[$key]))
			return $default;
		return $_SESSION[$key];
	}

	function set($key, $value)
	{
		$_SESSION[$key] = $value;
	}

	function push($arrayName, $value)
	{
		if(!isset($_SESSION[$arrayName]))
			$_SESSION[$arrayName] = [];

		$_SESSION[$arrayName] []= $value;
	}
}

class UserSession
{
	const DefaultSessionExpirySecs = 2592000; // 30 days

	/** @var Context */
	protected $ctx;

	/** @var SessionSegment */
	protected $auth;

	function getUserId()
	{
		return $this->auth->userId;
	}

	function getUser()
	{
		return new \Bugvote\DataModels\User($this->ctx, $this->getUserId());
	}

	protected function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
		$this->auth = new SessionSegment("auth");
	}

	// opening the user session isn't free, so we should do this on startup
	// and determine if we have an anonymous user or a logged in user
	static function Open(Context $ctx)
	{
		$user = new UserSession($ctx);
		$user->process();
		return $user;
	}

	/*
	// creates a new user session
	// 1) logs it in the database
	// 2) creates cookies
	function createSession($userId)
	{
		$salt = sha1(mt_rand());
		$signature = hash_hmac("sha256", $salt, $userId);

		// start session in database first, then share it with http server and user's browser
		$sessionId = $this->ctx->dal->insertSingleObj(
			"insert into userSessions (userId, signature) " .
			"values (:userId, :signature)",
			[
				":userId" => $userId,
				":signature" => $signature
			]
		);

		if( ! $sessionId )
		{
			$this->ctx->log->write("Error: Failed to create a new user session");
			return false;
		}

		$this->resetSession();

		// expire in 30 days
		$expiry = time() + self::DefaultSessionExpirySecs;

		// ensure the cookie's php-session-id wasn't forged/guessed by an attacker
		// create a secondary hash
		$salt = sha1(mt_rand());
		$signature = hash_hmac("sha256", $salt, $this->auth->sessionId);

		$this->auth->signature = $signature;

		$this->set_cookie("signature", $signature, $expiry, '/', null, false, true);
		//return $this->rebuildSession($userId, $sessionId, $signature);
	}
	*/

	/**
	 * extracts session id from user's browser-cookie if possible
	 * then validates it against server-side sessions, or database
	 */
	protected function process()
	{
		$cookieSignature = filter_input(INPUT_COOKIE, "signature", FILTER_SANITIZE_STRING); // may be missing
		$cookieAuthId = filter_input(INPUT_COOKIE, "authId", FILTER_SANITIZE_STRING); // will always be there

		if($cookieSignature && $cookieAuthId)
		{   // we have a cookie that claims to belong to an authenticated user

			if(!$this->auth->signature || !$this->auth->userId)
			{
				// if we have a cookie-signature but not a session-signature, try to restore the session
				$this->cleanupSession();

				$this->ctx->log->write("restoring session from cookies: $cookieSignature/$cookieAuthId");
				$storedSession = $this->ctx->dal->fetchSingleObj(
					"select * from userSessions where authId = :authId and signature = :signature limit 1",
					[":authId" => $cookieAuthId, ":signature" => $cookieSignature]
				);

				if(!$storedSession) {
					// we have a browser-cookie backed session that is no longer matched by database
					// this could have resulted from a db-outage/rollback or other error
					// best thing to do is log the user out and force them to login again
					$this->destroySessionAndCookies();
				} else {
					// otherwise the session was found, so we can rebuild it in the local cache
					$this->auth->userId = $storedSession->userId;
					$this->auth->signature = $cookieSignature;
				}
			}
			else if($this->auth->signature != $cookieSignature)
			{   // signature mismatch -- destroy session immediately and dump the cookies (sanity restoration)
				$this->ctx->log->write("Session signature mismatch: {$this->auth->signature} != $cookieSignature");
				$this->destroySessionAndCookies();
			}
		} else
		{   // no auth cookies
			if($this->auth->signature && $this->auth->userId)
			{   // but we have auth session, destroy it
				$this->ctx->log->write("session doesn't match cookies, destroying session");
				$this->ctx->log->writeObject("session:", $_SESSION);
				$this->ctx->log->writeObject("cookies:", $_COOKIE);
				$this->destroySessionAndCookies();
			}
		}
	}

	protected function destroySessionAndCookies()
	{
		$this->ctx->log->write("Destroying session and cookies");

		// erase all cookies
		foreach($_COOKIE as $key => $value)
			setcookie($key, null, null, '/');

		foreach($_SESSION as $key => $value)
			unset($_SESSION[$key]);

		// destroy old session data
		session_destroy();

		// create a new session
		session_start();
		session_regenerate_id(true); // this will update PHPSESSID reference

		// session_id() is automatically stored in cookie/session by PHP above via PHPSESSID

		// empty local session cache (this is stupid)
		//$_SESSION = array();

		// create default session space
		$this->auth = new SessionSegment("auth");

		//$this->set_cookie("signature", null, null, '/', null, false, true);
	}

	/*
	protected function rebuildSession($userId, $sessionId, $signature)
	{
		// wipe any existing cookie/session data
		self::resetSession();

		// expire in 30 days
		$expiry = time() + self::DefaultSessionExpirySecs;

		$this->auth->signature = $signature;

		$this->ctx->log->Write("Setup session: userId=$userId, sessionId=$sessionId, signature=$signature");

		// client-side data (this cookie must not be accessible by javascript)
		// auth signature is a secret that must match to the php-session's stored data
		// this ensures if someone somehow hijacks a php-session, that they wont past the authenticity test
		//$this->set_cookie("authSessionId", $sessionId, $expiry, '/', null, false, true);
		$this->set_cookie("authSignature", $signature, $expiry, '/', null, false, true);

		return true;
	}
	*/

	protected function set_cookie($key, $value = '', $expiry = null, $path = '/', $domain = null, $secure = false, $httponly = false)
	{
		if (null === $expiry) {
			$expiry = time() + (3600 * 24 * 30);
		}
		return setcookie($key, $value, $expiry, $path, $domain, $secure, $httponly);
	}

	function newSegment($name) {
		return new SessionSegment($name);
	}

	// assumes user logged in, create a php-session and link it to user's cookie
	function login($userId)
	{
		$this->ctx->log->write("Logging in");

		// attempting a login, destroy any existing session and create a new one
		$this->destroySessionAndCookies();

		$deviceHint = $_SERVER["HTTP_USER_AGENT"];

		// store signature in session and cookie
		$expiry = time() + 3600 * 24 * 365 * 10; // 10 years

		// ensure the cookie's php-session-id wasn't forged/guessed by an attacker
		$salt = sha1(mt_rand());
		$signature = hash_hmac("sha256", $salt, $userId);

		// create permanent logon
		$authId = $this->ctx->dal->insertSingleObj(
			"insert into userSessions (userId, signature, device) " .
			"values (:userId, :signature, :device)", [
				":userId" => $userId,
				":signature" => $signature,
				":device" => $deviceHint
			]
		);

		// create long-term cookie in browser
		$this->set_cookie("authId", $authId, $expiry, '/', null, false, true);
		$this->set_cookie("signature", $signature, $expiry, '/', null, false, true);

		// store userid and signature in session
		$this->auth->userId = $userId;
		$this->auth->authId = $authId;
		$this->auth->signature = $signature;
	}

	function logout()
	{
		$this->ctx->log->write("Logging out");

		// destroy and start anew
		$this->destroySessionAndCookies();
	}

	protected function cleanupSession()
	{
		foreach($_SESSION as $key => $value)
			unset($_SESSION[$key]);

		$this->auth = new SessionSegment("auth");
	}

	protected function compactSession()
	{
		foreach($_SESSION as $key => $value)
		{
			if(is_array($value) && count($value) == 0)
				unset($_SESSION[$key]);
		}
	}
}