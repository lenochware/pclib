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
use pclib\Exception;

/**
 * It represents related record(s) of the Model.
 * Usually you do not instantiate this class manually - it is returned by Model->related() method.
 * Because it is Selection, you can use where(), order() etc. methods on related records.
 * Examples: 
 * - print $book->related('authors'); or in short form: print $book->authors;
 * - Using where: print $book->authors->where("name='John'");
 */
class Relation extends Selection
{
	public $params;
	protected $model;

/**
 * Constructor.
 * @param Model $model Owner of the relation
 * @param string $name Name of the relation - must be specified in model template
 */
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
	if (!empty($this->params['many'])) return 'many';
	if (!empty($this->params['owner'])) return 'owner';
	if (!empty($this->params['many_to_many'])) return 'many_to_many';
	if (!empty($this->params['one'])) return 'one';

	throw new Exception("Unknown relation type.");
}

function getJoinTableName()
{
	if ($this->params['through']) return $this->params['through'];

	$names = array($this->model->getTableName(), $this->params['table']);
	sort($names);
	return implode('_', $names);
}

/**
 * @return string SQL join condition
 */
function getJoinCondition()
{
	if ($this->getType() == 'many_to_many') {
		$t1 = $this->model->getTableName();
		$t2 = $this->params['table'];
		$tj = $this->getJoinTableName();
		list($k1,$k2) = explode(',', $this->params['key']);
		return "$tj.$k1=$t1.ID and $tj.$k2=$t2.ID";
	}
	else {
		$t1 = $this->model->getTableName();
		$k1 = 'ID'; //TODO: nacist z modelu
		$t2 = $this->params['table'];
		$k2 = $this->params['key'];
		return "$t1.$k1=$t2.$k2";		
	}
}

/**
 * Save $record as relation of the owner.
 * Example: Add post to the user: $user->posts->save($post);
 * @param Model|array $record Record to be saved.
 */
function save($record)
{
	if ($record instanceof Model) {
		$model = $record;
	}
	else {
		$model = new Model($this->params['table'], $record);
	}

	$foreignKey = $this->params['key'];

	switch ($this->getType()) {
		case 'one':
		case 'many':
			$model->setValue($foreignKey, $this->model->getPrimaryId());
			return $model->save();
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
	$primaryKey = $this->model->getPrimaryId();

	// if (!$primaryKey) { //problem in whereJoin()
	// 	throw new Exception('Primary key is not defined.');
	// }

	switch ($this->getType()) {
		case 'one':
		case 'many':
			$this->from($table)->where(array($foreignKey => $primaryKey));
			break;
		case 'owner':
			$this->from($table)->where(array('ID' => $this->model->getValue($foreignKey)));
			break;
		case 'many_to_many':
			$joinTable = $this->getJoinTableName();
			list($k1,$k2) = explode(',', $foreignKey);

			$this->from($table);
			if(!isset($this->query['whereJoin'])) $this->query['whereJoin'] = array();
			$this->query['whereJoin'][] = paramStr(
				"EXISTS (SELECT * FROM {0} WHERE $table.ID={0}.$k2 and {0}.$k1='{1}')", 
				array($joinTable, $primaryKey)
			);
			break;
	}
}

}

?>