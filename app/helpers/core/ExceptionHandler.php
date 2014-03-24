<?php namespace Bugvote\Core;

use Exception;

interface IHandleException
{
	function HandleIt();
}

class ExceptionHandler
{
	public function handle($that, $msg, $type, Exception $err)
	{
		if($err instanceof IHandleException)
		{
			$err->HandleIt();
		} else
		{   // exception can't handle itself
			echo "** Completely unhandled exception **<br>\n";
			var_dump($err->getMessage());
		}
	}
}