<?php namespace Bugvote\Core\DAL;

use Bugvote\Core\Paths;

class DatabaseSettings
{
	// select a database-entry from the database config file
	/**
	 * @param string $mode
	 * @param $paths Paths
	 * @return mixed
	 */
	public static function load($mode = "staging", $paths)
	{
		$settings = json_decode(file_get_contents($paths->AbsoluteAppPath . "/dal.conf"));
		return $settings->$mode;
	}
}
