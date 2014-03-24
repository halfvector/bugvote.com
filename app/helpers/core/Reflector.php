<?php

namespace Bugvote\Core;

class Reflector
{
	/**
	 * an empty metadata object, for when reflection is not wanted. mostly for testing/timing purposes.
	 * @param int $pop ignored
	 * @return Reflector\FrameMetadata
	 */
	public static function GetEmptyFrameMetadata($pop = 1)
	{
		$metadata = new Reflector\FrameMetadata();
		return $metadata;
	}

	/**
	 * @param int $pop number of frames to rewind into the callstack
	 * @return Reflector\FrameMetadata
	 */
	public static function GetFrameMetadata($pop = 1)
	{
		$metadata = new Reflector\FrameMetadata();

		// i need 2 frames to be left after transparent functions (logging, etc) are popped off the stack.
		$btMinSize = $pop + 2;
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $btMinSize);

		if(count($backtrace) < $btMinSize)
		{	// error in logging-related code, can't really log that easily
			error_log("Reflector::GetFrameMetadata(): backtrace is less than $btMinSize frames");
			return $metadata;
		}

		for($i = 0; $i < $pop; $i ++)
			array_shift($backtrace);

		// this frame
		$callerFrame = array_shift($backtrace);
		// previous frame -- contains relevant context info
		$parentFrame = array_shift($backtrace);

		// strip root from file path
		//$file_line = str_replace(LEXY_ROOT_PATH, "", $this_frame["file"]) . ":" . $this_frame["line"];

		$metadata->file = $callerFrame['file'];
		$metadata->line = $callerFrame['line'];

		if( isset( $parentFrame["class"] ) )
			$metadata->method = $parentFrame["class"] . $parentFrame["type"] . $parentFrame["function"]; // method call with type (static or instance)
		else
			$metadata->method = $parentFrame["function"]; // function call

		$metadata->method = str_replace(array("include", "require", "include_once". "require_once"), "", $metadata->method);
		if( strlen($metadata->method) > 2 )
			$metadata->method .= "()";

		return $metadata;
	}

	// 0.025 msec for going 2 frames deep
	public static function GetSimpleCallerMetadata($pop = 1)
	{
		$btMinSize = $pop + 1;
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $btMinSize);

		if(count($backtrace) < $btMinSize)
		{	// error in logging-related code, can't really log that easily
			error_log("Reflector::GetFrameMetadata(): backtrace is less than $btMinSize frames");
			return "(unknown)";
		}

		// previous frame -- contains relevant context info
		$parentFrame = $backtrace[$pop];

		$method = isset($parentFrame["class"]) ? $parentFrame["class"] . $parentFrame["type"] . $parentFrame["function"] : $parentFrame["function"];

		if( strlen($method) > 2 )
			$method .= "()";

		return $method;
	}

	public static function GetStacktrace($pop = 1)
	{
		$btMinSize = $pop + 1;
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

		$metadata = new Reflector\FrameMetadata();

		if(count($backtrace) < $btMinSize)
		{	// error in logging-related code, can't really log that easily
			error_log("Reflector::GetFrameMetadata(): backtrace is less than $btMinSize frames");
			return $metadata;
		}

		for($i = 0; $i < $pop; $i ++)
			array_shift($backtrace);

		dump($backtrace);

		return $backtrace;
	}
}