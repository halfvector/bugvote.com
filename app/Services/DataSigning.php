<?php namespace Bugvote\Services;

use Exception;

interface ICSRF
{
	function getCSRF($token);
	function validateCSRF($csrf);
}

class DataSigning implements ICSRF
{
	protected $ctx;

	const CSRF_VALID = 1;
	const CSRF_INVALID = 0;
	const CSRF_EXPIRED = -1;

	const CSRF_MaxFormAgeSeconds = 3600;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}

	function getCSRF($token)
	{
		return $this->generateTimedCsrf($token);
	}

	function validateCSRF($csrf)
	{
		$csrfState = $this->getCsrfState($csrf);

		if( $csrfState != self::CSRF_VALID )
		{
			$this->ctx->log->write("Warning: CSRF is invalid");
			return false;
		}

		return true;
	}

	function encode($data)
	{
		return base64_encode(json_encode($data));
	}

	function decode($data)
	{
		return json_decode(base64_decode($data));
	}

	protected function getCsrfState($csrf)
	{
		$csrf_data = $this->extractTimedCsrf($csrf);

		if( $csrf_data === false )
		{	// completely invalid (forged?)
			$this->ctx->log->write("CSRF token completely invalid");
			return self::CSRF_INVALID;
		}

		if( !isset($csrf_data->csrf_time) )
		{
			$this->ctx->log->write("CSRF token missing time");
			return self::CSRF_INVALID;
		}

		// age of form when it was submitted
		$form_age = time() - (isset($csrf_data->csrf_time) ? $csrf_data->csrf_time : 0);

		$this->ctx->log->write("\$form_age = $form_age seconds");

		if( $form_age > self::CSRF_MaxFormAgeSeconds )
		{
			$this->ctx->log->write("CSRF is too old: $form_age");
			return self::CSRF_EXPIRED;
		}

		return self::CSRF_VALID;
	}

	protected function generateTimedCsrf($token)
	{
		// values
		$data = [
			"csrf_time" => time(),
			"csrf_token" => "$token/" . base64_encode(mt_rand()),
		];

		$data_line = base64_encode(json_encode($data));
		$signature = $this->getCsrfSessionTokenFor($data_line);

		$csrf_value = "$data_line,$signature";

		return $csrf_value;
	}

	protected function extractTimedCsrf($csrf)
	{
		if( $csrf == null )
			return false;

		try
		{
			list($data_line, $signature) = explode(",", $csrf);
			//$data = explode(".", $data_line);
			$data = json_decode(base64_decode($data_line));

			$correct_signature = $this->getCsrfSessionTokenFor($data_line);
		}
		catch(Exception $e)
		{
			//echo "caught exception:";
			//dump($e);
			return false;
		}

		if( $correct_signature === $signature )
		{	// signature is correct, this csrf blob is legit
			return $data;
		}

		// signature is invalid, csrf blob is a forgery!
		return false;
	}

	protected function getCsrfSessionTokenFor($formName)
	{
		// ensure we have a valid session-token
		$session_token = $this->getSessionToken();

		//echo "\$session_token: $session_token<br>\n";

		// generate a new CSRF token based on session-token
		$csrf_token = base64_encode(hash_hmac("sha256", $formName, $session_token));

		return $csrf_token;
	}

	protected function getSessionToken()
	{
		if( session_id() == "" )
			session_start();

		// generate a nice long bit of random salt to use as a key for short-term signatures
		if( ! isset($_SESSION['session_token']) )
			$_SESSION['session_token'] = sha1(uniqid(mt_rand(), TRUE));

		return $_SESSION['session_token'];
	}
}