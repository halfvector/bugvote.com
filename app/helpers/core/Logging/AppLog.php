<?php namespace Bugvote\Core\Logging;

// an entire log of everything relevant from the application
// this is like a playout script that lets us rewind and monitor stuff
// it must handle:
// * debug information
// ** routing
// ** database queries
// ** performance timing
// * analytics
// ** user activity
// *** logged in
// *** logged out
// *** viewed app
// *** viewed idea
// *** searched for idea
// *** submitted idea
// *** voted on idea
// *** commented on idea
// *** posted dev update
// *** commented on dev update
// *** edited idea
// *** edited dev update
// *** updated profile

use Pheanstalk_Pheanstalk;

/*
 * log types
 * lexy.debug
 * lexy.error
 * lexy.warning
 * lexy.activity
 * heap.identity
 * heap.tracking
 * librato.
 */

class AppLogEntry
{
	function __construct($type, $data)
	{
		$this->entryType = $type;
		$this->data = $data;
	}

	public $entryType;
	public $data;
}

class ApplicationLog
{
	protected $log = [];

	function write($type, $data)
	{
		$this->log []= new AppLogEntry($type, $data);
	}

	// send it over to a local daemon to handle and process the data out of core
	function commit()
	{
		// encode log
		$data = json_encode($this->log);

		// blazing fast (1.5ms) once warm
		$pheanstalk = new Pheanstalk_Pheanstalk('127.0.0.1');
		$pheanstalk
			->useTube('bugvote.success')
			->put($data);
	}
}
