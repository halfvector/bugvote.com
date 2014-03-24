<?php namespace Bugvote\Core;

use ArrayAccess;
use ArrayObject;
use Michelf\MarkdownExtra;

class RequestVariable
{
	protected $value;
	protected $name;

	public function __construct($value, $name) {
		$this->value = $value;
		$this->name = $name;
	}

	function __toString() {
		return filter_var($this->value, FILTER_SANITIZE_STRING, 0);
	}

	function toInteger() {
		return filter_var($this->value, FILTER_SANITIZE_NUMBER_INT);
	}

	function asInt() {
		return filter_var($this->value, FILTER_SANITIZE_NUMBER_INT);
	}

	function asString() {
		return filter_var($this->value, FILTER_SANITIZE_STRING, 0);
	}

	function asArray() {
		if(!is_array($this->value) || empty($this->value))
			return [];
		return $this->value;
	}

	function formatString() {
		$string = filter_var($this->value, FILTER_SANITIZE_STRING, 0);
		return MarkdownExtra::defaultTransform($string);
	}

	function minLength($length) {
		if(!strlen($this->value))
			throw new StringTooShort("String {$this->name} is too short");

		return $this;
	}

	function decodeId() {
		$encoded = filter_var($this->value, FILTER_SANITIZE_STRING, 0);
		return base_convert($encoded, 36, 10) - 10000;
	}
}

class RequestVariables extends ArrayObject implements ArrayAccess
{
	protected $params;

	function __construct($params) {
		$this->params = $params;
	}

	function __get($param) {
		$value = isset($this->params[$param]) ? $this->params[$param] : (isset($_GET[$param]) ? $_GET[$param] : null);
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
		if(isset($this->params[$offset]))
			return $this->params[$offset];
		return null;
	}

	public function offsetSet($offset, $value)
	{
	}

	public function offsetUnset($offset)
	{

	}
}

class IntegerFilter
{
	/** @var \Klein\Request */
	protected $params;

	public function __construct($params) {
		$this->params = $params;
	}

	public function __get($param) {
		$var = isset($this->params[$param]) ? $this->params[$param] : (isset($_GET[$param]) ? $_GET[$param] : null);
		return filter_var($var, FILTER_SANITIZE_NUMBER_INT);
	}
}

class StringFilter
{
	protected $params;

	public function __construct($params) {
		$this->params = $params;
	}

	/**
	 * strips and escapes everything. the default approach to strings: paranoid.
	 * if you want to do something special (allow html, markdown, keep newlines, etc), do it explicitly.
	 * @param $param
	 * @return mixed
	 */
	public function __get($param) {
		$var = isset($this->params[$param]) ? $this->params[$param] : (isset($_GET[$param]) ? $_GET[$param] : null);
		return filter_var($var, FILTER_SANITIZE_STRING, 0);
	}
}

class RawFilter
{
	/** @var \Klein\Request */
	protected $request;

	/** @param $request \Klein\Request */
	public function __construct($request) {
		$this->request = $request;
	}

	public function __get($param) {
		return filter_var($this->request->param($param), FILTER_UNSAFE_RAW, 0);
	}
}

class EncodedIdFilter
{
	/** @var \Klein\Request */
	protected $request;

	/** @param $request \Klein\Request */
	public function __construct($request) {
		$this->request = $request;
	}

	/**
	 * @param $param string name of the parameter
	 * @return int representing decoded raw id
	 */
	public function __get($param) {
		$encoded = filter_var($this->request->param($param), FILTER_SANITIZE_STRING, 0);
		return base_convert($encoded, 36, 10) - 10000;
	}
}