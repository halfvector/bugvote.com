<?php namespace Bugvote\Core;

class AuditFacade
{
	/** @var IAudit */ protected $auditing = false;

	/** @param $audit IAudit */
	public function __construct($audit) {
		$this->auditing = $audit;
	}

	public function startTimer($name)
	{
		if(!($perf = $this->auditing->getTimer()))
			return null;

		return $perf->create($name);
	}

	public function markTiming($name)
	{
		if(!($perf = $this->auditing->getTimer()))
			return null;

		return $perf->mark($name);
	}

	public function log($message, $object = null, $pop = 1)
	{
		if(!($log = $this->auditing->getLogger()))
			return false;

		if($object !== null)
			$log->writeObject($message, $object, $pop+1);
		else
			$log->write($message, "", $pop+1);

		return true;
	}
}