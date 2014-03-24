<?php namespace Bugvote\Core\Debug;

class DebugDump
{
	function dump($log, $perf)
	{
		//if(!headers_sent())
		//  header("Content-Type: text/plain; charset=utf8");

		$trace = "";
		$trace .= "  UUID={$log->session->uuid};host={$log->session->host};date={$log->session->date};pid={$log->session->pid}\n";
		$trace .= str_repeat('-', 110) . "\n";

		foreach($log->entries as $entry)
		{
			$date = date("Y-m-d H:i:s", $entry->timestamp);
			$source = $entry->file . ":" . $entry->line;

			if($entry->channel)
				$entry->channel = "[$entry->channel]";

			if(strstr($entry->method, "->__construct()"))
				$entry->method = "new " . str_replace("->__construct()", "()", $entry->method);

			// with a date
			$trace .= "<span class=\"file\">$source</span> <span class=\"method\">$entry->method</span> " . $entry->message . " $entry->channel" . PHP_EOL;
		}

		$perf_trace = "";
		$perf_trace .= "  UUID={$log->session->uuid};host={$log->session->host};date={$log->session->date};pid={$log->session->pid}\n";
		$perf_trace .= str_repeat('-', 110) . "\n";
		$perf_trace .= $this->dumpPerformanceTree($perf);

		$trace = trim($trace);
		$perf_trace = trim($perf_trace);

		if(false)
		echo <<<EOT
<div class="bootstrap">
	<div class="panel-group" id="accordion">
	  <div class="panel panel-default">
	    <div class="panel-heading">
	      <h4 class="panel-title">
	        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne">
	          Trace Log
	        </a>
	      </h4>
	    </div>
	    <div id="collapseOne" class="panel-collapse collapse">
	      <div class="panel-body">
			<div class="debug-log">$trace</div>
	      </div>
	    </div>
	  </div>
	  <div class="panel panel-default">
	    <div class="panel-heading">
	      <h4 class="panel-title">
	        <a data-toggle="collapse" data-parent="#accordion" href="#collapseTwo">
	          Performance
	        </a>
	      </h4>
	    </div>
	    <div id="collapseTwo" class="panel-collapse collapse">
	      <div class="panel-body">
			<div class="debug-log">$perf_trace</div>
	      </div>
	    </div>
	  </div>
	</div>
</div>
EOT;

		echo <<<EOT
<div class="debug-container">
	<div class="debug-log">$trace</div>
	<div class="debug-log">$perf_trace</div>
</div>
EOT;

		echo <<<EOT
<script type="text/javascript">
	$('[rel=tooltip]').tooltip();
</script>
EOT;
	}

	protected function dumpPerformanceTree($tree, $indent = 1, &$altRow = false)
	{
		$output = "";

		$indent_space = str_repeat(' ', max(0, $indent-1) * 2);

		$total = ($tree->last - $tree->start) * 1000;

		$linePadding = 90;
		$paddingCharacter = $altRow ? '.' : ' ';

		//$nice_time = number_format($total, 2) . " msec";
		//$line = str_pad('+ ' . $tree->name . ' ', $linePadding, $paddingCharacter);
		//$output .= $indent_space . $line . " " . $nice_time . "\n";

		$output .= $this->dumpLine($indent_space, $total, $tree->name, $altRow, true);

		$indent_space = str_repeat(' ', $indent * 2);

		foreach($tree->children as $child)
		{
			if(isset($child->name))
			{
				$altRow = ! $altRow;
				$output .= $this->dumpPerformanceTree($child, $indent + 1, $altRow);
			}
			else
			{
				// skip empty entries (placeholders for empty groups)
				if($child->source == "" )
					continue;

				$altRow = ! $altRow;
				$output .= $this->dumpLine($indent_space, $child->time, $child->source, $altRow);
			}
		}

		return $output;
	}

	function dumpLine($indent_space, $time, $source, $altRow, $isParent = false)
	{
		$output = "";
		$linePadding = 90;

		$parentIndicator = $isParent ? '+' : '-';

		$nice_time = number_format($time, 2) . " msec";
		$line = str_pad($parentIndicator . ' ' . $source . ' ', $linePadding, ' ');

		// highlight slow leaf-nodes
		if(!$isParent && $time > 1)
		{
			$nice_time = "<span class=\"slow-time\">$nice_time</span>";
			$line = "<span class=\"slow\">$line</span>";
		}

		if($altRow)
			$output .= $indent_space . "<span class=\"alt\">" . $line . " " . $nice_time . "</span>\n";
		else
			$output .= $indent_space . $line . " " . $nice_time . "\n";

		return $output;
	}
}