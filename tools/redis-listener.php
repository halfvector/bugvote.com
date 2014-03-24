<?php

include __DIR__ . "/../vendor/autoload.php";
use Cowlby\Loggly\ApiLogger;

include "listener-renderer.php";

// install signal hook
pcntl_signal(SIGHUP, function ($signo) {
	echo "caught hangup signal, exiting..\n";
	exit;
});

$redis = new Redis();
//$redis->connect("127.0.0.1:6379");
$redis->connect("/tmp/redis.sock", 0, 6000000);

$logglySender = new ApiLogger("1de48388-ead3-46c5-82c3-af509bd34240");

while(true)
{
	list($channel, $value) = $redis->brPop(['log', 'performance'], 600000);

	if($value)
		processMessage(json_decode($value));

	//$result = json_decode($value);
	//if($result->channel == "log")
	//	$logglySender->send(json_encode($result->data));
}

// fast for web-server, but slow for listener
while(false)
{
	$value = $redis->rPop('log');
	if($value)
		processMessage(json_decode($value));

	$value = $redis->rPop('performance');
	if($value)
		processMessage(json_decode($value));

	sleep(1);
}

/*
$redis->subscribe(['log', 'performance'], 'onMessageReceived');

function onMessageReceived($redis, $channel, $jsonMsg)
{
	//var_dump($jsonMsg);
	$msg = json_decode($jsonMsg);
	processMessage($msg);
}
*/