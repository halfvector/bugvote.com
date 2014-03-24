<?php namespace Bugvote\Core;

class SignedUrl
{
	protected $userSignatureKey;

	public $url;
	public $signature;

	function __construct($url, $userId)
	{
		$this->userSignatureKey = "userId=$userId signature";
		$this->url = urlencode($url);
		$this->signature = hash_hmac('md5', $url, $this->userSignatureKey);
	}
}