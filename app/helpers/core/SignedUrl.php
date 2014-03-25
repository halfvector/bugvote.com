<?php namespace Bugvote\Core;

class SignedUrl
{
	protected $userSignatureKey;

	public $url;
	public $signature;

	/**
	 * just an idea for now.
	 * should use a session-backed secret for signing.
	 * @param $url
	 * @param $userId
	 */
	function __construct($url, $userId)
	{
		$this->userSignatureKey = "userId=$userId signature";
		$this->url = urlencode($url);
		$this->signature = hash_hmac('md5', $url, $this->userSignatureKey);
	}
}