<?php

include "listener-renderer.php";

// install signal hook
pcntl_signal(SIGHUP, function ($signo) {
	echo "caught hangup signal, exiting..\n";
	exit;
});

$ctx = new ZMQContext();
$socket = $ctx->getSocket(ZMQ::SOCKET_PULL);
//$socket->setSockOpt(ZMQ::SOCKOPT_HWM, 10);
$socket->bind("tcp://127.0.0.1:5557");

// dequeueing decoder service

while(true)
{
	$json_msg = $socket->recv();
	$msg = json_decode($json_msg);

	processMessage($msg);
}
