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
use pclib\Exception;

/**
 * Base class for any pclib Validator.
 * Features:
 * - Method validate() for validation of one value
 * - Method validateArray() for validation array of values against specification (elements)
 * - Method getErrors() for reading validation errors
 * - Method setRule() for adding your own rules
 */
class ValidatorBase extends BaseObject
{
	/** Are variables undefined in template valid? */
	public $skipUndefined = false;

	/** Silently skip unknown rules? */
	public $skipUndefinedRule = false;

	/** List of ignored attributes (rules) */
	public $ignoredAttributes = array('id', 'type', 'begin', 'end');

	/** List of ignored element types */
	public $ignoredElements = array();

	/** Array of messages [ruleName: message, ...] */
	public $messages = array(
		'undefined' => "Undefined element!",
	);

	/** Array of rule handlers [ruleName: callable, ...] */
	protected $rules = array();

	/** Array of [fieldName: errorMessage, ...] */
	protected $errors = array();

	protected $elements;

	/** var TplParser */
	protected $parser;

	/** var Translator */
	public $translator;

	/** Occurs before element validation. */
	public $onValidateElement;

	function __construct()
	{
		parent::__construct();
		$this->service('translator', false);
		$this->setRule('required', array($this, 'notBlank'), "Field is required!");
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
	 * Set error message for element $id.
	 * Called when validation of element's value failed.
	 * @param string $id Element-id
	 * @param string $messageId Id such as 'email', 'required' or full message text
	 */
	function setError($id, $messageId, array $args = array())
	{
		$mEl = $this->elements[$id.'.'.$messageId];

		if ($mEl['type'] == 'message') {
			$message = $mEl['text'];
		}
		else {
			$message = $this->messages[$messageId] ?: $messageId;
		}

		if ($this->translator) {
			$s = $this->translator->translate($message, $args);
		}
		else {
			$s = vsprintf($message, $args);
		}

		$this->errors[$id] = $s;
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

	protected function getParser()
	{
		if (!$this->parser) {
			$this->parser = new TplParser;
		}

		return $this->parser;
	}

	/**
	 * Validate $value using $rule.
	 * Example: validateRule('1.1.2016', 'date', '%d.%m.%Y')
	 * @param mixed $value
	 * @param string $rule
	 * @param mixed $param Rule parameters
	 * @return bool isValid
	 */
	protected function validateRule($value, $rule, $param = null)
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
	 * Validate $value against $rules.
	 * Example: validate('1.1.2016', 'date required')
	 * @param mixed $value
	 * @param string $rules
	 * @return bool isValid
	 */
	function validate($value, $rules)
	{
		$this->errors = array();
		$elem = $this->getParser()->parseLine("string value $rules");
		return $this->validateElement($value, $elem);

	}

	/**
	 * Validate $value against $elem rules.
	 * @param mixed $value
	 * @param array $elem [rule => param, ...] e.g. ['date' => 1, 'required' => 1]
	 * @return bool isValid
	 */
	function validateElement($value, array $elem)
	{
		$event = $this->onValidateElement($value, $elem);
		if ($event) {
			if (!$event->propagate) return $event->result;
		}

		if (!$elem['type']) {
			if ($this->skipUndefined) {
				return true;
			}
			else {
				$this->setError($elem['id'], 'undefined');
				return false;
			}
		}

		//blank fields handling: required: invalid, not-required: valid.
		if ($this->isBlank($value)) {
			if ($elem['required']) {
				$this->setError($elem['id'], 'required');
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

			if (!$this->validateRule($value, $rule, $param)) {
				$this->setError($elem['id'], $rule, array($elem['id'], $value, $rule, $param));
				return false;
			}
		}

		return true;
	}

/**
	 * Validate array of values, using validation rules in $elements.
	 * Set $this->errors array.
	 * @param array $values [id => value, ...]
	 * @param array $elements [id => array_of_rules, ...]
	 * @return bool isValid
	 */
	function validateArray(array $values, array $elements)
	{
		$ok = true;
		$this->errors = array();
		$this->elements = $elements;

		$keys = array_unique(
			array_merge(array_keys($values), 
			array_keys($elements))
		);

		foreach ($keys as $key) {
			if (in_array($elements[$key]['type'], $this->ignoredElements)) continue;
			if (!$this->validateElement($values[$key], (array)$elements[$key])) $ok = false;
		}

		return $ok;
	}


}

?>