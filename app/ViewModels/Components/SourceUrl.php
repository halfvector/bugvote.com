<?php namespace Bugvote\ViewModels\Components;

class SourceUrl
{
	protected $userSignatureKey;

	public $url;
	public $signature;

	function __construct($userId)
	{
		$this->userSignatureKey = "userId=$userId signature";
		$this->url = urlencode($_SERVER['REQUEST_URI']);
		$this->signature = hash_hmac('md5', $this->url, $this->userSignatureKey);
	}

	static function sign($url, $userId)
	{
		$key = self::getSignatureKey($userId);
		$signature = hash_hmac('md5', $url, $key);
		return $signature;
	}

	static function getSignatureKey($userId)
	{
		return "userId=$userId signature";
	}
}