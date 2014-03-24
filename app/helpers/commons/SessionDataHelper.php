<?php namespace Bugvote\Commons;

class SessionDataHelper
{
    protected $unique;

    function __construct($unique)
    {
        $this->unique = $unique;
    }

    protected function getFullKeyName($key)
    {
        return '__lexy_' . $this->unique . '_var_' . $key;
    }

    function __get($key)
    {
        $sessionKey = $this->getFullKeyName($key);

        if(!isset($_SESSION[$sessionKey]))
            return null;

        $value = $_SESSION[$sessionKey];

        //\Log::Write("Fetched: $key = $value");
        return $value;
    }

    function __set($key, $value)
    {
        //\Log::Write("Saving: $key = $value");
        $_SESSION[$this->getFullKeyName($key)] = $value;
    }

    function getOnce($key, $defaultValue = null)
    {
        $sessionKey = $this->getFullKeyName($key);
        if(!isset($_SESSION[$sessionKey]))
            return $defaultValue;

        $value = $_SESSION[$sessionKey];
        unset($_SESSION[$sessionKey]);

        //\Log::Write("Destructive fetch: $key = $value");

        return $value;
    }

	/**
	 * @param $key
	 * @return integer or FALSE if no such key found
	 */
	function getInteger($key)
	{
		$keyPath = $this->getFullKeyName($key);
		if(!isset($_SESSION[$keyPath]))
			return false;

		$value = intval($_SESSION[$keyPath]);

		return $value;
	}
}