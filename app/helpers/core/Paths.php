<?php namespace Bugvote\Core;

class Paths
{
	// absolute storage root
	public $AbsoluteStorageRoot = null; // eg: /var/www/apptogether/storage
	public $WebStorageRoot = "/storage"; // relative to domain
	public $RelativeStorageRoot = "storage"; // relative to app
	public $RelativeStorageAssets = "assets"; // relative to storage
	public $AbsoluteRoot = null;
	public $AbsoluteLibsPath;
	public $AbsoluteAppPath;
	public $AbsoluteTmpPath;
	public $AbsoluteWebStorageRoot = "://static.dev.bugvote.com"; // full path
	public $AbsoluteWebCacheRoot = "//static.dev.bugvote.com/cache"; // full path

	public function __construct($root)
	{
		$this->AbsoluteRoot = $root;
		$this->AbsoluteStorageRoot = $root . '/' . $this->RelativeStorageRoot;
		$this->AbsoluteLibsPath = $root . '/app/helpers/';
		$this->AbsoluteAppPath = $root . '/app/';
		$this->AbsoluteTmpPath = $root . '/tmp/';
	}
}