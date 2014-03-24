<?php

namespace Bugvote\Commons;

use Bugvote\Services\Context;
use Bugvote\Core\API;
use Bugvote\Models\Scaffold;
use DAL;
use Models\Scaffold\ItemList;
use Symfony\Component\Yaml\Yaml;

class DataModelDefinition
{
	public $table;			// database table
	public $primaryKey;		// database primaryKey
	public $list;			// URL for items list
	public $token;			// URL primaryKey eg: [:users_id]
	public $id;				// primaryKey value
	public $preferred;		// list of columns to always show

	public $froms;			// where from to select data
	public $joins;			// join with other tables to get a full-picture
	public $parent;			// parenting information
	public $children = [];	// children information
	
	public function __construct($table, $primaryKey, $list, $preferred = false)
	{
		$this->table = $table;
		$this->primaryKey = $primaryKey;
		$this->list = $list;
		$this->preferred = $preferred;
		$this->design = $this->loadSchemaFromFile($table);

		$this->froms = [];
		$this->joins = [];

		if(!$this->preferred)
			$this->preferred = [];

		foreach($this->design as $schemaTableName => $schema)
		{
			foreach($schema as $rule => $ruleSchema)
			{
				if($rule == "%join")
				{
					foreach($ruleSchema as $join => $keys)
					{
						$leftTable = $schemaTableName;
						$rightTable = $join;
						$leftId = $keys[0];
						$rightId = $keys[1];

						$joinLine = "join $rightTable on ($leftTable.$leftId = $rightTable.$rightId)";
						$this->joins []= $joinLine;
					}
				} else
				if($rule == "%children")
				{
					$this->children []= $ruleSchema;
				} else
				{
					if(isset($ruleSchema["parent"]))
					{
						$this->parent = [
							'table' => $ruleSchema["parent"]["table"],
							'label' => $ruleSchema["parent"]["label"],
							'parentKey' => $ruleSchema["parent"]["key"],
							'localKey' => $rule,

						];
					}

					$this->preferred []= "$schemaTableName.$rule";
				}
			}

			$this->froms []= $schemaTableName;
		}
	}

	protected function loadSchemaFromFile($tableName)
	{
		return Yaml::parse(file_get_contents(LEXY_ROOT_PATH . "/models" . "/$tableName.yaml"));
	}
}

abstract class Scaffolding extends BaseController
{
    function setView(PageModel $main, $template, $layout, $scripts = false)
    {
        // build view
        $main->layout = $layout;
        $main->template = 'Scaffold/' . $template;
        $main->templateScripts = 'Scaffold/' . $scripts;
    }

	/** @returns DataModelDefinition */
	protected function getDataModelFromContext(Context $context)
	{
		$definition = $this->getDefinition($context);
		
		$basePath = trim($definition->list, '?/');
		$nouns = explode('/', $basePath);
		$nounId = end($nouns) . 'Id';

		$data = $definition;
		$data->token = $nounId;
		$data->id = $context->request->$nounId;

		return $data;
	}

	/** @returns DataModelDefinition */
	protected abstract function getDefinition(Context $context);

	public function index(Context $context)
	{
		// setup data models
		$main = new PageModel($context);

		// data-model definition of the underlying database schema
		$dataModel = $this->getDataModelFromContext($context);

		$main->setSecondaryNav([
            "list" => [ 'url' => $dataModel->list, 'active' => '', 'title' => 'Browse', 'icon' => 'icon-search' ],
			"search" => [ 'url' => $dataModel->list, 'active' => '', 'title' => 'Search', 'icon' => 'icon-search' ],
		]);

		$searchFields = $context->request->searchFields;
		$searchQuery = $context->request->q;

		$parentId = $context->request->parentId;

		// convert query into internal representation
		$dbQuery = str_replace("*", "%", $searchQuery);

		$items = self::getAllItems($context->dal, $dataModel, $searchFields, $dbQuery, $parentId);

		$expandedPath = $context->path;
		$expandedPath = preg_replace('/\[.*:parentId\]/', $parentId, $expandedPath);

		$main->autoList = new ItemList($items, $dataModel, $expandedPath, $searchFields);
		$main->searchQuery = $searchQuery;

		if($dataModel->parent)
		{
			$main->parenting = $context->dal->fetchSingleValue(
				"select {$dataModel->parent['label']} from {$dataModel->parent['table']} where {$dataModel->parent['parentKey']} = :id", [':id' => $parentId]
			);
		}

        $this->setView($main, 'List', 'Site', 'LayoutScripts');
        $main->setButtons($dataModel->list, "list");

		$allFlashes = $context->response->flashes();
		$main->alerts = [];
		foreach($allFlashes as $type => $flashes)
		{
			foreach($flashes as $flash)
				$main->alerts []= ['type' => $type, 'message' => $flash];
		}

		// render
		$context->render($main);
	}

	public function edit(Context $context)
	{
		// setup data models
		$main = new PageModel($context);

		$dataModel = $this->getDataModelFromContext($context);
		$metadata = new ScaffoldMetadata($context->api);
		$item = self::getCurrentItem($context->dal, $dataModel->table, $dataModel->primaryKey, $dataModel->id);
		$design = $metadata->getDesign($dataModel->table, $item);
		$main->fields = $design['fields'];

		$main->setSecondaryNav([
			"Search" => [ 'url' => $dataModel->list, 'active' => '', 'title' => 'Search', 'icon' => 'icon-search', 'notifications' => 2 ],
		]);

		$allFlashes = $context->response->flashes();
		$main->alerts = [];
		foreach($allFlashes as $type => $flashes)
		{
			foreach($flashes as $flash)
				$main->alerts []= ['type' => $type, 'message' => $flash];
		}

        $this->setView($main, 'ItemEdit', 'Site', 'LayoutScripts');
        $main->setButtons($dataModel->list, "edit");

		// render
		$context->render($main);
	}

