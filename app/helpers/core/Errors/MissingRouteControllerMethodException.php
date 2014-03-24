<?php namespace Bugvote\Core\Errors;

use Bugvote\Core\IHandleException;
use Exception;

class MissingRouteControllerMethodException extends Exception implements IHandleException
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
		echo "<code><strong>$this->class</strong> is missing the <strong>$this->method()</strong> method needed to handle <strong>$this->route</strong> route</code>";
	}
}