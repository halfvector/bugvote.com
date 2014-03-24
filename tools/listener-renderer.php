<?php

$numOfLogs = 0;
$numOfTimings = 0;

function processMessage($msg)
{
	global $numOfTimings, $numOfLogs;

	switch($msg->channel)
	{
		case 'performance':
			onPerformanceMsg($msg->data);
			$numOfTimings ++;
			break;

		case 'log':
			onLogMsg($msg->data);
			$numOfLogs ++;
			break;
	}

	//echo "Have $numOfLogs logs and $numOfTimings timings\n";
}

// data handlers

function onPerformanceMsg($msg)
{
	echo "+" . str_repeat('-', 110) . "+\n";
	echo "│ UUID={$msg->session->uuid};host={$msg->session->host};date={$msg->session->date};pid={$msg->session->pid}\n";
	echo "+" . str_repeat('-', 110) . "+\n";

	//var_dump($msg);
	//echo "-----------------------------------------------------------------------------------\n";

	dumpPerformanceTreeStart($msg->entries);
}

function onLogMsg($msg)
{
	echo "\n";
	echo "| UUID={$msg->session->uuid};host={$msg->session->host};date={$msg->session->date};pid={$msg->session->pid}\n";
	//echo "-----------------------------------------------------------------------------------\n";
	echo "└" . str_repeat('─', 110) . "┐\n";

	//var_dump($msg);
	//echo "-----------------------------------------------------------------------------------\n";

	dumpSessionLogLong($msg->entries);
}


// pretty print functions

function dumpSessionLog($entries)
{
	foreach($entries as $entry)
	{
		// could compress the method name by stripping off namespaces
		$entry->method = end(explode("\\", $entry->method));

		$date = date("Y-m-d H:i:s", $entry->timestamp);
		//$source = $entry->method . ";";
		$source = $entry->file . ":" . $entry->line . " ";

		if($entry->channel)
			$entry->channel = "[$entry->channel]";

		// with a date
		//echo "| " . $date . " | " . str_pad($source, 40, " ") . " " . $entry->message . " $entry->channel" . PHP_EOL;

		// without a date
		echo "| " . str_pad($source, 50, " ") . " " . $entry->message . " $entry->channel" . PHP_EOL;
	}
}

function dumpSessionLogLong($entries)
{
	foreach($entries as $entry)
	{
		$date = date("Y-m-d H:i:s", $entry->timestamp);
		$source = $entry->file . ":" . $entry->line . " " . $entry->method . ";";

		if($entry->channel)
			$entry->channel = "[$entry->channel]";

		// with a date
		echo "| " . str_pad($source, 40, " ") . " " . $entry->message . " $entry->channel" . PHP_EOL;
	}
}

function dumpSessionLogFull($entries)
{
	foreach($entries as $entry)
	{
		$date = date("Y-m-d H:i:s", $entry->timestamp);
		$source = $entry->file . ":" . $entry->line . " " . $entry->method . ";";

		if($entry->channel)
			$entry->channel = "[$entry->channel]";

		// with a date
		echo "| " . $date . " | " . str_pad($source, 40, " ") . " " . $entry->message . " $entry->channel" . PHP_EOL;
	}
}

function dumpPerformanceTree($tree, $indent = 1, &$altRow = false)
{
	$indent_space = str_repeat(' ', max(0, $indent-1) * 2);

	$linePadding = 90;
	$paddingCharacter = $altRow ? '.' : ' ';

	$nice_time = number_format($tree->span, 2) . " msec";
	$line = str_pad('+ ' . $tree->label . ' ', $linePadding, $paddingCharacter);
	echo $indent_space . $line . " " . $nice_time . "\n";

	$indent_space = str_repeat(' ', $indent * 2);

	//var_dump($tree);

	foreach($tree->children as $child)
	{
		if(isset($child->label))
		{
			$altRow = ! $altRow;
			dumpPerformanceTree($child, $indent + 1, $altRow);
		}
		else
		{
			// skip empty entries (placeholders for empty groups)
			if($child->meta == "" )
				continue;

			$altRow = ! $altRow;
			$paddingCharacter = $altRow ? '.' : ' ';

			$nice_time = number_format($child->span, 2) . " msec";
			$line = str_pad('- ' . $child->meta . ' ', $linePadding, $paddingCharacter);
			echo $indent_space . $line . " " . $nice_time . "\n";
		}
	}
}

function dumpPerformanceTreeStart($treeRootElements)
{
	$latest = 0;

	foreach($treeRootElements as $items)
	{
		dumpPerformanceTree($items);
		$latest = $items->span + $items->offset;
	}

	echo "> Total: " . number_format($latest, 2) . " msec\n";
}