<?php namespace Bugvote\Commons;

use ArrayAccess;
use ArrayObject;
use Bugvote\Core\FormVariables;
use Bugvote\Core\ImageUrlGenerator;
use Bugvote\Services\Context;
use stdClass;

trait UrlCallbackHelper
{
	function getAppId(Context $ctx)
	{
		$appId = $ctx->dal->fetchSingleValue('select projectId from projects where seoUrlTitle = :title', [':title' => $ctx->parameters->strings->appUrl]);

		return $appId;
	}

	function getIdeaId(Context $ctx)
	{
		$ideaId = $ctx->parameters->strings->ideaId;
		$ideaId = base_convert($ideaId, 36, 10) - 10000;
		return $ideaId;
	}

	function encodeIdeaId($realId)
	{
		return base_convert($realId + 10000, 10, 36);
	}

	function getAppUrl(Context $ctx, $appId)
	{
		return $ctx->dal->fetchSingleValue('select projectUrl from projects where projectId = :id', [':id' => $appId]);
	}
}

class FormVariable
{
	protected $value;
	protected $name;
	protected $parent;
	protected $isOptional = true;

	function __construct(FormGatherer $parent, $value, $name)
	{
		$this->parent = $parent;
		$this->value = $value;
		$this->name = $name;
	}

	function __invoke($config)
	{
		var_dump("invoked with config = [$config]");
		return $this->parent;
	}

//	function __call($name, $arguments)
//	{
//		var_dump("called $name with arguments: " . implode(', ', $arguments));
//		return $this->parent;
//	}

	function isOptional() {
		$this->isOptional = true;
		return $this;
	}

	function isRequired() {
		$this->isOptional = false;
		return $this;
	}

	/***
	 * @param int $default
	 * @return FormGatherer
	 */
	function asInt($default = 0) {
		$value = filter_input(INPUT_POST, $this->name, FILTER_SANITIZE_NUMBER_INT);
		if($this->value == null)
			$value = $default;
		return $this->parent->set($this->name, $value);
	}

	/***
	 * @param int $minRange unused
	 * @param int $maxRange unused
	 * @return FormGatherer
	 */
	function asIntArray($minRange = 0, $maxRange = 0) {
		$value = filter_input(INPUT_POST, $this->name, FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY);
		return $this->parent->set($this->name, $value);
	}

	/***
	 * @param string $default
	 * @param int $minLength
	 * @return FormGatherer
	 */
	function asString($default = "", $minLength = 0) {
		$value = filter_input(INPUT_POST, $this->name, FILTER_SANITIZE_STRING, 0);
		if(!$value)
			$value = $default;
		return $this->parent->set($this->name, $value);
	}

	/***
	 * @param null $default
	 * @return FormGatherer
	 */
	function asOneFile($default = null) {

		$value = $default;

		if(isset($_FILES[$this->name]) && !is_array($_FILES[$this->name]) && $_FILES[$this->name]["size"] != 0)
			$value = $_FILES[$this->name];

		return $this->parent->set($this->name, $value);
	}

	/***
	 * @param array $default
	 * @return FormGatherer
	 */
	function asFileArray($default = []) {

		$value = $default;

		if(isset($_FILES[$this->name]) && is_array($_FILES[$this->name]))
			$value = $_FILES[$this->name];

		return $this->parent->set($this->name, $value);
	}

	/***
	 * @param array $default
	 * @return FormGatherer
	 */
	function asArray($default = []) {
		$value = filter_input(INPUT_POST, $this->name, FILTER_UNSAFE_RAW, FILTER_FORCE_ARRAY);
		if(!$value)
			$value = $default;

		return $this->parent->set($this->name, $value);
	}

	function asArrayFromString($delim = ',', $default = []) {
		$value = filter_input(INPUT_POST, $this->name, FILTER_SANITIZE_STRING);
		$array = explode($delim, $value);
		if(!$array)
			$array = $default;

		return $this->parent->set($this->name, $array);
	}

	/***
	 * @param string $default
	 * @return FormGatherer
	 */
	function asMarkdown($default = "") {
		$value = filter_input(INPUT_POST, $this->name, FILTER_SANITIZE_STRING, 0);
		if(!$value)
			$value = $default;

		return $this->parent->set($this->name, $value);
	}
}

class FormGatherer extends ArrayObject implements ArrayAccess
{
	protected $params;
	public $data = [];

	const AsMarkdown = 0, AsArray = 1, AsString = 2, AsFileArray = 3;

	function __construct($params = []) {
		$this->params = $params;
		$this->data = new stdClass();
	}

	/***
	 * @param $param string
	 * @return FormVariable
	 */
	function __get($param) {
		$value = isset($this->params[$param]) ? $this->params[$param] : null;
		return new FormVariable($this, $value, $param);
	}

	function __isset($param) {
		return isset($this->params[$param]);
	}

	/***
	 * @param $name
	 * @param $value
	 * @return FormGatherer
	 */
	function set($name, $value)
	{
		$this->data->$name = $value;
		return $this;
	}

	function required()
	{
		return $this;
	}

	function optional()
	{
		return $this;
	}

	function asObject()
	{
		return $this->data;
	}

	public function offsetExists($offset)
	{
		return isset($this->params[$offset]);
	}

	/***
	 * @param mixed $param
	 * @return FormVariable
	 */
	public function offsetGet($param)
	{
		//return $this->params[$offset];
		$value = isset($this->params[$param]) ? $this->params[$param] : null;
		return new FormVariable($this, $value, $param);
	}

	public function offsetSet($offset, $value)
	{
	}

	public function offsetUnset($offset)
	{

	}
}

abstract class BaseController
{
    protected $context;
    protected $baseUrl;

    function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * filters/sanitizes user-input
     * generates one-time session-stored flashing errors for user-feedback
     * @param array $parameters
     * @return bool|stdClass returns an object full of requested parameter values or false on error
     */
    function getRequirements($parameters, $flashErrors = true)
    {
        $ctx = $this->context;
        $requirements = new Requirements($parameters);

		$ctx->log->write("Gathering user input..");

        $errors = [];
        $data = $requirements->getData($ctx, $errors);

        // early-out if data requirements aren't met
        if(!$data && $flashErrors)
        {
            foreach($errors as $error)
                $ctx->flash($error, 'error');

            return false;
        }

        return $data;
    }

	function gather()
	{
		return new FormGatherer();
	}

    function showError($errorMsg)
    {
        $this->context->flash($errorMsg, 'error');
        $this->context->redirect($this->baseUrl);
    }

	function renderTemplate(PageViewModel $viewModel, $layout, $template)
	{
		$viewModel->layout = $layout;
		$viewModel->template = $template;
		//$viewModel->setButtons($this->context->descriptor->resource, '');

		$this->context->renderTemplate($viewModel);
	}

	function renderJson($object)
	{
		$json = json_encode($object);
		header("Content-Type: application/json");
		echo $json;
		return true;
	}
}