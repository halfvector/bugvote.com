<?php namespace Bugvote\Services;

use Bugvote\Core\SessionSegment;

class UserSession
{
	const DefaultSessionExpirySecs = 2592000; // 30 days
	/** @var Context */
	protected $ctx;
	/** @var SessionSegment */
	protected $auth;

	protected function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
		$this->auth = new SessionSegment("auth");
	}

	static function Open(Context $ctx)
	{
		$user = new UserSession($ctx);
		$user->process();
		return $user;
	}

	/**
	 * extracts session id from user's browser-cookie if possible
	 * then validates it against server-side sessions, or database
	 */
	protected function process()
	{
		$cookieSignature = filter_input(INPUT_COOKIE, "signature", FILTER_SANITIZE_STRING); // may be missing
		$cookieAuthId = filter_input(INPUT_COOKIE, "authId", FILTER_SANITIZE_STRING); // will always be there

		if ($cookieSignature && $cookieAuthId) { // we have a cookie that claims to belong to an authenticated user

			if (!$this->auth->signature || !$this->auth->userId) {
				// if we have a cookie-signature but not a session-signature, try to restore the session
				$this->cleanupSession();

				$this->ctx->log->write("restoring session from cookies: $cookieSignature/$cookieAuthId");
				$storedSession = $this->ctx->dal->fetchSingleObj(
					"SELECT * FROM userSessions WHERE authId = :authId AND signature = :signature LIMIT 1",
					[":authId" => $cookieAuthId, ":signature" => $cookieSignature]
				);

				if (!$storedSession) {
					// we have a browser-cookie backed session that is no longer matched by database
					// this could have resulted from a db-outage/rollback or other error
					// best thing to do is log the user out and force them to login again
					$this->destroySessionAndCookies();
				} else {
					// otherwise the session was found, so we can rebuild it in the local cache
					$this->auth->userId = $storedSession->userId;
					$this->auth->signature = $cookieSignature;
				}
			} else if ($this->auth->signature != $cookieSignature) { // signature mismatch -- destroy session immediately and dump the cookies (sanity restoration)
				$this->ctx->log->write("Session signature mismatch: {$this->auth->signature} != $cookieSignature");
				$this->destroySessionAndCookies();
			}
		} else { // no auth cookies
			if ($this->auth->signature && $this->auth->userId) { // but we have auth session, destroy it
				$this->ctx->log->write("session doesn't match cookies, destroying session");
				$this->ctx->log->writeObject("session:", $_SESSION);
				$this->ctx->log->writeObject("cookies:", $_COOKIE);
				$this->destroySessionAndCookies();
			}
		}
	}

	// opening the user session isn't free, so we should do this on startup
	// and determine if we have an anonymous user or a logged in user
	protected function cleanupSession()
	{
		foreach ($_SESSION as $key => $value)
			unset($_SESSION[$key]);

		$this->auth = new SessionSegment("auth");
	}

	protected function destroySessionAndCookies()
	{
		$this->ctx->log->write("Destroying session and cookies");

		// erase all cookies
		foreach ($_COOKIE as $key => $value)
			setcookie($key, null, null, '/');

		foreach ($_SESSION as $key => $value)
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

	function getUser()
	{
		return new \Bugvote\DataModels\User($this->ctx, $this->getUserId());
	}

	function getUserId()
	{
		return $this->auth->userId;
	}

	function newSegment($name)
	{
		return new SessionSegment($name);
	}

	// called for an authenticated user that should be logged in
	// create a php-session and link it to user's cookie
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
		$this->setCookie("authId", $authId, $expiry, '/', null, false, true);
		$this->setCookie("signature", $signature, $expiry, '/', null, false, true);

		// store userid and signature in session
		$this->auth->userId = $userId;
		$this->auth->authId = $authId;
		$this->auth->signature = $signature;
	}

	protected function setCookie($key, $value = '', $expiry = null, $path = '/', $domain = null, $secure = false, $httponly = false)
	{
		if (null === $expiry) {
			$expiry = time() + (3600 * 24 * 30);
		}
		return setcookie($key, $value, $expiry, $path, $domain, $secure, $httponly);
	}

	function logout()
	{
		$this->ctx->log->write("Logging out");

		// destroy and start anew
		$this->destroySessionAndCookies();
	}

	protected function compactSession()
	{
		foreach ($_SESSION as $key => $value) {
			if (is_array($value) && count($value) == 0)
				unset($_SESSION[$key]);
		}
	}
}