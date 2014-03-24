<?php namespace Bugvote\Core;

use ArrayAccess;
use ArrayObject;

class FormVariables extends ArrayObject implements ArrayAccess
{
	protected $params;

	function __construct($params = []) {
		$this->params = $params;
	}

	function __get($param) {
		$value = isset($this->params[$param]) ? $this->params[$param] : (isset($_POST[$param]) ? $_POST[$param] : null);
		return new RequestVariable($value, $param);
	}

	function __isset($param) {
		return isset($this->params[$param]);
	}

	public function offsetExists($offset)
	{
		return isset($this->params[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->params[$offset];
	}

	public function offsetSet($offset, $value)
	{
	}

	public function offsetUnset($offset)
	{

	}
}
