<?php namespace Bugvote\Core\DAL;

use Bugvote\Core\DAL;

class FluentDAL
{
	protected $action = false;
	protected $table = false;
	protected $what = [];
	protected $where = [];
	protected $label = null;

	/** @var DAL */ protected $dal;

	public function __construct($dal, $table, $action, $label = null)
	{
		$this->table = $table;
		$this->action = $action;
		$this->dal = $dal;
		$this->label = $label;
	}

	public function set($array)
	{
		$this->what = $array;

		if($this->action == "insert" || $this->action == "replace")
			return $this->execute();

		return $this;
	}

	public function where($array = [])
	{
		$this->where = $array;
		return $this->execute();
	}

	public function execute()
	{
		if( $this->action == "insert" )
		{
			$this->associativeArrayToBindingsAndValues($this->what, $whatList, $whatValues, ", ");
			return $this->dal->insertSingleObj('insert into ' . $this->table . ' set ' . $whatList, $whatValues, $this->label);
		}

		if( $this->action == "replace" )
		{
			$this->associativeArrayToBindingsAndValues($this->what, $whatList, $whatValues, ", ");
			return $this->dal->insertSingleObj('replace into ' . $this->table . ' set ' . $whatList, $whatValues, $this->label);
		}

		// comma separated list
		$this->associativeArrayToBindingsAndValues($this->what, $whatList, $whatValues, ", ");

		// inclusive AND where
		$this->associativeArrayToBindingsAndValues($this->where, $whereList, $whereValues);


		$values = array_merge($whatValues, $whereValues);


//		\Log::WriteObject('what list', $whatList);
//		\Log::WriteObject('where list', $whereList);
//		\Log::WriteObject('values', $values);

		if( $this->action == "update" )
		{
			return $this->dal->updateSingleObj('update ' . "$this->table set $whatList where $whereList", $values, $this->label);
		}

		if( $this->action == "select1" )
		{
			return $this->dal->fetchSingleObj('select '. "$this->table where $whereList", $values, $this->label);
		}

		if( $this->action == "selectMany" )
		{
			//dump("select $this->table where $whereList");
			if(!empty($whereList))
				$whereList = " where $whereList";
			else
				$whereList = "";
			return $this->dal->fetchMultipleObjs('select ' . $this->table . $whereList, $values, $this->label);
		}
	}

	// supports arrays for "in (1,2,3)" format :D
	protected function associativeArrayToBindingsAndValues($array, &$bindingsString, &$values, $separator = " and ")
	{
		$values = [];
		$bindingsString = "";

		foreach($array as $key => $value) {

			if( $bindingsString != "" )
				$bindingsString .= $separator;

			if( is_array($value) )
			{
				//$bindingsString .= "$key in (:$key)";
				//$values[":$key"] = implode(",", $value);
				$i = 0;
				$keys = [];
				foreach($value as $id)
				{
					$keys []= ":key_$i";
					$values[":key_$i"] = $id;
					$i++;
				}
				$bindingsString .= "$key in (" . implode(',', $keys) .")";

				//$values[":$key"] = $value;
			} else
			{
				$bindingsString .= "$key = :$key";
				$values[":$key"] = $value;
			}
		}
	}
}
