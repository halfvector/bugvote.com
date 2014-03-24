<?php namespace Bugvote\Lib;

use Symfony\Component\ClassLoader\ApcClassLoader;

/** @var $appAutoLoader NiceAppAutoloader */
$appAutoloader = false;
/** @var $vendorAutoloader ApcClassLoader */
$vendorAutoloader = false;

class BugvoteAutoload
{
	function __construct()
	{
	}

	function register($paths)
	{
		global $appAutoloader, $vendorAutoloader;

		// our app loader with a cache-prefix
		$appAutoloader = new ApcNiceAppAutoloader('Bugvote/Autoload/');
		//$appAutoloader = new NiceAppAutoloader();

		foreach($paths as $pathEntry)
		{
			list($namespace, $path) = $pathEntry;
			$appAutoloader->add($namespace, $path);
		}

		$appAutoloader->register();

		// third-party vendor loader (using apc cache) -- autoloader is registered in autoload.php
		//$composerLoader = include "$rootDir/vendor/autoload.php";

		// wrap both autoloaders with APC-cachers
		//$vendorAutoloader = new ApcClassLoader('AutoloadVendor_', $composerLoader);
		//$vendorAutoloader->register();
		//$composerLoader->unregister();
	}
}


class NiceAppAutoloader
{
	protected $paths = [];

	public function add($prefix, $paths)
	{
		$this->paths[$prefix] = (array) $paths;
	}

	public function register($prepend = false)
	{
		spl_autoload_register(array($this, 'loadClass'), true, $prepend);
	}

	public function unregister()
	{
		spl_autoload_unregister(array($this, 'loadClass'));
	}

	public function loadClass($class)
	{
		//$start = microtime(true);
		if ($file = $this->findFile($class))
		{
			//$span = (microtime(true) - $start) * 1000;
			//echo "<pre>loadClass scan took $span msec for $file\n</pre>";

			//$start = microtime(true);
			include $file;

			//$span = (microtime(true) - $start) * 1000;
			//echo "<pre>loadClass include took $span msec for $file\n</pre>";

			return true;
		}

		return false;
	}

	public function findFile($class)
	{
		return $this->findFileLookup($class);
	}

	// just 20% faster than baseline
	protected function findFileLookup($class)
	{
		if (false !== $pos = strrpos($class, '\\')) {
			// namespaced class name
			//$classPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 0, $pos)) . DIRECTORY_SEPARATOR;
			//$className = substr($class, $pos + 1);

			// TODO: optimize this path search using partial hashing
			// AppTogether\Core\ImageProvider ->
			// AppTogether
			// AppTogether\Core
			// grab all the namespace parts:
			// $parts = explode $class into [AppTogether, AppTogether\Core]
			// then go backwards from the longest path to the shortest
			// to find the MOST-fitting prefix
			// if(isset($this->paths[$parts[0]]))
			// this should minimize amount of file_exists and other heavy system-calls

			// all this works makes it just 20% faster
			// the file_exists() call is probably optimized away well enough without my help
			$namespace = substr($class, 0, $pos);
			$namespace = trim($namespace, '\\');
			$pathFragments = explode('\\', $namespace);
			$possiblePrefixes = [];
			$pathBuilder = "";
			foreach($pathFragments as $fragment)
			{
				$pathBuilder .= '\\' . $fragment;
				$possiblePrefixes []= $pathBuilder;
			}
			$possiblePrefixes = array_reverse($possiblePrefixes);

			//var_dump($possiblePrefixes);
			//var_dump($this->paths);

			foreach($possiblePrefixes as $possiblePrefix)
			{
				if(isset($this->paths[$possiblePrefix]))
				{
					foreach($this->paths[$possiblePrefix] as $path)
					{   // for each possible folder

						// normalize paths
						$path = rtrim($path, DIRECTORY_SEPARATOR);
						$prefix = trim($possiblePrefix, '\\') . '\\';

						// get the partial namespace/class path trimming the prefix portion
						$partialNamespace = str_replace($prefix, '', $class) . '.php';
						$partialPath = str_replace('\\', DIRECTORY_SEPARATOR, $partialNamespace);
						$possibleFilePath = $path . DIRECTORY_SEPARATOR . $partialPath;

						//var_dump("search: $class -> $possibleFilePath");

						if(file_exists($possibleFilePath))
							return $possibleFilePath;
					}
				}
			}
		}

		return false;
	}

	// baseline
	protected function findFileScanner($class)
	{
		if (false !== $pos = strrpos($class, '\\')) {
			// namespaced class name

			foreach($this->paths as $prefix => $paths)
			{
				if(strpos($class, $prefix) == 0)
				{   // class is within this prefix
					foreach($paths as $path)
					{   // for each possible folder
						// normalize paths
						$path = rtrim($path, DIRECTORY_SEPARATOR);
						$prefix = trim($prefix, '\\') . '\\';

						// get the partial namespace/class path trimming the prefix portion
						$partialNamespace = str_replace($prefix, '', $class) . '.php';
						$partialPath = str_replace('\\', DIRECTORY_SEPARATOR, $partialNamespace);
						$possibleFilePath = $path . DIRECTORY_SEPARATOR . $partialPath;

						//\Log::Write("search: $class -> $possibleFilePath");

						if(file_exists($possibleFilePath))
							return $possibleFilePath;
					}
				}
			}
		}

		return false;
	}
}

class ApcNiceAppAutoloader extends NiceAppAutoloader
{
	private $prefix;

	public function __construct($prefix)
	{
		if (!extension_loaded('apc')) {
			throw new \RuntimeException('Unable to use ApcNiceAppAutoloader as APC is not enabled.');
		}

		$this->prefix = $prefix;
	}

	public function findFile($class)
	{
		if (false === $file = apc_fetch($this->prefix.$class)) {
			apc_store($this->prefix.$class, $file = parent::findFile($class));
		}

		return $file;
	}
}