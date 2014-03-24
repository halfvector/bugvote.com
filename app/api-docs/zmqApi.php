<?php
/**
 * zmq-API v1.0.6 Docs build by DocThor [2013-06-07]
 * @package zmq
 */

/**
 * @package zmq
 */
class ZMQ {
	const SOCKET_PAIR = 0;
	const SOCKET_PUB = 1;
	const SOCKET_SUB = 2;
	const SOCKET_REQ = 3;
	const SOCKET_REP = 4;
	const SOCKET_XREQ = 5;
	const SOCKET_XREP = 6;
	const SOCKET_PUSH = 8;
	const SOCKET_PULL = 7;
	const SOCKET_DEALER = 5;
	const SOCKET_ROUTER = 6;
	const SOCKET_UPSTREAM = 7;
	const SOCKET_DOWNSTREAM = 8;
	const POLL_IN = 1;
	const POLL_OUT = 2;
	const MODE_SNDMORE = 2;
	const MODE_NOBLOCK = 1;
	const MODE_DONTWAIT = 1;
	const DEVICE_FORWARDER = 2;
	const DEVICE_QUEUE = 3;
	const DEVICE_STREAMER = 1;
	const ERR_INTERNAL = -99;
	const ERR_EAGAIN = 11;
	const ERR_ENOTSUP = 95;
	const ERR_EFSM = 156384763;
	const ERR_ETERM = 156384765;
	const LIBZMQ_VER = '2.2.0';
	const SOCKOPT_HWM = 1;
	const SOCKOPT_SWAP = 3;
	const SOCKOPT_AFFINITY = 4;
	const SOCKOPT_IDENTITY = 5;
	const SOCKOPT_RATE = 8;
	const SOCKOPT_RECOVERY_IVL = 9;
	const SOCKOPT_RECOVERY_IVL_MSEC = 20;
	const SOCKOPT_MCAST_LOOP = 10;
	const SOCKOPT_SNDBUF = 11;
	const SOCKOPT_RCVBUF = 12;
	const SOCKOPT_LINGER = 17;
	const SOCKOPT_RECONNECT_IVL = 18;
	const SOCKOPT_RECONNECT_IVL_MAX = 21;
	const SOCKOPT_BACKLOG = 19;
	const SOCKOPT_SUBSCRIBE = 6;
	const SOCKOPT_UNSUBSCRIBE = 7;
	const SOCKOPT_TYPE = 16;
	const SOCKOPT_RCVMORE = 13;
	const SOCKOPT_FD = 14;
	const SOCKOPT_EVENTS = 15;
	const SOCKOPT_SNDTIMEO = 28;
	const SOCKOPT_RCVTIMEO = 27;
}
/**
 * @package zmq
 */
class ZMQContext {
	public function __construct($io_threads="", $persistent="") {}
	public function getsocket($type, $dsn, $on_new_socket="") {}
	public function ispersistent() {}
}
/**
 * @package zmq
 */
class ZMQSocket {
	public function __construct(ZMQContext $ZMQContext, $type, $persistent_id="", $on_new_socket="") {}
	public function send($message, $mode="") {}
	public function recv($mode="") {}
	public function sendmulti($message, $mode="") {}
	public function recvmulti($mode="") {}
	public function bind($dsn, $force="") {}
	public function connect($dsn, $force="") {}
	public function setsockopt($key, $value) {}
	public function getendpoints() {}
	public function getsockettype() {}
	public function ispersistent() {}
	public function getpersistentid() {}
	public function getsockopt($key) {}
	public function sendmsg($message, $mode="") {}
	public function recvmsg($mode="") {}
}
/**
 * @package zmq
 */
class ZMQPoll {
	public function add($entry, $type) {}
	public function poll(&$readable, &$writable, $timeout="") {}
	public function getlasterrors() {}
	public function remove($remove) {}
	public function count() {}
	public function clear() {}
}
/**
 * @package zmq
 */
class ZMQDevice {
	public function __construct($frontend, $backend) {}
	public function run() {}
	public function setidlecallback($idle_callback) {}
	public function setidletimeout($timeout) {}
}
/**
 * @package zmq
 */
class ZMQException extends Exception {
}
/**
 * @package zmq
 */
class ZMQContextException extends ZMQException {
}
/**
 * @package zmq
 */
class ZMQSocketException extends ZMQException {
}
/**
 * @package zmq
 */
class ZMQPollException extends ZMQException {
}
/**
 * @package zmq
 */
class ZMQDeviceException extends ZMQException {
}
