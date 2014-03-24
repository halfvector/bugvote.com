<?php namespace Bugvote\Core;

class LazySession
{
	function __get($key) {

		return new SessionSegment($key);

	}
}

class SessionSegment
{
	protected $data;

	function __construct($name) {
		if(!isset($_SESSION[$name]))
			$_SESSION[$name] = [];

		$this->data = &$_SESSION[$name];
	}

	function __get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	function __set($key, $value) {
		$this->data[$key] = $value;
	}

	function __isset($key) {
		return isset($this->data[$key]);
	}

	function __unset($key) {
		unset($this->data[$key]);
	}

	function clear() {
		$this->data = [];
	}

	function setFlash($key, $value) {
		$this->data['__flash'][$key] = $value;
	}

	function getFlash($key) {
		if(isset($this->data['__flash'][$key])) {
			$value = $this->data['__flash'][$key];
			unset($this->data['__flash'][$key]);
			return $value;
		}

		return null;
	}

	function getAllFlashes() {
		return isset($this->data['__flash']) ? $this->data['__flash'] : [];
	}

	function hasFlash($key) {
		return isset($this->data['__flash'][$key]);
	}

	function clearFlash() {
		unset($this->data['__flash']);
	}
}