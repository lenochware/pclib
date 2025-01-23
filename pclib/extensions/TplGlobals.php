<?php
/**
 * @file
 * TplGlobals class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\extensions;
use pclib\system\BaseObject;
use pclib\Tpl;
use pclib\IService;

/**
 * Add variables or new element types globally for all templates.
 * Use like app service: $app->globals = new TplGlobals;
 * You can define type like this: set('type:my_string', $fn);
 * You can use modules: set('images.upload_dir', '/images');
 */
class TplGlobals extends BaseObject implements IService
{
	protected $values = [];

/**
 * Set global template variable $id.
 * @param string $id
 * @param mixed|callable $value /can be callable function($o, $id, $sub, $value)/
 */
	function set($id, $value)
	{
		$this->values[$id] = $value;
	}

/**
 * Set array of global template variables.
 */
	function setArray(array $values)
	{
		$this->values = $values;
	}

/**
 * Remove all global variables.
 */
	function reset()
	{
		$this->values = [];
	}

/**
 * Get global template variable $id.
 */
	function get($id)
	{
		return $this->values[$id];
	}

/**
 * Delete global template variable $id.
 */
	function delete($id)
	{
		unset($this->values[$id]);
	}

/**
 * Fetch global template variable $id (if it is callable, return result).
 */
	function fetch($id, array $params = [])
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

/**
 * Add globals to template $t
 */
	function addGlobals(Tpl $t, array $params)
	{
		$this->addGlobalsModule($t, '');

		$use = array_get($params, 'use', '');
		if (!$use) return;

		$modules = explode(',', $use);

		foreach ($modules as $module) {
			$this->addGlobalsModule($t, $module);
		}
	}

	protected function addGlobalsModule(Tpl $t, $module = '')
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

			// if (isset($t->elements[$id])) {
			// 	throw new \pclib\Exception("Name conflict: global '$id'");
			// }

			// $t->addTag("global $id skip");

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