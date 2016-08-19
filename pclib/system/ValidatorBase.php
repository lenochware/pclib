<?php 
/**
 * @file
 * Base class for any pclib Validator.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\system;

/**
 * Base class for any pclib Validator.
 * Features:
 * - Definition of validation rules in template
 * - Method isValid() for validation just one value
 * - Method validate() for validation array of values against template
 * - Method getErrors() for reading validation errors
 * - Method setRule() for adding your own rules
 */
class ValidatorBase extends BaseObject
{
 
 /** Are variables undefined in template valid? */
 public $skipUndefined = true;

 /** Silently skip unknown rules? */
 public $skipUndefinedRule = true;

/** List of ignored attributes (rules) */
 public $ignoredAttributes = array('id', 'type', 'begin', 'end');

/** List of ignored element types */
 public $ignoredElements = array();

/** Array of messages [ruleName: message, ...] */
	public $messages = array(
		'undefined' => "Undefined element '{name}'",
	);

	/** Array of rule handlers [ruleName: callable, ...] */
	protected $rules = array();

	/**var Tpl */
	protected $template;

	/** Array of [fieldName: errorMessage, ...] */
	protected $errors;

	/**
	 * Create validator.
	 * Get template object or path to template and load it.
	 * @param string|Tpl $pathOrTpl
	 */
	function __construct($pathOrTpl = '')
	{
		if ($pathOrTpl instanceof \pclib\Tpl) {
			$this->template = $pathOrTpl;
		}
		else {
			$this->load($pathOrTpl);
		}

		$this->setRule('required', array($this, 'notBlank'), "'{name}' is required!");
	}

	/**
	 * Load template.
	 * @param string $path Path to template.
	 */
	function load($path)
	{
		$this->template = new \pclib\Tpl($path);
	}

	/**
	 * Set or add new rule.
	 * @param string $name Rule name
	 * @param callable $function Rule handler
	 * @param string $message Rule error message
	 */
	function setRule($name, /*callable*/ $function, $message)
	{
		if (!is_callable($function)) {
			throw new Exception("Rule handler must be callable.");
		}

		$this->rules[$name] = $function;
		$this->messages[$name] = $message;
	}

	/**
	 * Check if validator has handler for rule $rule.
	 * @param string $rule Rule name
	 */
	function hasRule($rule)
	{
		return is_callable($this->rules[$rule]);
	}

	/**
	 * Validate array of values, using validation rules in template.
	 * Set $this->errors array.
	 * @param array $values
	 * @return bool isValid
	 */
	function validate(array $values)
	{
		$ok = true;
		$this->errors = array();

		$keys = array_unique(
			array_merge(array_keys($values), 
			array_keys($this->template->elements))
		);

		foreach ($keys as $key) {
			if (in_array($this->template->elements[$key]['type'], $this->ignoredElements)) continue;
			if (!$this->isValidElem($values[$key], $key)) $ok = false;
		}

		return $ok;
	}

	/**
	 * Set error message for element $name, rule $rule and value $value.
	 * Called when validation of element's value failed.
	 */
	function setError($name, $value, $rule)
	{
		$mEl = $this->template->elements[$name.'.'.$rule];

		if ($mEl['type'] == 'message') {
			$message = $mEl['text'];
		}
		else {
			$message = $this->messages[$rule] ?: sprintf("%s: Validation of '%s' failed,", $name, $rule);
		}

		$param = array('name' => $name, 'value' => $value);
		$this->errors[$name] = paramStr($message, $param);
	}

	/** 
	 * Return validation errors.
	 */
	function getErrors()
	{
		return $this->errors;
	}

	/** 
	 * Check if field is blank (not filled). 
	 */
	function isBlank($value)
	{
		return is_array($value)? (count($value) == 0) : (strlen($value) == 0);
	}

	/** 
	 * NOT isBlank().
	 */
	function notBlank($value)
	{
		return !$this->isBlank($value);
	}

	/**
	 * Validate $value using $rule.
	 * Example: isValidRule('1.1.2016', 'date', '%d.%m.%Y')
	 * See also isValid()
	 * @param mixed $value
	 * @param string $rule
	 * @param mixed $param Rule parameter
	 * @return bool isValid
	 */
	function isValidRule($value, $rule, $param = null)
	{
		$func = $this->rules[$rule];

		if (is_callable($func)) {
			return call_user_func($func, $value, $param);
		}
		else {
			throw new Exception(sprintf("Rule '%s' is not defined.", $rule));			
		}
	}

	/**
	 * Validate $value using template element.
	 * Example: isValidElem('1.1.2016', 'ORDER_DATE')
	 * See also isValid()
	 * @param mixed $value
	 * @param string $name Element name
	 * @return bool isValid
	 */
	function isValidElem($value, $name)
	{
		$elem = $this->template->elements[$name];

		if (!$elem['type']) {
			if ($this->skipUndefined) {
				return true;
			}
			else {
				$this->setError($name, $value, 'undefined');
				return false;
			}
		}

		//blank fields handling: required: invalid, not-required: valid.
		if ($this->isBlank($value)) {
			if ($elem['required']) {
				$this->setError($name, $value, 'required');
				return false;

			}
			return true;
		}

		foreach ((array)$elem as $rule => $param) {
			if (!$this->hasRule($rule)) {
				 if ($this->skipUndefinedRule or in_array($rule, $this->ignoredAttributes)) {
					continue;
				}
			}

			if (!$this->isValidRule($value, $rule, $param)) {
				$this->setError($name, $value, $rule);
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate $value using template element or rule.
	 * Example: isValid('1.1.2016', 'elem ORDER_DATE') or isValid('1.1.2016', 'date')
	 * @param mixed $value
	 * @param string $name Element or rule name
	 * @param mixed $name Rule parameter
	 * @return bool isValid
	 */
	function isValid($value, $name, $param = null)
	{
		if (strpos($name, 'elem ') === 0) {
			return $this->isValidElem($value, substr($name, 5));
		}
		else return $this->isValidRule($value, $name, $param);
	}

	//vygeneruje podle *.tpl souboru javascript s validacnimi pravidly?
	//pri pridani noveho rule uzivatel muze pridat i odpovidajici js?
	function createJavascript()
	{

	}
}

?>