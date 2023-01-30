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

namespace pclib\system;

/**
 * Add variables or new element types visible in all templates.
 */
class TplGlobals extends BaseObject implements \pclib\IService
{
	protected $values = [];

	function set($id, $value)
	{
		$this->values[$id] = $value;
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

			$v = $this->fetch($id);
			$t->addTag("global $id skip");

			$t->elements[$id]['onprint'] = function() use($t, $id) {
				print $this->fetch($id, [$t, $id]);
			};

		}
	}

	protected function isType($id)
	{
		return (strpos($id, 'type-') === 0);
	}
}

?>