<?php namespace Bugvote\Commons;

class TimeHelper
{
	static function MySQLTimestampToISO8601($timestamp)
	{
		$unix_timestamp = strtotime($timestamp);
		// simple timezone marker
		//return date('o-m-d', $unix_timestamp) . "T" . date('H:i:s', $unix_timestamp) . "Z";

		// full timezone designator
		return date("c", $unix_timestamp);
	}

	static function SmartShortAge($secs)
	{
		$secs = max(1,ceil($secs));

		$bit = array(
			' year'        => $secs / 31556926 % 12,
			' month'        => $secs / 2592000 % 12,
			' week'        => $secs / 604800 % 52,
			' day'        => $secs / 86400 % 7,
			' hour'        => $secs / 3600 % 24,
			' minute'    => $secs / 60 % 60,
			' second'    => $secs % 60
		);

		$ret = [];
		foreach($bit as $k => $v) {
			if($v > 1) $ret []= $v . $k . 's';
			if($v == 1) $ret []= $v . $k;
		}

		$best = array_shift($ret);
		return $best;
	}
}