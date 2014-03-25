<?php namespace Bugvote\Core\Errors;

use Bugvote\Core\IHandleException;
use Exception;

class MissingRouteControllerException extends Exception implements IHandleException
{
	function __construct($route, $class, $method, $message)
	{
		$this->message = $message;
		$this->route = $route;
		$this->class = $class;
		$this->method = $method;
	}

	function HandleIt()
	{
		//var_dump($this->message);
		echo "<code>Hey you, go create a <strong>$this->class</strong> controller to handle this <strong>$this->route</strong> route</code>";
	}
}