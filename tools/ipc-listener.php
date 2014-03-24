<?php

include "listener-renderer.php";

$socket_file = "/tmp/logwriter.sock";

// install signal hook
pcntl_signal(SIGHUP, function ($signo) {
	global $socket_file;
	echo "caught hangup signal, exiting..\n";
	unlink($socket_file);
	exit;
});

if(file_exists($socket_file))
	unlink($socket_file);

$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
socket_bind($socket, $socket_file) or die("unable to bind to socket\n");
socket_set_block($socket);
chmod($socket_file, 0666);

// dequeueing decoder service

while(true)
{
	$received = socket_recvfrom($socket, $msg, 50000, 0, $from);
	echo "received $received bytes\n";
	if($received < 1)
		exit;

	$msg = json_decode($msg);

	processMessage($msg);
}
