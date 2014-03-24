<?php namespace Bugvote\Core;

class PerformanceLogRenderer
{
	public static function render($log)
	{
		return self::dump($log);
	}

	protected static function makeNice($span)
	{
		if( $span > 1000 )
			$span = number_format($span / 1000, 2) . " sec";
		else
			$span = number_format($span, 2) . " msec";

		return $span;
	}

	private static $minIndentation = 0;
	protected static $indentation = 0;

	protected static function dump($log)
	{
		// special case: no entries
		if( count($log->entries) == 0 )
		{   // use title to create a single-entry box
			$log->entries []= [ "source" => $log->name, "time" => ($log->last - $log->start) * 1000, "metadata" => null ];
		}

		// scan log to find max-width of source-line
		$maxWidth = 0;
		foreach($log->entries as $entry)
			if(!($entry instanceof PerformanceLog))
				$maxWidth = max($maxWidth, strlen($entry['source']));

		$maxLength = max($maxWidth+12, strlen($log->name) + 1);

		$oldIndent = self::$minIndentation;
		self::$minIndentation = $maxLength = max(self::$minIndentation, $maxLength);

		$line = str_repeat("─", $maxLength);

		$mainSpacing = "";
		for( $i = 0; $i < self::$indentation; $i ++ )
			$mainSpacing .= "│    ";

		$output = "";

		// special case: just one entry
		$compactBox = false;
		if( count($log->entries) == 1 && !($log->entries[0] instanceof PerformanceLog))
		{
			$compactBox = true;
		}

		$output .= $mainSpacing . "│    ┌─{$line}┐\n";
		if( ! $compactBox ) {
			$output .= $mainSpacing . "├────┤ " . str_pad($log->name, $maxLength) . "│\n";
			$output .= $mainSpacing . "│    ├─{$line}┤\n";
		}
		foreach( $log->entries as $entry )
		{
			if( $entry instanceof PerformanceLog )
			{
				//$output .= "│    ┌─{$line}┐";
				//$output .= "├────┤ " . str_pad("Child Entry:", $maxLength) . "│";
				//$output .= "│    ├─{$line}┘";
				self::$indentation ++;
				$output .= self::dump($entry);
				self::$indentation --;

				$output .= $mainSpacing . "│    │ " . str_repeat(" ", $maxLength) . "│\n";
			} else
			{
				$metadata = $entry["metadata"];

				$result = $entry["source"] . ": " . self::makeNice($entry["time"]);
				$padding = $maxLength - strlen($result);
				$padding = $padding > 0 ? str_repeat(' ', $padding) : '';

				// update result with metadata
				if( $metadata != null )
				{
					$metadata = str_replace("\r", "", $metadata);
					$lines = explode("\n", $metadata);
					$metadata = "";
					foreach($lines as $part) {
						$part = preg_replace('/\s{2,}/', '', $part);
						if( $part != "" )
							$metadata .= $part . "\n";
					}

					$result = "<a title=\"$metadata\" rel=\"tooltip\">".$entry["source"]."</a>" . ": " . self::makeNice($entry["time"]);
				}

				if($compactBox)
					$output .= $mainSpacing . "├────┤ " . $result . $padding . "│\n";
				else
					$output .= $mainSpacing . "│    ├ " . $result . $padding . "│\n";
			}
		}

		self::$minIndentation = $oldIndent;

		if(!$compactBox)
		{
			$throughput = "";
			if( $log->bytes != 0 )
			{
				$throughput = "  Throughput: " . toNiceSize($log->bytes / ($log->last - $log->start)) . "/sec";
				$throughput .= "  Size: " . toNiceSize($log->bytes);
			}

			$totalStr = "Total " . self::makeNice(($log->last - $log->start) * 1000) . "$throughput";
			//$maxLength = max(25, strlen($totalStr) + 3);
			//$line = str_repeat("─", $maxLength);

			//$output .= "├─{$line}┐";
			$output .= $mainSpacing . "│    │ <b>" . str_pad($totalStr, $maxLength) . "</b>│\n";
		}
		$output .= $mainSpacing . "│    └─{$line}┘\n";

		return $output;
	}
}