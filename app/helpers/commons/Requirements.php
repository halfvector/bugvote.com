<?php

namespace Bugvote\Commons;

/**
 * Class Requirements
 * @package AppTogether\Controllers\Developer
 * provides easy parameter validation for form submission
 */
use Bugvote\Services\Context;
use Bugvote\RequestVariables;

class Requirements
{
    protected $rules = [];

    function __construct($rules)
    {
        $this->rules = $rules;
    }

    # make sure rules make sense
    function validateRules()
    {
        foreach($this->rules as $variable => $rules)
        {
            if(!isset($rules['type']))
                $rules['type'] = 'string';

        }
    }

    # enforce the rules for data extraction, upon failure return a list of errors
	/**
	 * @param \Bugvote\Services\Context $ctx
	 * @param array $errors returns a list of errors if any are found
	 * @internal param \AppTogether\RequestVariables $parameters
	 * @internal param \AppTogether\Commons\SessionDataHelper $sessionHelper
	 * @return bool|\stdClass returns false on error, otherwise an object of filtered and sanitized properties
	 */
    function getData(Context $ctx, &$errors = [])
    {
        $this->validateRules();
        $values = new \stdClass();

		$parameters = $ctx->parameters;
		$sessionHelper = $ctx->session;

	    $ctx->log->writeObject("gathering requirements:", $_POST);

        $persistence = [];

        foreach($this->rules as $variable => $rules)
        {
            $typeHint = $rules['type'];

            $value = null;

            $isOptional = isset($rules['optional']) ? $rules['optional'] : false;
            $isPersistent = isset($rules['persistent']) ? $rules['persistent'] : true; // whether to save on error
			$defaultValue = isset($rules["default"]) ? $rules["default"] : null;

	        if($typeHint == 'array')
		        $defaultValue = [];

			// skip unset variables
            if((!isset($_POST[$variable]) || ($typeHint == 'string' && empty($_POST[$variable]))) && $typeHint != 'file')
			{
                if(!$isOptional)
				{
					$errors []= "Required variable '$variable' is not set.";
					$ctx->log->write("Warning: Required variable '$variable' is not set.");
				} else
				{
					$ctx->log->write("Optional variable '$variable' is not set.");
				}

                $values->$variable = $defaultValue;
                continue;
            }

            if($typeHint == 'string')
                $value = filter_input(INPUT_POST, $variable, FILTER_SANITIZE_STRING, 0);

            else if($typeHint == 'markdown')
            {   // prepare for markdown formatting
                $value = filter_input(INPUT_POST, $variable, FILTER_UNSAFE_RAW, 0);

                // normalize newlines (I just want simple \n everywhere)
                //$value = preg_replace("/\r\n|\r|\n/", "\n", $value);

                // cleanup start and ending
                $value = trim($value);

                // escape all html
                //$value = htmlspecialchars($value);
            }
            elseif($typeHint == 'int' || $typeHint == 'integer') // a strict base-10 integer (not hexadecimal or base-36)
			{
                $value = filter_input(INPUT_POST, $variable, FILTER_SANITIZE_NUMBER_INT, 0);
			}
            elseif($typeHint == 'array')
			{
                $value = $_POST[$variable];
				if($value == null)
					$value = [];
			}
            elseif($typeHint == 'file')
            {
				//$ctx->log->writeObject("_FILES array", $_FILES);

                if(isset($_FILES[$variable]) && $_FILES[$variable]["size"] != 0)
                {
                   	$value = $_FILES[$variable];
                } else
                {
                    if(!$isOptional)
					{
						$errors []= "Required file '$variable' is not set.";
						$ctx->log->write("Warning: Required file '$variable' is not set.");
					} else
					{
						$ctx->log->write("Optional file '$variable' is not set.");
					}
                    $values->$variable = $defaultValue;
                    continue;
                }
            }

            $newErrors = [];

			//$ctx->log->writeObject("rules for '$variable'", $rules);

            foreach($rules as $ruleName => $ruleProp)
            {
                switch($ruleName)
                {
                    case 'minLength':
                        if(strlen($value) < $ruleProp)
						{
                            $newErrors []= "String '$variable' is too short. Must be at least $ruleProp characters.";
							$ctx->auditor->log("Warning: String '$variable' is too short. Must be at least $ruleProp characters.");
						}
                        break;
                }
            }

            // build persistance data in case we need to show some form-validation-errors and don't want to lose all the user's entered data
            if($isPersistent)
                $persistence[$variable] = $value;

            if(!count($newErrors)) {
                // if no errors, great, save the value
                $values->$variable = $value;
            } else {
                // got a new set of validation errors!
                $errors = array_merge($errors, $newErrors);
            }
        }

        // if we had errors, store form-persistence data in session (if possible)
        if(count($errors) && $sessionHelper)
            foreach($persistence as $key => $value)
                $sessionHelper->$key = $value;


        if(count($errors))
            return false;

        return $values;
    }
}
