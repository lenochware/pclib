<?php 
/**
 * @file
 * Template factory.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\extensions;
use pclib;
use pclib\system\BaseObject;
use pclib\Exception;
use pclib\Tpl;

/**
 * Template factory.
 * Build grid, form or tpl from list of database columns.
 * Usage: $grid = TemplateFactory::create('grid.tpl', $columns);
 */
class TemplateFactory extends BaseObject
{

/**
 * Create template object from database columns.
 * @param string $path Path to generator template (see assets/tpl for defaults)
 * @param array $columns List of database columns (see Db->columns() method)
 * @return Tpl|Form|Grid $object
 **/
static function create($path, array $columns)
{
	$gen = static::getGenerator($path, $columns);
	
	$classNames = array('tpl' => '\pclib\Tpl', 'form' => '\pclib\Form', 'grid' => '\pclib\Grid');

	$className = $classNames[$gen['create']];

	if (!$className) {
		throw new Exception('Unknown template type.');
	}

	$obj = new $className;
	$obj->loadString($gen['output']);
	$obj->init();

	return $obj;
}

/**
 * Create template from database columns.
 * @param string $path Path to generator template (see assets/tpl for defaults)
 * @param array $columns List of database columns (see Db->columns() method)
 * @return string $template Template source
 **/
static function getTemplate($path, array $columns)
{
	$gen = static::getGenerator($path, $columns);
	return $gen['output'];
}

protected static function getGenerator($path, $columns)
{
	$t = new PCTpl($path);

	$options = $t->elements['templatefactory'];
	if (!$options['create']) $options['create'] = 'tpl';

	$cols = array_values($columns);

	$t->values['columns'] = $cols;
	$t->values['head'] = $cols;
	$t->values['elements'] = static::getElements($options, $columns);

	$trans = array('<:' => '<', ':>' => '>', '{:' => '{', ':}' => '}');

	return array(
		'template' => $t,
		'create' => $options['create'],
		'output' => strtr($t->html(), $trans)
		);
}

// protected static getColumns($tableName)
// {
// 	global $pclib;
// 	return $pclib->app->db->columns($tableName);
// }

protected static function getElements($options, $columns)
{
	$elements = array();

	$getter = 'get'.ucfirst($options['create']).'Element';
	if (!method_exists(get_class(), $getter)) {
		throw new Exception("Unknown method %s", array('TemplateFactory::'.$getter));
	}

	foreach ($columns as $id => $col) {
		$el = array(
			'type' => 'string',
			'name' => $id,
			'lb' => $col['comment'] ?: $id,
		);
		$el = static::$getter($el, $col, $options);
		$elements[] = static::mkEl($el);
	}

	return implode("\n", $elements);
}

private static function mkEl($el)
{
	$s = $el['type'].' '.$el['name'];
	foreach ($el as $k => $v) {
		if ($k == 'type' or $k == 'name') continue;
		$s .= " $k \"$v\"";
	}
	return trim($s);
}

protected static function getTplElement($el, $col, $options)
{
	return $el;
}

protected static function getGridElement($el, $col, $options)
{
	if ($options['sort']) $el['sort'] = '1';
	if ($col['type'] == 'date') $el['date'] = '1';
	return $el;
}

protected static function getFormElement($el, $col, $options)
{

	$type = 'input';

	$size = ($col['size'] > 50)? '50/'.$col['size'] : $col['size'];
	if ($col['type'] == 'string' and $col['size'] > 255) { $type = 'text'; $size = null; }
	if ($col['type'] == 'bool') { $type = 'check'; $size = null; }

	if ($col['type'] == 'int' or $col['type'] == 'float') $size = '6/30';
	if ($col['type'] == 'date') $size = ($col['size'] > 1)? '20/30' : '10/30';

	if ($size) $el['size'] = $size;
	if ($col['type'] == 'date') $el['date'] = '1';
	if (stripos('-'.$col['name'], 'MAIL')) $el['email'] = '1';
	if (!$col['nullable']) $el['required'] = '1';

	$el['type'] = $type;

	return $el;
}

}

?>