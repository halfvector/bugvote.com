<?php namespace Bugvote\Core;

use Bugvote\Core\DAL\FluentDAL;
use Bugvote\Core\Logging\ILogger;
use Bugvote\Core\Logging\IPerformanceLog;
use Exception;
use PDO;
use PDOException;
use Timer;

// from http://www.yiiframework.com/wiki/38/how-to-use-nested-db-transactions-mysql-5-postgresql/
class NestedPDO extends PDO
{
	// Database drivers that support SAVEPOINTs.
	protected static $savepointTransactions = array("pgsql", "mysql");

	// The current transaction level.
	protected $transLevel = 0;

	protected function nestable() {
		return in_array($this->getAttribute(PDO::ATTR_DRIVER_NAME),
			self::$savepointTransactions);
	}

	public function beginTransaction() {
		if($this->transLevel == 0 || !$this->nestable()) {
			parent::beginTransaction();
		} else {
			$this->exec("SAVEPOINT LEVEL{$this->transLevel}");
		}

		$this->transLevel++;
	}

	public function commit() {
		$this->transLevel--;

		if($this->transLevel == 0 || !$this->nestable()) {
			parent::commit();
		} else {
			$this->exec("RELEASE SAVEPOINT LEVEL{$this->transLevel}");
		}
	}

	public function rollBack() {
		$this->transLevel--;

		if($this->transLevel == 0 || !$this->nestable()) {
			parent::rollBack();
		} else {
			$this->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transLevel}");
		}
	}
}

class DAL
{
	/** @var PDO */ private $pdo;
	/** @var IPerformanceLog */ private $perf;
	/** @var ILogger */ private $log;
	/** @var ObjectCache */ private $cache;

	private $connectingTimeMsec = 0;
	private $settings;

	/**
	 * @param $settings
	 * @param ILogger $logger
	 * @param IPerformanceLog $perf
	 * @internal param \Bugvote\Core\IAudit $audit
	 */
	public function __construct($settings, $logger, $perf)
	{
		$this->perf = $perf;
		$this->log = $logger;
		$this->settings = $settings;
		$this->cache = null;//new ObjectCache($audit);
	}

	public function getConnectionStatus()
	{
		if( $this->pdo == null )
			return false;
		return $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
	}

	public function isConnectionPersistent()
	{
		if( $this->pdo == null )
			return false;
		return $this->pdo->getAttribute(PDO::ATTR_PERSISTENT);
	}

	public function getConnectingTimeMsec()
	{
		return $this->connectingTimeMsec;
	}

