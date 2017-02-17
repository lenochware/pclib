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

namespace pclib;
use pclib;

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
class Selection implements \Iterator {

protected $app;

/** var Db */
protected $db;

/** Array of sql query clausules. */
protected $query = array();

/** Result of underlying sql query. */
protected $result = null;

/** Data of the current row. */
protected $data = array();

protected $position = 0;

protected $cachedTemplate;

function __construct(App $app)
{
	$this->app = $app;
	$this->db = $this->app->getService('db');
	$this->reset();
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
	$model = $this->app->newModel($this->query['from']);

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

protected function getModelName()
{
	return $this->app->getClassName($this->query['from'], 'model');
}

/**
 * PHP magic method.
 * Redirect unknown method call to underlying model class.
 */
public function __call($name, $args)
{
	$modelClass = $this->getModelName();
	array_unshift($args, $this);
	$methodName = 'select'.ucfirst($name);
	return call_user_func_array(array($modelClass, $methodName), $args); 
	//parent::__call($name, $args);
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

function isEmpty()
{
	if (!$this->query['from']) return true;
	$rows = $this->getClone()->limit(1)->select('*');
	return !$rows;
}

function count()
{
	if (!$this->query['from']) return 0;
	$rows = $this->getClone()->select('count(*) as n');
	return (int)$rows[0]['n'];
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

function update($values)
{
	foreach ($this as $model) {
		$model->setValues($values);
		$model->save();
	}
}


function delete()
{
	foreach ($this as $model) {
		$model->delete();
	}
}

function getClone()
{
	return clone $this;
}

/*

function first($n = 1) {
}

//totez co toArray()?
function all() {
}
*/

/**
 * Execute query to the database and set $this->result.
 * @return $result
 */
protected function execute()
{
	$this->result = $this->db->query($this->getSql());
	$this->position = 0;
	$this->data = array();

	//$this->reset();
	return $this->result;
}

/**
 * Set selection limit. Fluent interface.
 * @return Selection $this
 */
function limit($limit, $offset = 0)
{
	$this->query['limit'] = array($limit, $offset);
	return $this;
}

//selectRaw, raw, getRaw, selectRow?
function select($columns)
{
	$this->query['select'] = is_array($columns)? $columns : explode(',', $columns);
	$this->execute();
	$rows = $this->db->fetchAll($this->result);
	$this->reset();
	return $rows;
}

/**
 * Set source table $s. Fluent interface.
 * @return Selection $this
 */
function from($s)
{
	$this->query['from'] = $s;
	$this->cachedTemplate = null;
	return $this;
}

/**
 * Set where condition. Fluent interface.
 * @return Selection $this
 */
function where($s)
{
	if(!isset($this->query['where'])) $this->query['where'] = array();
	if (is_array($s)) $s = $this->createFieldList(' AND ', $s);
	$this->query['where'][] = $s;
	return $this;
}

/**
 * Set order by clausule. Fluent interface.
 * @return Selection $this
 */
function order($s)
{
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
	$this->query['group'] = $s;
	return $this;
}

/**
 * Set having clausule. Fluent interface.
 * @return Selection $this
 */
function having($s)
{
	if(!isset($this->query['having'])) $this->query['having'] = array();
	if (is_array($s)) $s = $this->createFieldList(' AND ', $s);
	$this->query['having'][] = $s;
	return $this;
}

/**
 * Reset selection. Remove all conditions and loaded data.
 * @return Selection $this
 */
function reset()
{
	$this->query = array('select' => array('*'));
	$this->position = 0;
	$this->data = array();  
	$this->result = null;
	return $this;
}

/**
 * Build sql query for current selection.
 * @return string $sql
 */
function getSql()
{
	extract($this->query, EXTR_SKIP);
	
	if (!$select or !$from)
		throw new SqlQueryException('Invalid command.');
	
	$sql = 'SELECT '.implode(',', $select).' FROM '.$from;
	if ($where)  $sql .= ' WHERE '.implode(' AND ', $where);
	if ($group)  $sql .= ' GROUP BY '.$group;
	if ($having) $sql .= ' HAVING '.implode(' AND ', $having);
	if ($order)  $sql .= ' ORDER BY '.implode(',', $order);
	if ($limit)  $sql .= ' LIMIT '.$limit[0].' OFFSET '.$limit[1];
	return $sql;
}

protected function createFieldList($separ, array $fieldsArray)
{
	//escape keys?
	foreach($fieldsArray as $k => $v) {
		$output[] = $k.'='.$this->escape($v);
	}
	return implode($separ, $output);
}

function escape($s) {
	return "'".$this->db->escape($s)."'";
}

/**
 * Return current selection as array.
 * @return array $rows Array of models.
 */
function toArray()
{
	$rows = array();
	foreach ($this as $model) {
		$rows[] = $model;
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
	} catch (Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}

	//return json_encode($this->toArray());
}

}

?>