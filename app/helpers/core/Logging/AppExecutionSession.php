<?php namespace Bugvote\Core\Logging;

class AppExecutionSession
{
	protected function __construct()
	{
	}

	public static function GetUUID()
	{
		// machineid + time + random
		if(!self::$UUID)
			self::$UUID = uniqid('host=' . php_uname('n') . ';pid=' . getmypid() . ';date=' . date('Ymd') . ';id=');

		return self::$UUID;
	}

	protected static $Metadata = false;

	public static function getMetadata()
	{
		if(!self::$Metadata)
		{
			$metadata = new AppExecutionSession();
			$metadata->host = php_uname('n');
			$metadata->pid = getmypid();
			$metadata->date = date('Ymd');
			$metadata->uuid = uniqid();
			self::$Metadata = $metadata;
		}

		return self::$Metadata;
	}
}
