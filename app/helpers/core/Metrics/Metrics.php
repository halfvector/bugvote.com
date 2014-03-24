<?php namespace Bugvote\Core\Metrics;

class Metrics
{

	function Send2AIM($user, $app, $section)
	{
		$this->Post("https://zapier.com/hooks/catch/n/4cpzx/", ["user" => $user, "app" => $app, "section" => $section]);
	}

	function Send2HipChat($user, $app, $section)
	{
		$this->Post("https://zapier.com/hooks/catch/n/4cnhe/", ["user" => $user, "app" => $app, "section" => $section]);
	}

	function Post($url, $data)
	{
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_POST => 1,
			CURLOPT_HTTPHEADER => [
				"Content-Type: application/json",
				"Accept: application/json"
			],
			CURLOPT_POSTFIELDS => json_encode($data)
		]);
		$result = curl_exec($curl);
		curl_close($curl);

		return $result;
	}
}
