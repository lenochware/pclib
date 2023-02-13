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

	function addGlobals($t, $params)
	{
		$this->addGlobalsModule($t, '');

		$use = array_get($params, 'use', '');
		if (!$use) return;

		$modules = explode(',', $use);

		foreach ($modules as $module) {
			$this->addGlobalsModule($t, $module);
		}
	}

	protected function addGlobalsModule($t, $module = '')
	{
		foreach ($this->values as $id => $value)
		{
			$var = $this->parseId($id);
			if ($var['module'] != $module) continue;

			if ($var['type']) {
				$t->addType($var['id'], $value);
				continue;
			};

			$id = $var['id'];

			if (isset($t->elements[$id])) {
				throw new \pclib\Exception("Name conflict: global '$id'");
			}

			$t->addTag("global $id skip");

			$t->elements[$id]['onprint'] = function($o, $id, $sub, $value) use($module) {

				print $this->fetch(($module? "$module.":'') . $id, [$o, $id, $sub, $value]);
			};
		}
	}

	protected function parseId($id)
	{
		$type = false;

		if (strpos($id, 'type:') === 0) {
			$type = true;
			$id = substr($id,5);
		};

		if (strpos($id, '.')) {
			list($module, $id) = explode('.', $id, 2);
		}
		else {
			$module = '';
		}

		return ['type' => $type, 'module' => $module, 'id' => $id];
	}	
}

?>