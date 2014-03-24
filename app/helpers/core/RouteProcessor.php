<?php namespace Bugvote\Core;

use Symfony\Component\Yaml\Yaml;

class RouteProcessor
{
	protected $cache;
	protected $rootDir;

	function __construct($appDir, ObjectCache $cache = null)
	{
		$this->cache = $cache;
		$this->rootDir = $appDir;
	}

	function getRoutes()
	{
		$routeFile = $this->rootDir . "/routes.yaml";
		$lastModified = filemtime($routeFile);

		if($this->cache)
		{   // use cache if possible
			$routesDefinitions = $this->cache->Cache("router/yaml:$lastModified", [$this, 'getRouteDefinitions'], [$routeFile], false);
			return $this->cache->Cache("router/expanded:$lastModified", [$this, 'processRoutesYaml'], [$routesDefinitions], false);
		} else
		{
			$routesDefinitions = $this->getRouteDefinitions($routeFile);
			return $this->processRoutesYaml($routesDefinitions);
		}
	}

	public function getRouteDefinitions($routeFile)
	{
		// yaml parser is slow..
		$yamlFile = file_get_contents($routeFile);
		$routes = Yaml::parse($yamlFile);

		//$this->getAuditingFacade()->markTiming("Routes loaded from disk");

		return $routes;
	}

	public function processRoutesYaml($routes)
	{
		$context = [];

		$routeCache = [];

		foreach($routes as $name => $rules)
		{

			if( $name == "%resources" )
			{   // special name, auto-expand the resource with ror style paths

				$this->processRouteResource($rules, $context);

			} else
			{   // standard definition

				$verb = "GET";
				$path = "";
				$caller = null;

				foreach($rules as $rule)
				{
					foreach($rule as $key => $value)
					{
						if($key == "Controller")
							$caller = explode("#", $value);
						else
						{
							$verb = strtoupper($key);
							$path = $value;
						}
					}

					$class = $caller[0];
					$method = $caller[1];

					$uniqueName = "$class#$method";

					//$routeCache []= $this->addRoute($verb, $path, $class, $method);
					$routeCache []= ['verb' => $verb, 'path' => $path, 'class' => $class, 'method' => $method, 'name' => $uniqueName];
				}
			}
		}

		return $routeCache;
	}

	protected function expandRoutes($parentPath, $path, $controller)
	{
		$nouns = explode('/', $path);
		$nounId = end($nouns) . 'Id';
		$path = $parentPath . $path;

		// generate standard paths
		$routes []= $this->addRoute('GET',		$path . '/?',					$controller, 'index');			// show list of items
		$routes []= $this->addRoute('GET',		$path . "/[i:$nounId]",			$controller, 'show');			// show item
		$routes []= $this->addRoute('GET',		$path . '/new',					$controller, 'design');			// show create-form
		$routes []= $this->addRoute('POST', 	$path . '/new',					$controller, 'create');			// create item
		$routes []= $this->addRoute('POST', 	$path . '/?',					$controller, 'create');			// create item
		$routes []= $this->addRoute('GET',		$path . "/[i:$nounId]/edit",	$controller, 'edit');			// show update-form
		$routes []= $this->addRoute('POST',		$path . "/[i:$nounId]/edit", 	$controller, 'update');			// update item
		$routes []= $this->addRoute('GET',		$path . "/[i:$nounId]/delete", 	$controller, 'confirm');		// show delete-confirmation
		$routes []= $this->addRoute('POST',		$path . "/[i:$nounId]/delete", 	$controller, 'destroy');		// delete item


		// PUT and DELETE are cool and all, but they don't work for simple browser scaffolds
		//$routes []= Bootstrap::addRoute('DELETE',	$path . "/[i:$nounId]", 		$controller, 'destroy');		// delete item

		$map = [
			'noun' => $nounId,
			'routes' => $routes,
		];

		$this->routeMap[trim($controller,'\\')] = $map;
	}

	public function addRoute($verb, $path, $class, $method)
	{
		//$this->getAuditingFacade()->log("Registered Route: $verb $path -> $class::$method()");

		//$this->klein->respond($verb, $path, function($request, $response, $service) use($class, $method, $path) {
		//	$this->executeRoute($request, $response, $service, $class, $method, $path);
		//});

		return ['verb' => $verb, 'path' => $path, 'class' => $class, 'method' => $method];
	}

	protected function processRouteResource($rules, $context)
	{
		foreach($rules as $rule)
		{
			$path = $rule["path"];
			$controller = $rule["Controller"];

			$parentPath = implode("", $context);

			$this->expandRoutes($parentPath, $path, $controller);

			if(isset($rule["%resources"]))
			{
				$noun = trim($path, '/');
				array_push($context, $path . "/[:{$noun}Id]");
				$this->processRouteResource($rule["%resources"], $context);
				array_pop($context);
			}
		}
	}

	// defunc?
	protected $routeMap = [];

	public function saveRoute($verb, $path, $class, $method)
	{
		$hash = trim($class .'::'. $method, '\\');

		$this->routeMap[$hash] = ['verb' => $verb, 'path' => $path, 'class' => $class, 'method' => $method];
	}

	public function getRouteMap()
	{
		return $this->routeMap;
	}
}