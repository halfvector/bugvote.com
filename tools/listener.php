<?php

$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, "localhost", 7001);

$buffer = "";

while(1) {
	socket_recvfrom($socket, $json_msg, 65000, 0, $src_ip, $src_port);

	// check if last byte is the end of a json msg '}'
	if( $json_msg[strlen($json_msg)-1] != '}' )
	{
		$buffer .= $json_msg;
		continue;
	}

	$json_msg = $buffer . $json_msg;
	$buffer = "";

	$msg = json_decode($json_msg);

	if( !isset($msg->source) || !isset($msg->msg))
	{
		echo "bad packet:\n";
		var_dump($json_msg);
		echo "decoded badly:\n";
		var_dump($msg);
	}

	//$prefix = "$msg->time $msg->src $msg->source";
	$prefix = "$msg->source";
	$msg->msg = str_replace("\n", "\n$prefix ", trim($msg->msg, "\r\n"));

	echo "-----------------------------------------------------------------------------------\n";
	echo "$prefix $msg->msg\n";
}
