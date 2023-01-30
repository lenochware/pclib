<?php 
/**
 * @file
 * TplGlobals class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\extensions;
use pclib\system\BaseObject;
use pclib\IService;

/**
 * Add variables or new element types visible in all templates.
 */
class TplGlobals extends BaseObject implements IService
{
	protected $values = [];

	function set($id, $value)
	{
		$this->values[$id] = $value;
	}

	function setArray(array $values)
	{
		$this->values = $values;
	}

	function reset()
	{
		$this->values = [];
	}

	function get($id)
	{
		return $this->values[$id];
	}

	function delete($id)
	{
		unset($this->values[$id]);
	}

	function fetch($id, $params = [])
	{
		if (is_callable($this->values[$id])) {
			return call_user_func_array($this->values[$id], $params);
		}
		else {
			return $this->values[$id];
		}
	}

	function has($id)
	{
		return isset($this->values[$id]);
	}

	//add [global id] to template elements

	function addGlobals($t)
	{
		foreach ($this->values as $id => $value) {
			if ($this->isType($id)) {
				$t->addType(substr($id,5), $value);
				continue;
			};

			if (isset($t->elements[$id])) {
				throw new \pclib\Exception("Name conflict: global '$id'");
			}

			$t->addTag("global $id skip");

			$t->elements[$id]['onprint'] = function($o, $id, $sub, $value) {
				print $this->fetch($id, [$o, $id, $sub, $value]);
			};

		}
	}

	protected function isType($id)
	{
		return (strpos($id, 'type:') === 0);
	}
}

?>