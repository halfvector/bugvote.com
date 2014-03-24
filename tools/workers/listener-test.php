<?php

$ctx = new ZMQContext();
$socket = $ctx->getSocket(ZMQ::SOCKET_PULL);
//$socket->setSockOpt(ZMQ::SOCKOPT_SUBSCRIBE, 'Log');
$socket->bind("tcp://127.0.0.1:5557");
//$socket->bind("ipc://../../icanhaserror-logging.ipc");

while(true)
{
	//var_dump($socket->recv());

	$json_msg = $socket->recv();
	$msg = json_decode($json_msg);

	/*
	if( !isset($msg->source) || !isset($msg->msg))
	{
		echo "bad packet:\n";
		var_dump($json_msg);
		echo "decoded badly:\n";
		var_dump($msg);
	}
	*/

	//$prefix = "$msg->time $msg->src $msg->source";
	//$prefix = "$msg->source";
	//$msg->msg = str_replace("\n", "\n$prefix ", trim($msg->msg, "\r\n"));

	//echo "-----------------------------------------------------------------------------------\n";
	//echo "$msg->session $msg->source $msg->msg\n";
	var_dump($msg);

	echo "-----------------------------------------------------------------------------------\n";
	echo "unique session id: $msg->uuid\n";

	dumpPerformanceTree($msg->trace);
}


function dumpPerformanceTree($tree, $indent = 0)
{
	$indent_space = str_repeat(' ', max(0, $indent-1) * 2);

	if(!$indent)
		echo "[" . $tree->name . "]\n";
	else
	{
		echo $indent_space . "+ " . $tree->name . "\n";
	}

	$indent_space = str_repeat(' ', $indent * 2);

	foreach($tree->children as $child)
	{
		if(isset($child->name))
			dumpPerformanceTree($child, $indent + 1);
		else
		{
			$nice_time = number_format($child->time, 2) . " msec";
			$line = str_pad('- ' . $child->source, 50, ' ');
			echo $indent_space . $line . " " . $nice_time . "\n";
		}
	}
}