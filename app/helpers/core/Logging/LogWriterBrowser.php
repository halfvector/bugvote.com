<?php namespace Bugvote\Core\Logging;

class LogWriterBrowser implements ILogWriter
{
    public function Write($channel, $data)
    {
        $this->dumpSessionLogLong($data->entries);
    }

    function dumpSessionLogLong($entries)
    {
	    echo "<pre>";

        foreach($entries as $entry)
        {
            $date = date("Y-m-d H:i:s", $entry->timestamp);
            $source = $entry->file . ":" . $entry->line . " " . $entry->method . ";";

            if($entry->channel)
                $entry->channel = "[$entry->channel]";

            // with a date
            //echo "<code>" . str_pad($source, 40, " ") . " " . $entry->message . " " . $entry->channel . "\n</code><br>\n";
	        echo str_pad($source, 40, " ") . " " . $entry->message . " " . $entry->channel . "\n";
        }

	    echo "</pre>";
    }
}