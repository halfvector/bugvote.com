<?php namespace Bugvote\Core;

class Dropdown
{
	public  $name;
	public  $value;
	private $options;

	public function __construct($name, array $options, $value)
	{
		$this->name    = $name;
		$this->options = $options;
		$this->value   = $value;
	}

	public function options()
	{
		$values = [];

		foreach($this->options as $key => $value)
		{
			$values [] = ['value' => $key, 'display' => $value, 'selected' => $key == $this->value];
		}

		/*
		$values = array_map(function($k, $v) use ($value) {
			return array(
				'value'    => $k,
				'display'  => $v,
				'selected' => ($value === $k),
			);
    	}, $this->options);
		*/

		return $values;
	}
}