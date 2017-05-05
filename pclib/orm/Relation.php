<?php
/**
 * @file
 * Relation class.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\orm;
use pclib;

class Relation extends Selection
{
	public $params;
	protected $model;

function __construct(Model $model, $name)
{
	$params = $model->getTemplate()->elements[$name];

	if ($params['type'] != 'relation') {
		throw new Exception("Relation not found: '%s'", array($name));
	}

	if (!$params['table'] or !$params['key']) {
		throw new Exception("Missing table or key name.");
	}

	$this->params = $params;
	$this->model = $model;
	parent::__construct();
}

function getType()
{
	if ($this->params['many']) return 'many';
	if ($this->params['owner']) return 'owner';
	if ($this->params['many_to_many']) return 'many_to_many';
	if ($this->params['one']) return 'one';

	throw new Exception("Unknown relation type.");
}

function getJoinTableName()
{
	if ($this->params['through']) return $this->params['through'];

	$names = array($this->model->getTableName(), $this->params['table']);
	sort($names);
	return implode('_', $names);
}

function getJoinCondition()
{
	if ($this->getType() == 'many_to_many') {
		throw new pclib\NotImplementedException;
	}

	$t1 = $this->model->getTableName();
	$k1 = 'ID'; //TODO: nacist z modelu
	$t2 = $this->params['table'];
	$k2 = $this->params['key'];

	return "$t1.$k1=$t2.$k2";
}

function save(Model $model)
{
	$foreignKey = $this->params['key'];

	switch ($this->getType()) {
		case 'one':
		case 'many':
			$model->setValue($foreignKey, $this->model->getPrimaryId());
			$model->save();
			break;
		case 'owner':
			throw new Exception('Cannot save owner relation.');
			break;
		case 'many_to_many':
			throw new pclib\NotImplementedException;
			break;
	}
}

function clear()
{
	parent::clear();

	$table = $this->params['table'];
	$foreignKey = $this->params['key'];

	switch ($this->getType()) {
		case 'one':
		case 'many':
			$this->from($table)->where(array($foreignKey => $this->model->getPrimaryId()));
			break;
		case 'owner':
			$this->from($table)->where(array('ID' => $this->model->getValue($foreignKey)));
			break;
		case 'many_to_many':
			throw new pclib\NotImplementedException;
			$joinTable = 	$this->getJoinTableName();
			list($k1,$k2) = explode(',', $foreignKey);
			$this->from($table)->where(array('ID' => $this->model->getValue($foreignKey)));
			break;
	}
}

}

?>