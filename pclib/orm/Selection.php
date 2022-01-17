<?php
/**
 * @file
 * Selection of records in database.
 * @author -dk-
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\orm;
use pclib\system\BaseObject;

/**
 * Selection of records in database.
 * It represents any selection on database table and will return Model instances as records.
 * It does not load records from the database before they are really requested.
 *
 * Features:
 * - Fluent interface: $sel->from('PEOPLE')->order('SURNAME desc');
 * - Using in foreach: foreach ($sel as $person) {..}
 * - Return models: $person1 = $sel->from('PEOPLE')->find(1); print $person1->posts;
 */
class Selection extends BaseObject implements \Iterator {

/** var Db */
public $db;

/** Array of sql query clausules. */
protected $query = array();

/** Result of underlying sql query. */
protected $result = null;

/** Data of the current row. */
protected $data = array();

protected $position = 0;

protected $cachedTemplate;

function __construct()
{
	parent::__construct();
	$this->service('db');
	$this->clear();
}

/** Iterator.rewind() implementation. */
function rewind()
{
	$this->execute();
	$this->next();
}

/** Iterator.current() implementation. */
function current()
{
	return $this->valid()? $this->newModel($this->data) : null;
}

/** Iterator.key() implementation. */
function key()
{
	return $this->position;
}

/** Iterator.next() implementation. */
function next()
{
	$data = $this->db->fetch($this->result);
	if ($data === false) {
		$this->result = null;
		$this->position = 0;
	}

	$this->data = $data;
	$this->position++;
}

/** Iterator.valid() implementation. */
function valid()
{
	return ($this->result !== null);
}

/**
 * Create model instance, fill its values with $data and return it.
 * @return Model $model
 */
protected function newModel($data)
{
	$className = Model::className($this->query['from']);
	$model = new $className($this->query['from']);

	if (!$this->cachedTemplate) {
		$this->cachedTemplate = $model->getTemplate();
	}

	$model->setTemplate($this->cachedTemplate);

	if ($data) {
		$model->setValues($data);
		$model->isInDb(true);
	}

	return $model;
}

/**
 * PHP magic method.
 * Redirect unknown method call to underlying model class.
 */
public function __call($name, $args)
{
	if (empty($this->query['from'])) {
		return parent::__call($name, $args);
	}

	$modelClass = Model::className($this->query['from']);
	$methodName = 'select'.ucfirst($name);
	if (method_exists($modelClass, $methodName)) {
		array_unshift($args, $this);
		return call_user_func_array(array($modelClass, $methodName), $args); 
	}
	else {
		return parent::__call($name, $args);
	}
}

/**
 * Return first record in the selection.
 * @return Model $model
 */
function first()
{
	$this->rewind();
	return $this->current();
}

/**
 * Is Selection empty?
 * @return bool $isEmpty
 */
function isEmpty()
{
	if (!$this->getSql()) return true;
	$rows = $this->getClone()->limit(1)->select('*');
	return !$rows;
}

/**
 * Return number of rows in the Selection.
 */
function count()
{
	if (!$this->getSql()) return 0;
	$rows = $this->getClone()->select('count(*) as n');
	return (int)$rows[0]['n'];
}

/**
 * Return summary of field $s.
 * @TODO: verify if $s is valid fieldname.
 */
function sum($s)
{
	if (!$this->getSql()) return 0;
	$rows = $this->getClone()->select("sum($s) as n");
	return +$rows[0]['n'];
}

/**
 * Return AVG of field $s.
 * @TODO: verify if $s is valid fieldname.
 */
function avg($s)
{
	if (!$this->getSql()) return 0;
	$rows = $this->getClone()->select("avg($s) as n");
	return +$rows[0]['n'];
}



/**
 * Find record by primary key.
 * @return Model $model
 */
function find($id)
{
	$model = $this->newModel(null);
	return $model->find($id);
}

/**
 * Update records in selection with $values.
 */
function update(array $values)
{
	foreach ($this as $model) {
		$model->setValues($values);
		$model->save();
	}
}


/**
 * Delete selection.
 */
function delete()
{
	foreach ($this as $model) {
		$model->delete();
	}
}

/**
 * Clone selection.
 */
function getClone()
{
	$sel = clone $this;
	$sel->close();
	return $sel;
}

/*

function first($n = 1) {
}

*/

protected function tryModify()
{
	if ($this->result) {
		throw new \pclib\Exception('Cannot modify open selection.');
	}
}

/**
 * Execute query to the database and set $this->result.
 * @return $result
 */
protected function execute()
{
	$this->result = $this->db->query($this->getSql());
	$this->position = 0;
	$this->data = array();

	return $this->result;
}

/**
 * Set selection limit. Fluent interface.
 * @return Selection $this
 */
function limit($limit, $offset = 0)
{
	$this->tryModify();
	$this->query['limit'] = array((int)$limit, (int)$offset);
	return $this;
}

/**
 * Execute selection and return array of rows.
 * @param array|string $columns List of columns to select
 * @return array $rows
 */
function select($columns)
{
	$this->tryModify();
	$this->query['select'] = is_array($columns)? $columns : explode(',', $columns);
	$this->execute();
	$rows = $this->db->fetchAll($this->result);
	$this->close();
	$this->query['select'] = array('*');
	return $rows;
}

/**
 * Set source table $s. Fluent interface.
 * @return Selection $this
 */
function from($s)
{
	$this->tryModify();
	$this->query['from'] = $s;
	$this->cachedTemplate = null;
	return $this;
}

protected function setWhereParams($s, $args, $offset)
{
	if (is_array($s)) {
		return $this->createFieldList(' AND ', $s);
	}

	$args = array_slice($args, $offset);
	if (!$args) return $s;
	if (is_array($args[0])) $args = $args[0];
	return $this->db->setParams($s, $args);
}

/**
 * Set where condition. Fluent interface.
 * @return Selection $this
 */
function where($s)
{
	$this->tryModify();
	if(!isset($this->query['where'])) $this->query['where'] = array();
	$this->query['where'][] = $this->setWhereParams($s, func_get_args(), 1);
	return $this;
}

/**
 * Set where condition. Fluent interface.
 * @param $relName Name of relation
 * @param $s Condition used on relation
 * @return Selection $this
 */
function whereJoin($relName, $s)
{
	$this->tryModify();

	$rel = new Relation($this->newModel(null), $relName);

	$s = $this->setWhereParams($s, func_get_args(), 2);

	$table = $rel->params['table'];
	$join = $rel->getJoinCondition();

	if(!isset($this->query['whereJoin'])) $this->query['whereJoin'] = array();

	if ($rel->getType() == 'many_to_many') {
		$joinTable = $rel->getJoinTableName();
		$this->query['whereJoin'][] = "EXISTS (SELECT * FROM $table,$joinTable where $join and ($s))";
		return $this;
	}

	$this->query['whereJoin'][] = "EXISTS (SELECT * FROM $table WHERE $join AND ($s))";
	return $this;
}

/**
 * Set order by clausule. Fluent interface.
 * @return Selection $this
 */
function order($s)
{
	$this->tryModify();
	$args = func_get_args();
	if (is_array($args[0])) $args = $args[0];
	
	$this->query['order'] = $args;
	return $this;
}


/**
 * Set group by clausule. Fluent interface.
 * @return Selection $this
 */
function group($s)
{
	$this->tryModify();
	$this->query['group'] = $s;
	return $this;
}

/**
 * Set having clausule. Fluent interface.
 * @return Selection $this
 */
function having($s)
{
	$this->tryModify();
	if(!isset($this->query['having'])) $this->query['having'] = array();
	$this->query['having'][] = $this->setWhereParams($s, func_get_args(), 1);
	return $this;
}

/**
 * Closes the cursor, enabling the query to be executed again.
 * @return Selection $this
 */
function close()
{
	$this->position = 0;
	$this->data = array();
	$this->result = null;
	return $this;
}

/**
 * Clear selection query and data.
 * @return Selection $this
 */
function clear()
{
	$this->close();
	$this->query = array('select' => array('*'));
	return $this;
}

/**
 * Build sql query for current selection.
 * @return string $sql
 */
function getSql()
{
	extract($this->query, EXTR_SKIP);
	
	if (!$select or !$from) return '';
	
	$sql = 'SELECT '.implode(',', $select).' FROM '.$from;
	if (isset($where))  $sql .= ' WHERE '.implode(' AND ', array_unique($where));
	if (isset($whereJoin))  $sql .= ($where? ' AND ':' WHERE ').implode(' AND ', array_unique($whereJoin));
	if (isset($group))  $sql .= ' GROUP BY '.$group;
	if (isset($having)) $sql .= ' HAVING '.implode(' AND ', array_unique($having));
	if (isset($order))  $sql .= ' ORDER BY '.implode(',', $order);
	if (isset($limit))  $sql .= ' LIMIT '.$limit[0].' OFFSET '.$limit[1];
	return $sql;
}

protected function createFieldList($separ, array $fieldsArray)
{
	foreach($fieldsArray as $k => $v) {
		$output[] = $this->db->escape($k,'ident').'='.$this->escape($v);
	}
	return implode($separ, $output);
}

function escape($s) {
	return "'".$this->db->escape($s)."'";
}

/**
 * Return current selection as array.
 * @param bool $deep Return arrays instead of models
 * @return array $rows Array of models.
 */
function toArray($deep = false)
{
	$rows = array();
	foreach ($this as $model) {
		$rows[] = $deep? $model->toArray() : $model;
	}
	return $rows;
}

/**
 * Return string representation of selection for debugging purposes.
 */
function __toString()
{
	try {
		$s = 'Object.Selection<br>';
		$s .= $this->getSql().'<br>';
		foreach ($this->toArray() as $key => $value) {
			$s.= "$key: $value<br>";
		}
		return $s;
	} catch (\Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}

	//return json_encode($this->toArray());
}

}

?>