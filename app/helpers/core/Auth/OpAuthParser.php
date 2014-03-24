<?php namespace Bugvote\Core\Auth;

// handles "poauth" responses, which include signatures, credentials, nicely formatted data, and raw data
// mostly this tries to pull as much useful data from the 'raw' field as possible

/*
 * try to guess the user's timezone
 * I will try to avoid all attempts to do correct local-time, since its such a horrible pain
 * with various time-zones whose daylight saving start/end are rarely predictable
 * better to just display the "age" of data, or use js client-side to convert utc timestamps into local times
 *
 * don't bother with geo-data. just geocode the ip, it will probably be more accurate and less hastle.
 */

class OpAuthParser
{
	public static function TestFacebookParsing()
	{
		$data = file_get_contents("sample-data/auth.facebook.json");
		$parsed = self::ParseFacebookResponse(json_decode($data, true));
		dump($parsed);
	}

	public static function TestTwitterParsing()
	{
		$data = file_get_contents("sample-data/auth.twitter.json");
		$parsed = self::ParseTwitterResponse(json_decode($data, true));
		dump($parsed);
	}

	public static function ParseFacebookResponse($data)
	{
		// auth['info is a pretty stable data structure
		$poauthInfo = $data['auth']['info'];
		$raw = $data['auth']['raw'];

		$userInfo = [];
		$userInfo["providerId"] = 1;
		$userInfo["provider"] = "Facebook";
		$userInfo["id"] = $data['auth']['uid'];
		$userInfo["fullName"] = $poauthInfo['name'];
		$userInfo["profileImage"] = $poauthInfo['image'];
		$userInfo["nickName"] = $poauthInfo['nickname'];
		$userInfo["verified"] = $raw['verified'];
		$userInfo["credentials"] = $data['auth']['credentials'];

		// get the biggest pictures we can
		$userInfo["profileImage"] = str_replace("type=square", "type=large", $userInfo["profileImage"]);

		// timezone guess
		$userInfo["utcOffset"] = $raw['timezone'];

		return $userInfo;
	}

	public static function ParseTwitterResponse($data)
	{
		// auth['info is a pretty stable data structure
		$poauthInfo = $data['auth']['info'];
		$raw = $data['auth']['raw'];

		$userInfo = [];
		$userInfo["providerId"] = 2;
		$userInfo["provider"] = "Twitter";
		$userInfo["id"] = $data['auth']['uid'];
		$userInfo["fullName"] = $poauthInfo['name'];
		$userInfo["profileImage"] = $poauthInfo['image'];
		$userInfo["nickName"] = $poauthInfo['nickname'];
		$userInfo["verified"] = $raw['verified'];
		$userInfo["credentials"] = $data['auth']['credentials'];

		$userInfo["numOfFollowers"] = $raw['followers_count'];
		$userInfo["numOfTweets"] = $raw['statuses_count'];
		$userInfo["friends_count"] = $raw['friends_count'];
		// timezone guess
		$userInfo["utcOffset"] = $raw['utc_offset'] / 3600;

		return $userInfo;
	}
}