	public function connect()
	{
		$settings = $this->settings;
		$p = $this->perf->start("DAL DB Connect");

		$this->pdo = new NestedPDO(
			"mysql:unix_socket={$settings->socket};dbname={$settings->catalog};charset=utf8",
			$settings->username, $settings->password, [PDO::ATTR_PERSISTENT => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
		);

		//$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		//$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$p->stop();
	}

	public function beginTransaction()
	{
		try {
			$pdo = $this->getPDO();
			return $pdo->beginTransaction();
		}
		catch(PDOException $err) {
			$this->log && $this->log->write("SqlError: {$err->getFile()}:{$err->getLine()} " . $err->getMessage());
		}
	}

	public function commitTransaction()
	{
		try {
			$pdo = $this->getPDO();
			return $pdo->commit();
		}
		catch(PDOException $err) {
			$this->log && $this->log->write("SqlError: {$err->getFile()}:{$err->getLine()} " . $err->getMessage());
		}
	}

	public function rollbackTransaction()
	{
		try {
			$pdo = $this->getPDO();
			return $pdo->rollback();
		}
		catch(PDOException $err) {
			$this->log && $this->log->write("SqlError: {$err->getFile()}:{$err->getLine()} " . $err->getMessage());
		}
	}

	public function getPDO()
	{
		if( $this->pdo == null )
			$this->connect();

		return $this->pdo;
	}

	public $suppressExceptions = false;

	/**
	 * @param $label string
	 * @param $function callable
	 * @param $queryFormat string
	 * @param array $parameters
	 * @return bool|array
	 * @throws Exception|PDOException
	 */
	public function safeOperationWrapper($label, $function, $queryFormat, $parameters = array())
	{
		$attempts = 0;
		$retry = true;
		$autoId = false;

		$p = $this->perf->start("DAL Query [$label]");

		// this logging is pretty handy, but can spam the console log-viewer
		//$p->setMetadata(['query' => $queryFormat, 'parameters' => $parameters]);
		//$this->log->writeObject("query: $queryFormat", $parameters);

		while( $attempts++ <3 && $retry )
		{
			try
			{
				$autoId = $this->$function($queryFormat, $parameters);
				$retry = false;
			}
			catch(PDOException $err) {
				// the dumb error looks like this
				// SQLSTATE[HY000]: General error: 2006 MySQL server has gone away

				// if we are inside a transaction when this happens.. that's a serious problem.

				if($err->getCode() == "HY000" && !strcmp($err->getMessage(), "MySQL server has gone away"))
				{   // caught a special possibly-recoverable error, involving mysql connection dropping
					$this->log && $this->log->writeObject("Caught SQL Error:",
						[
							"Query" => $queryFormat,
							"Parameters" => $parameters,
							"Error" => $err->getMessage(),
							"Location" => "{$err->getFile()}:{$err->getLine()}",
							"Stacktrace" => $err->getTraceAsString()
						]
					);
					$this->log && $this->log->write("MySQL Connection Timed out. Reconnecting and retrying query ($attempts/3)..");
					usleep(3000); // sleep a few milliseconds and try again
					$this->connect();
				} else
				{   // caught another type of error
					if(!strcmp($err->getMessage(), "Integrity constraint violation"))
					{
						//var_dump($queryFormat);
						//var_dump($parameters);
					}

					$this->log && $this->log->writeObject("Caught SQL Error:",
						[
							"Query" => $queryFormat,
							"Parameters" => $parameters,
							"Error" => $err->getMessage(),
							"Location" => "{$err->getFile()}:{$err->getLine()}",
							"Stacktrace" => $err->getTraceAsString()
						]
					);

					$p->stop();

					// optionally rethrow exception (happens most of the time)
					if(!$this->suppressExceptions)
						throw $err;

					return false;
				}
			}
		}

		$p->stop();

		/*
		if(false)
		{
			$after = mysqli_get_client_stats();

			$bytes = $after['bytes_received'] - $before['bytes_received'];
			$packets = $after['packets_received'] - $before['packets_received'];
			$noIndex = $after['no_index_used'] - $before['no_index_used'];
			$badIndex = $after['bad_index_used'] - $before['bad_index_used'];

			$msg = "Query: $queryFormat\n"
				. "Received $bytes bytes in $packets packets (No Index: $noIndex / Bad Index: $badIndex) in " . number_format($queryTime,2) . " msec\n"
				. print_r($parameters, true) . "\n" . print_r($autoId, true);

			$this->log && $this->log->write($msg);
		}
		*/

		return $autoId;
	}

	/////////////////////////////////////////////////
	// experimental

	// select(* from table a where, ["userId" => $userId])
	public function selectOne($table, $array)
	{
		$set = "";
		$values = [];
		$bindings = [];

		foreach($array as $key => $value) {
			$bindings["$key"] = ":$key";
			$values[":$key"] = $value;
		}

		//$set = implode(" and ", array_keys($array));
		$set = rawurldecode(http_build_query($bindings, '', " and "));

		foreach($array as $key => $value)
			$values[":$key"] = $value;


		$results = $this->fetchSingleObj(
			"select $table " . $set, $values
		);

		return $results;
	}

	protected function associativeArrayToBindingsAndValues($array, &$bindingsString, &$values)
	{
		$values = [];
		$bindings = [];

		foreach($array as $key => $value) {
			$bindings["$key"] = ":$key";
			$values[":$key"] = $value;
		}

		$bindingsString = rawurldecode(http_build_query($bindings, '', " and "));
	}

	public function select1($table, $label = null)
	{
		return new FluentDAL($this, $table, "select1", $label);
	}

	public function selectMany($table, $label = null)
	{
		return new FluentDAL($this, $table, "selectMany", $label);
	}

	public function update($table, $label = null)
	{
		return new FluentDAL($this, $table, "update", $label);
	}

	public function insert($table, $label = null)
	{
		return new FluentDAL($this, $table, "insert", $label);
	}

	public function replace($table, $label = null)
	{
		return new FluentDAL($this, $table, "replace", $label);
	}

	/////////////////////////////////////////////////
	// the usual CRUD methods

	public function insertSingleObj($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("insert.1 $label", 'insertSingleObjImpl', $queryFormat, $parameters);
	}
	protected function insertSingleObjImpl($queryFormat, $parameters = array())
	{
		$pdo = $this->getPDO();
		$query = $pdo->prepare($queryFormat);
		$query->execute($parameters);
		return $pdo->lastInsertId();
	}

	public function updateSingleObj($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("update.1 $label", 'updateSingleObjImpl', $queryFormat, $parameters);
	}
	protected function updateSingleObjImpl($queryFormat, $parameters)
	{
		$pdo = $this->getPDO();
		$query = $pdo->prepare($queryFormat);
		$query->execute($parameters);
		return $pdo->lastInsertId();
	}

	public function deleteSingleObj($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("delete.1 $label", 'deleteSingleObjImpl', $queryFormat, $parameters);
	}
	// return false on error
	protected function deleteSingleObjImpl($queryFormat, $parameters)
	{
		$pdo = $this->getPDO();
		$query = $pdo->prepare($queryFormat);
		$query->execute($parameters);

		return $pdo->lastInsertId();
	}

	// returns a database compatible iso-8601 type datetime string without timezone attached
	public function getCurrentDateTimeIso8601()
	{
		return gmdate('Y-m-d H:i:s');
	}

	public function fetchSingleRow($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("select.1 $label", 'fetchSingleRowImpl', $queryFormat, $parameters);
	}
	// return false on error
	protected function fetchSingleRowImpl($queryFormat, $parameters = array())
	{
		$query = $this->getPDO()->prepare($queryFormat);
		$query->execute($parameters);

		return $query->fetch(PDO::FETCH_NUM);
	}

	public function fetchSingleObj($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("select.1 $label", 'fetchSingleObjImpl', $queryFormat, $parameters);
	}
	// return false on error
	protected function fetchSingleObjImpl($queryFormat, $parameters = array())
	{
		$query = $this->getPDO()->prepare($queryFormat);
		$query->execute($parameters);

		return $query->fetch(PDO::FETCH_OBJ);
	}

	public function fetchSingleValue($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("select.1 $label", 'fetchSingleValueImpl', $queryFormat, $parameters);
	}
	// returns the first column of the first row or null
	protected function fetchSingleValueImpl($queryFormat, $parameters = array())
	{
		$query = $this->getPDO()->prepare($queryFormat);
		$query->execute($parameters);
		return $query->fetchColumn(0);
	}

	public function fetchMultipleObjs($queryFormat, $parameters = array(), $label = "(unlabeled)") {
		return $this->safeOperationWrapper("select.m $label", 'fetchMultipleObjsImpl', $queryFormat, $parameters);
	}

	protected function fetchMultipleObjsImpl($queryFormat, $parameters = array())
	{
		$query = $this->getPDO()->prepare($queryFormat); // free
		$query->execute($parameters); // expensive
		$obj = $query->fetchAll(PDO::FETCH_OBJ); // free
		return $obj;
	}
}