	public function update(Context $context)
	{
		$dataModel = $this->getDataModelFromContext($context);

		if( $context->request->action == "cancel" )
		{
			$context->redirect($dataModel->list);
		} else if( $context->request->action == "delete" )
		{
			self::destroyItem($context->dal, $dataModel->table, $dataModel->primaryKey, $dataModel->id);
			$context->redirect($dataModel->list);
		} else
		{
			// otherwise just update
			self::updateItem($context->api, $context->dal, $dataModel->table);
			$context->redirect($dataModel->list);
		}
	}

	public function create(Context $context)
	{
		/** @var $dataModel IScaffold */
		$dataModel = $this->getDataModelFromContext($context);
		self::createItem($context->api, $context->dal, $dataModel->table);
		$context->redirect($dataModel->list);
	}

	public function design(Context $context)
	{
		// setup data models
		$main = new PageModel($context);

		/** @var $dataModel IScaffold */
		$dataModel = $this->getDataModelFromContext($context);
		$metadata = new ScaffoldMetadata($context->api);
		$design = $metadata->getDesign($dataModel->table, null);

		$main->fields = [];

		# fields
		foreach($design['fields'] as $field)
		{
			# ignore invisible fields (ie: auto id)
			if(!$field['visible'])
				continue;

			$main->fields []= $field;
		}

        $this->setView($main, 'ItemCreate', 'Site', 'LayoutScripts');
        $main->setButtons($dataModel->list, "edit");

		$allFlashes = $context->response->flashes();
		$main->alerts = [];
		foreach($allFlashes as $type => $flashes)
		{
			foreach($flashes as $flash)
				$main->alerts []= ['type' => $type, 'message' => $flash];
		}

		// render
		$context->render($main);
	}

	public function destroy(Context $context)
	{
		$dataModel = $this->getDataModelFromContext($context);
		self::destroyItem($context->dal, $dataModel->table, $dataModel->primaryKey, $dataModel->id);
	}


	public static function creationForm(API $api, $tableName)
	{
		$metadata = new ScaffoldMetadata($api);
		$design = $metadata->getDesign($tableName, null);
		return $design;
	}

	public static function editingForm(API $api, $tableName, $existingItem)
	{
		$metadata = new ScaffoldMetadata($api);
		$design = $metadata->getDesign($tableName, $existingItem);
		return $design;
	}

	// POST response
	public static function createItem(API $api, DAL $dal, $tableName)
	{
		$metadata = new ScaffoldMetadata($api);
		$design = $metadata->getDesign($tableName, null);
		$query = $metadata->getQueryComponentsFromDesign($design);

		$dal->insert($query['table'])->set($query['values']);

		foreach($query['assetIds'] as $assetId)
		{
			\Log::Write("Activated assetId=$assetId");
			$api->EnableAsset($assetId);
		}

		# debug output
		\Log::Write("Inserted item in {$query['table']}");
	}

	public static function destroyItem(DAL $dal, $tableName, $primaryKey, $id)
	{
		// UGH.. need to resolve foreign constraints
		$dal->deleteSingleObj("delete from $tableName where $primaryKey = :$primaryKey", [":$primaryKey" => $id]);

		\Log::Write("Deleted item in $tableName with $primaryKey=$id");
	}

	// POST response
	public static function updateItem(API $api, DAL $dal, $tableName)
	{
		$metadata = new ScaffoldMetadata($api);
		$design = $metadata->getDesign($tableName, null);
		$query = $metadata->getQueryComponentsFromDesign($design);

		$dal->update($query['table'])->set($query['values'])->where($query['where']);

		foreach($query['assetIds'] as $assetId)
		{
			\Log::Write("Activated assetId=$assetId");
			$api->EnableAsset($assetId);
		}

		# debug output
		\Log::Write("Updated item in {$query['table']}");
	}

	public static function getCurrentItem(DAL $dal, $tableName, $primaryKey, $id)
	{
		$item = $dal->fetchSingleObj("select * from $tableName where $primaryKey = :$primaryKey", [":$primaryKey" => $id]);
		return $item;
	}

	public static function getAllItems(DAL $dal, DataModelDefinition $schemaDesign, $searchFields = [], $searchQuery = "", $parentId = null)
	{
		$tableName = $schemaDesign->table;
		$preferred = $schemaDesign->preferred;

		$selectColumns = implode(", ", $preferred);

		$filter = [];

		$searchFields = is_array($searchFields) ? $searchFields : [];
		foreach($searchFields as $field)
			$filter []= "($field like :query)";

		if(count($filter))
			$where = "where (" . implode(" or ", $filter) . ")";
		else
			$where = "";

		$joins = implode(" ", $schemaDesign->joins);

		if(count($schemaDesign->parent) && $parentId != null)
		{
			if($where != "")
				$where .= " and ";
			else
				$where = "where ";

			$where .= "({$schemaDesign->parent['localKey']} = $parentId)";
		}

		$query =
			"select $selectColumns ".
				"from $tableName ".
				"$joins ".
				"$where limit 50";

		//dump($query);

		$list = $dal->fetchMultipleObjs($query, [':query' => $searchQuery]);
		return $list;
	}
}
