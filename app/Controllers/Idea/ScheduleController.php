<?php namespace Bugvote\Controllers\Idea;

use Bugvote\Commons\BaseController;
use Bugvote\Commons\Requirements;
use Bugvote\Commons\ViewModelBase;
use Bugvote\Services\Context;
use Bugvote\ViewModels\IdeaRootVM;

class ScheduleControlerVM extends IdeaRootVM
{
	function __construct(Context $ctx, $menuItem = false)
	{
		parent::__construct($ctx);
        $this->setPrimaryMenuItem("roadmap");
	}
}

class ScheduleControlerItemVM extends ViewModelBase
{
	function extend(Context $ctx, $row)
	{
	}
}

class FormData
{
	protected $parameters = [];
	protected $csrf = null;

	function __construct()
	{
	}

	static function create()
	{
		return new FormData();
	}

	function string($name)
	{
		$this->parameters [$name]= ["type" => "string"];
		return $this;
	}

	function integer($name)
	{
		$this->parameters [$name]= ["type" => "integer"];
		return $this;
	}

	function csrf($name)
	{
		$this->csrf = $name;
		$this->parameters [$name]= ["type" => "string"];
		return $this;
	}

	function gather(Context $ctx)
	{
		$requirements = new Requirements($this->parameters);

		$ctx->auditor->log("Gathering user input..");

		$errors = [];
		$data = $requirements->getData($ctx, $errors);

		// early-out if data requirements aren't met
		if(!$data)
		{
			foreach($errors as $error)
				$ctx->flash($error, 'error');

			return false;
		}

		// validate csrf. if it fails, destroy the data too
		if($this->csrf && !$ctx->csrf->validateCSRF($data->csrf))
		{
			$ctx->auditor->log("invalidating form-data due to invalid CSRF token");
			$data = false;
		}

		return $data;
	}
}

class ScheduleController extends BaseController
{
	function plan(Context $ctx)
	{
		$vm = new ScheduleControlerVM($ctx);

		$items = $ctx->dal->fetchMultipleObjs("
		    select * from roadmapCategories
		"
		);


		$vm->categories = ScheduleControlerItemVM::createCollection($ctx, $items);
		$this->renderTemplate($vm, 'Site', "Idea/Schedule");
	}

	function reschedule(Context $ctx)
	{
		$data = FormData::create()
			->integer("categoryId")
			->integer("categoryLabel")
			->csrf("csrf")
			->gather($ctx);

		$ctx->dal->update("suggestions")->set(["categoryId" => $data->categoryId]);

		$result = [
			"value" => $data->categoryLabel
		];

		$this->renderJson($result);
	}
}