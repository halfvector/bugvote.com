<?php

include "listener-renderer.php";

// install signal hook
pcntl_signal(SIGHUP, function ($signo) {
	echo "caught hangup signal, exiting..\n";
	exit;
});

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, "127.0.0.1", 5555);

// dequeueing decoder service

while(true)
{
	// grab a packet
	socket_recvfrom($socket, $header, 100000, 0, $src_ip, $src_port);
	$header = json_decode($header);

	// first message should be a size, if not skip until we get one
	if(!isset($header->size))
		continue;

	//echo "header->size: $header->size\n";

	$packet = "";
	while(strlen($packet) < $header->size)
	{
		socket_recvfrom($socket, $msg, 100000, 0, $src_ip, $src_port);
		$packet .= $msg;

		//echo "strlen(packet): ".strlen($packet)."\n";
	}

	$msg = json_decode($packet);

	processMessage($msg);
}
