<?php

include "listener-renderer.php";

// install signal hook
pcntl_signal(SIGHUP, function ($signo) {
	echo "caught hangup signal, exiting..\n";
	exit;
});

$queue = msg_get_queue(5150);

// dequeueing decoder service

while(true)
{
	msg_receive($queue, 1, $msgType, 50000, $msg, false, 0);
	$msg = json_decode($msg);

	processMessage($msg);
}
