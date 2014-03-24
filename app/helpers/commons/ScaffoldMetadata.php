<?php

namespace Bugvote\Commons;

use Bugvote\Core\API;
use Bugvote\Core\ImageProvider;
use Symfony\Component\Yaml\Yaml;

class ScaffoldMetadata
{
	protected $api;

	public function __construct(API $api)
	{
		$this->api = $api;
	}

	public function extractPostInput($name, $special)
	{
		// handle images in a special way
		if(strstr($special,"image"))
			return $this->extractImageInput($name);

		# filter
		$filter = FILTER_SANITIZE_STRING;
		$data = filter_input(INPUT_POST, $name);

		# validate

		\Log::Write("name=$name data=$data");
		return $data;
	}

	public function extractImageInput($name)
	{
		if(isset($_FILES[$name]) && $_FILES[$name]["size"] != 0)
		{
			$file = $_FILES[$name];
			$assetId = $this->api->CreateAsset($file);

			\Log::Write("image uploaded; name=$name, assetId=$assetId");
			return $assetId;
		}

		\Log::Write("no image uploaded; name=$name");
		return false;
	}

	public function getDesign($tableName, $existingItem)
	{
		$modelDesign = Yaml::parse(file_get_contents(LEXY_ROOT_PATH . "/models" . "/$tableName.yaml"));

		$dataExtractor = [$this, 'extractPostInput'];
		$tableName = "";

		$fields = [];

		foreach($modelDesign as $key => $table)
		{
			$tableName = $key;
			foreach($table as $name => $column)
			{
				if($name == "%join")
				{
					continue;
				}

				$special = isset($column["special"]) ? $column["special"] : "";

				$visible = true;

				# regular field
				$template = "Scaffold/ItemInputField";

				# hidden field
				if(strstr($special, 'primaryKey'))
				{
					if(!$existingItem)
						$visible = false;
					$template = "Scaffold/ItemHiddenField";
				}

				# image uploader
				if(strstr($special, 'image') !== false)
					$template = "Scaffold/ImageUploadField";

				$previousValue = isset($existingItem->$name) ? $existingItem->$name : '';

				if(strstr($special, 'image') && $previousValue != '')
				{	// translate image-id to img-url
					$previousValue = ImageProvider::GetResizedAssetWebPath($previousValue, 200, 150, "auto");
				}

				$fields []= [
					'field' => $name, 'value' => $previousValue,
					'visible' => $visible,
					'template' => $template,
					'special' => $special,
					'getEditor' => function() use($template) {
						return "{{>$template}}";
					},
					'getData' => function() use($name, $special, $dataExtractor) {
						return call_user_func_array($dataExtractor, [$name, $special]);
					}
				];
			}
		}

		return [
			'name' => $tableName,
			'fields' => $fields
		];
	}

	function getQueryComponentsFromDesign($design)
	{
		$values = [];
		$where = [];
		$assetsNeedingValidation = [];

		foreach($design['fields'] as $field)
		{
			$name = $field['field'];
			$value = $field['getData']();

			if($value === false && strstr($field['special'], 'image'))
			{	// no image data? drop field from update list
				continue;
			}

			if(strstr($field['special'], 'image'))
				$assetsNeedingValidation []= $value;

			if(strstr($field['special'],'primaryKey'))
			{
				$where[$name] = $value;
				continue;
			}

			$values[$name] = $value;
		}

		return [
			'table' => $design['name'],
			'values' => $values,
			'where' => $where,
			'assetIds' => $assetsNeedingValidation,
		];
	}
}