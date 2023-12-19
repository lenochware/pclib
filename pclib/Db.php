<?php
/**
 * @file
 * %PClib database layer.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Simple database wrapper.
 * Features:
 * - Simplify common queries with select(), insert(), update(), count() etc.
 * - Access to metadata: index(), columns()
 * - Mass functions: selectAll(), insertAll(), runDump()
 * - Dynamic SQL for building parametrized queries. See \ref dynamic-sql
 * - SQL injection protection
 * - Drivers for different database engines (mysql, pgsql, sqlite, pdo_mysql and pdo_pgsql)
 */
class Db extends system\BaseObject implements IService
{
/** If enabled, no query is executed. */
public $disabled = false;

/** SQL of last executed query. */
public $lastQuery;

/** Create new connection even if connection with same params exists */
public $forceReconnect = true;

/** Log queries slower than 1s - 0: do not log anything */
public $slowQueryLog = 1.0;

public $LOOKUP_TAB = 'LOOKUPS';

public $info = [];

/** var AbstractDriver Database driver */
public $drv;

/** var App */
protected $app;

protected $config;

protected $SQL_PARAM_PATTERN = "/{([#\?\!]?)([a-z0-9_]+)}/i";

private $dataSource;

/**
 * Establish database connection.
 *
 * @param string|array $dataSource Format: 'driver://user:passw@host/database'
 * @see connect()
**/
function __construct($dataSource = null)
{
	global $pclib;
	parent::__construct();
	
	$this->app = $pclib->app;
	$this->config = $this->app->config;

	if (isset($dataSource)) $this->connect($dataSource);
}

//parse connection string to array
protected function parseDsn($dsn)
{
	if (stripos($dsn,'pdo_') === 0) {
		$pdo = true;
		$dsn = substr($dsn, 4);
	}
	else {
		$pdo = false;
	}
	
	$dsa = parse_url($dsn);
	if (!isset($dsa['scheme'])) return [];

	$path = explode('/', $dsa['path']);
	if (!$path[0]) array_shift($path);

	$dsarray = array(
	 'driver' => $dsa['scheme'],
	 'host'   => $dsa['host'],
	 'port'   => array_get($dsa, 'port'),
	 'path'   => substr($dsa['path'],1),
	 'dbname' => $path[0],
	 'user'   => $dsa['user'],
	 'passw'  => array_get($dsa, 'pass'),
	 'codepage' => isset($path[1])? $path[1] : null
	);

	//new way of adding options e.g. ?charset=utf8
	if (!empty($dsa['query'])) parse_str($dsa['query'], $dsarray['options']);

	if ($dsarray['driver'] == 'sqlite') $dsarray['codepage'] = null;
	if ($dsarray['codepage']) $dsarray['options']['charset'] = $dsarray['codepage'];

	if ($pdo) $dsarray['driver'] = 'pdo_'. $dsarray['driver'];
	return $dsarray;
}

/**
 * Establish database connection.
 *
 * Format of $datasource can be: 
 * - 'driver://user:passw@host/database'
 * - 'driver://user:passw@host/database?parameters'
 * - array with keys: driver, host, dbname, user, passw
 *
 * @param string|array $dataSource
**/
function connect($dataSource)
{
	if (empty($dataSource)) {
		throw new \InvalidArgumentException('Invalid connection parameters.');
	}

	$dsarray = [];

	if(is_string($dataSource)) {
		$dsarray = $this->parseDsn($dataSource);
	}
	elseif (is_array($dataSource) and array_key_exists ('driver', $dataSource)) {
		$dsarray = $dataSource;
	}

	if (!$dsarray) throw new \InvalidArgumentException('Invalid connection parameters.');
	
	$this->dataSource = $dataSource;	

	$drvname = pcl_ident($dsarray['driver']);
	
	if (strpos($drvname, 'pdo_') === 0) {
		$className = '\\pclib\\system\\database\\Pdo'.ucfirst(substr($drvname, 4)).'Driver';
	}
	else {
		$className = '\\pclib\\system\\database\\'.ucfirst($drvname).'Driver';
	}

	if (!class_exists($className)) {
		throw new DatabaseException("Database driver '%s' not found.", array($dsarray['driver']));
	}

	$this->drv = new $className;
	$this->drv->verboseErrors = in_array('develop', $this->config['pclib.errors']);
	$this->drv->forceReconnect = $this->forceReconnect;
	$this->drv->connect($dsarray);
	if (isset($dsarray['options']['charset'])) $this->drv->codePage($dsarray['options']['charset']);

	unset($dsarray['passw']);
	$this->info = $dsarray;
}

/**
 * Close database connection.
**/
function close()
{
	$this->drv->close();
	$this->info = [];
}

function __clone()
{
	$this->connect($this->dataSource);
}


/**
 * Set connection character set.
**/
function codePage($cp)
{
 return $this->drv->codePage($cp);
}

/** Seek resource $res to $rowno. */
function seek($res, $rowno)
{
	return $this->drv->seek($res, $rowno);
}

/**
 * The portable way to perform limit.
 * Next query result will be limited according setlimit parameters.
 * @param int $numrows number of rows in result
 * @param int $offset offset from which result started
**/
function setLimit($numrows, $offset = 0)
{
	$this->drv->setLimit($numrows, $offset);
}

/**
 * Perform database query with parameters - return result resource.
 * You can use \ref dynamic-sql. See \ref db-params
 * @param string $_sql sql-query
 * @param array $param query parameters.
 * @return resource $result
**/
function query($_sql, $param = null)
{
	$res = null;
	
	if (isset($param) and !is_array($param)) {
		$param = func_get_args();
		array_shift($param);
	}
	$sql = $param? $this->setParams($_sql, $param) : $_sql;
	
	$event = $this->trigger('db.before-query', ['sql' => $sql]);
	if ($event and !$event->propagate) return;

	$tm = microtime(true);

	if (!$this->disabled) {
		$res = $this->drv->query($sql);
	}
		
	$this->lastQuery = $this->drv->error? $this->drv->error : $sql;

  $this->trigger('db.after-query', ['sql' => $sql, 'query' => $res]);

  $tmEnd = microtime(true);
  if ($this->slowQueryLog and $tmEnd - $tm > $this->slowQueryLog) {
  	if (!strpos($this->lastQuery, 'LOGGER')) { //avoid recursion
  		$this->app->log('db', 'slow-query', $this->lastQuery . sprintf(" (%01.2fs)", $tmEnd - $tm));
  	}	
  }

	return $res;
}

/**
 * Perform SELECT query and return value of one database field.
 * @copydoc shortcut-select
 * @return value $field
**/
function field($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$this->setLimit(1);
	$res = $this->query($sql);
	$data = $this->drv->fetch($res, 'r');
	if (!$data) return null;
	return $data[0];
}

/**
 * Perform SELECT query and return one row of result as assoc-array.
 * @copydoc shortcut-select
 * @return array $row
**/
function select($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$this->setLimit(1);
	$res = $this->query($sql);
	$tabfld = (!strpos($dsstr,' ') and strpos($dsstr,':')); //list() hack
	return $this->drv->fetch($res, $tabfld? 'ar':'a');
}

/**
 * Perform SELECT query and return ALL rows of result as assoc-array.
 * @copydoc shortcut-select
 * @return array $result = array ($row0_array, $row1_array, $row2_array, ...)
**/
function selectAll($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$res = $this->query($sql);
	return $this->fetchAll($res);
}

/**
 * Perform SELECT query, return first column of result as indexed array.
 * @copydoc shortcut-select
 * @return array $column. Ex: selectOne('PERSONS:NAME')
 * will return array('John', 'Jack', ...)
**/
function selectOne($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$res = $this->query($sql);
	$rows = array();
	while ($row = $this->drv->fetch($res, 'r')) {$rows[] = $row[0];}
	return $rows;
}

/**
 * Perform SELECT query, return first and second column
 * as associative array (lookup query).
 * @copydoc shortcut-select
 * @return array $lookup. Ex: selectPair('PERSONS:NAME,MONEY')
 *  will return array('John' => 12000, 'Jack' => 200, ...)
**/
function selectPair($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$res = $this->query($sql);
	
	return $this->fetchPair($res);
}

/**
 * Perform INSERT query, return inserted ID.
 * See \ref db-params.
 * @param string $tab Table name
 * @param array|string $data assoc-array of 'FIELDNAME' => 'FIELDVALUE' pairs.
 * @return int $inserted_id
**/
function insert($tab, $data)
{
	if (!is_array($data) and get_class($this->drv) != 'mysql')
		$data = $this->parseSetClausule($data);
		
	if (is_array($data)) {
		$sep = '';
		$kstr = $vstr = '';
		foreach($data as $k => $v) {
			$kstr .= $sep.$this->escape($k, 'ident');
			if (is_null($v)) $vstr .= $sep."NULL";
			else $vstr .= $sep."'".$this->escape($v)."'";
			$sep = ',';
		}

		$sql = "INSERT INTO $tab ($kstr) VALUES ($vstr)";
	}
	else {
		 $sql = "INSERT INTO $tab SET $data";
	}
	
	$res = $this->query($sql);
	return $this->drv->getInsertId($res);
}

/** Insert multiple rows */
function insertAll($tab, array $data)
{
	foreach($data as $record) {
		$this->insert($tab, $record);
	}
}

/**
 * Update or insert when key does not exists.
 * @param string $tab Table name
 * @param array $data assoc-array of 'FIELDNAME' => 'FIELDVALUE' pairs.
 * @param array $key Found record for update with $key fields
 * @return int $inserted_id|false
**/
function insertUpdate($tab, array $data, array $key = ['ID'])
{
	$filter = array_intersect_key($data, array_flip($key));

	$found = $this->select($tab, $filter);

	if ($found) {
		$this->update($tab, $data, $filter);
		return false;
	}
	else {
		return $this->insert($tab, $data);
	}
}

/**
 * Run database dump file $fileName.
 * @return int Number of executed queries.
**/
function runDump($fileName, $skipErrors = false)
{
	if (!is_file($fileName))
		throw new FileNotFoundException("File '$fileName' not found.");

	set_time_limit(0);
	$sql = '';
	$err = $n = 0;
	$f = fopen($fileName, 'r');
	while (($line = fgets($f, 4096)) !== false) {
		if (strpos($line, '--') === 0) continue;
		$sql .= $line;
		if (preg_match('/;\s*$/iS', $line)) {
			try {  $this->query($sql);  }
			catch (Exception $e) {
				$err++;
				if (!$skipErrors) throw $e;
			}
			$n++;
			$sql = '';
		}
	}
	fclose($f);
	if ($err) throw new DatabaseException("Dump '$fileName': $err of $n queries failed.");
	return $n;
}

/**
 * Perform REPLACE query, return inserted ID.
 * See \ref db-params.
 * @param string $tab Table name
 * @param array|string $data assoc-array of 'FIELDNAME' => 'FIELDVALUE' pairs.
 * @return int $inserted_id
**/
function replace($tab, $data)
{
	if (!in_array($this->drv->extension, array('mysql', 'pdo_mysql'))) {
		throw new NotImplementedException;
	}
	
	if (is_array($data)) {
		$sep = '';
		foreach($data as $k => $v) {
			$kstr .= $sep.$this->escape($k, 'ident');
			if (is_null($v)) $vstr .= $sep."NULL";
			else $vstr .= $sep."'".$this->escape($v)."'";
			$sep = ',';
		}
		
		$sql = "REPLACE INTO $tab ($kstr) VALUES ($vstr)";
	}
	else {
		$sql = "REPLACE INTO $tab SET $data";
	}

	$res = $this->query($sql);
	return $this->drv->getInsertId($res);
}

/**
 * Perform UPDATE query.
 * See \ref db-params.
 * @param string $tab Table name
 * @param array|string $data array of values.
 * @param string $cond where condition (required).
 * @return bool $success.
 * @see insert()
**/
function update($tab, $data, $cond)
{
	$fields = '';
	if (is_array($data)) {
		$sep = '';
		foreach($data as $k => $v) {
			//if ($k == '' or $v == '') continue;
			if (is_null($v)) $v = 'NULL'; else $v = "'".$this->escape($v)."'";
			$fields .= $sep.$this->escape($k, 'ident')."=$v";
			$sep = ',';
		}
	}
	else $fields = $data;
	
	$args = (func_num_args() > 3)? array_slice(func_get_args(),3) : null;
	$where = $this->getWhereSql($cond, $args);
	$sql = "UPDATE $tab set $fields WHERE $where";
	$res = $this->query($sql);
	return $res;
}

/**
 * Perform DELETE query.
 * See \ref db-params.
 * @param string $tab Table name
 * @param string $cond where condition (required)
 * @return bool $success
 * @see insert()
**/
function delete($tab, $cond)
{
	$args = (func_num_args() > 2)? array_slice(func_get_args(),2) : null;
	$where = $this->getWhereSql($cond, $args);

	$sql = "DELETE FROM $tab WHERE $where";
	$res = $this->query($sql);
	return $res;
}

/**
 * Return number of rows in query result / table. Without parameters it returns
 * number of rows in last query. For using with parameters see examples.
 *
 * Ex:
 * - count('TABLE') //return number of rows in table
 * - count($res)  //return no. of rows in query result $res
 * - count() //return no. of rows in last query
 * - count('PERSONS', 'MONEY>100') //perform "select count(*) from PERSONS where MONEY>100"
 * - count('select ID from T where F>10') //return no. of rows of the query
 *
 * @param string $dsstr Datasource string
 * @param string $cond where condition
 * @return int $num Number of rows
**/
function count($dsstr = null)
{
	if (!$dsstr) return $this->drv->numRows();
	elseif (/*is_resource*/!is_string($dsstr)) return $this->drv->numRows($dsstr);
	elseif ($this->isSql($dsstr, 'select')) {
		$args = func_get_args();
		$sql = $this->getSelectSql($dsstr, $args);
		return $this->field("select count(*) from ($sql) as Q");
	}
	elseif (!strpos($dsstr,' ')) {
		$dsstr = "$dsstr:count(*)";
		$args = func_get_args();
		$sql = $this->getSelectSql($dsstr, $args);
		return $this->field($sql);
	}
	else throw new \InvalidArgumentException;
}

/**
 * Return true if some row which is satisfying condition exists.
 * See select() for possible parameters. See \ref db-params.
 *
 * Ex: if($db->exists('PERSONS', 'MONEY>1000')) ...
 *
 * @param string $dsstr Datasource string
 * @param string $cond where condition
 * @return bool $found
 * @see select()
**/
function exists($dsstr)
{
	$args = func_get_args();
	$sql = $this->getSelectSql($dsstr, $args);
	$this->setLimit(1);
	$res = $this->query($sql);
	return $this->drv->numRows($res);
}

/**
 * Fetch one row from query result. If result is ommited, it uses last query.
 * @param resource $res Query result resource
 * @param string $fmt format of result a - assoc, o - object, r - row, ar - array, f - field
 * @return array|object $row result row
**/
function fetch($res = null, $fmt = 'a')
{
	return $this->drv->fetch($res, $fmt);
}

/**
 * Fetch ALL rows from query result.
 * @param resource $res Query result resource
 * @param string $fmt format of result
 * @return array $rows = array($row_0, $row_1, $row_2, ...)
 * @see fetch()
**/
function fetchAll($res = null, $fmt = 'a')
{
	$rows = array();
	while ($row = $this->drv->fetch($res, $fmt)) {
		$rows[] = $row;
	}
	return $rows;
}

function fetchPair($res = null)
{
	$rows = []; 
	$n = 0;
	
	while ($row = $this->drv->fetch($res, 'r')) {
		if (!$n) $n = count($row);
		if ($n == 1) $rows[] = $row[0];
		elseif($n == 2) $rows[$row[0]] = $row[1];
		else {
			$id = array_shift($row);
			$rows[$id] = $row;
		}
	}

	return $rows;
}

/**
 * Begins a transaction.
 */
public function beginTransaction()
{
	$this->query('START TRANSACTION');
}
/**
 * Commits statements in a transaction.
 */

public function commit()
{
	$this->query('COMMIT');
}

/**
 * Rollback changes in a transaction.
 */
public function rollback()
{
	$this->query('ROLLBACK');
}

/** Return current database name */
function dbName()
{
	return $this->drv->dbName();
}

/**
 * Return columns metadata (name,size,type,nullable,default) of table $table as associative array.
 * @return array $columns
 */
function columns($table)
{
	return $this->drv->columns($table);
}

/**
 * Return indexes for table $table.
 * @return array $indexes
 */
function indexes($table)
{
	return $this->drv->indexes($table);
}

function tableName($dsstr)
{
	if (!strpos($dsstr, ' ')) {
		$t = explode(':', $dsstr);
		$table = $t[0];
	}
	else {
		preg_match("/from\s+(\w+)/i", $dsstr, $found);
		$table = $found[1];
	}
	return $table;
}

/**
 * Export query result to format $fmt. Only html is supported now.
 * @param string|resource $dba Resource or $dsstr string - see select() for details
 * @param string $fmt export format ('html' or 'html/vertical')
 * @return string Query result in format $fmt
 * @see select()
**/
function export($dba, $fmt = 'html')
{
	if (!$dba) return '';
	if (is_resource($dba)) $dba = $this->fetchAll($dba);
	if (is_string($dba)) $dba = $this->selectAll($dba);
	if (is_array($dba) and !is_array(array_get($dba, 0))) $dba = array($dba);
	
	switch ($fmt) {
	case 'html':
		$html = '<TABLE class="db" border="1">';
		$hdr = array_keys($dba[0]);
		$str = ''; 
		foreach ($hdr as $h) {$str .= "<th>$h</th>";}
		$html .= "\n<tr>$str</tr>\n";
		$str = ''; 
		foreach($dba as $i => $row) {
			foreach($row as $cell) {$str .= "<td>$cell</td>";}
			$html .= "<tr>$str</tr>\n";
			$str = '';
		}
		$html .= "</TABLE>";
		return $html;

	case 'html/vertical':
		$html = '';
		foreach($dba as $row) {
			$html .= '<TABLE class="db" border="1">';
			foreach($row as $flname => $cell) {
				$html .= "<tr><th>$flname</th><td>$cell</td></tr>\n";
			}
			$html .= "</TABLE>\n\n";
			$str = '';
		}
		return $html;
	}
}

/** Return lookup table as array.
 *  \param string $lkpname Lookup table name
 *  \return array $items Lookup table items
 */
function getLookup($lkpName)
{
	global $pclib;
 if ($pclib->app) $appflt = "AND (APP='".$pclib->app->name."' or APP is null)";
 $sql = sprintf(
	 "select ifnull(id,guid), label from %s
	 where cname='%s' %s order by position,label",
	 $this->LOOKUP_TAB, $lkpName, $appflt);
	
	$items = $this->selectPair($sql);

	return $items;
}

/**
 * Escapes string for use in database query.
 * @note If you use {param} parameters in sql, it will be escaped automatically.
 * @param string $str String (parameter) used in sql query
 * @return string $str escaped string
**/
function escape($str, $type = 'string')
{
	return $this->drv->escape($str, $type);
}

/**
 * Place $params into $sql and return result string.
 * See query() for details about $sql and $param format.
 * @param string $sql SQL-query string
 * @param array $params Parameters
 * @return string $sql
 * @see query()
**/
function setParams($sql, $params)
{
	$has_params = false;
	
	$pat = $this->SQL_PARAM_PATTERN;
	if (strpos($sql, '{')) $has_params = true;
	if (!$has_params) return $sql;
	
	preg_match_all($pat, $sql, $found);

	if (!$found[0]) return $sql;
	$from = $to = array();
	$empty = false;
	foreach($found[2] as $i => $key)
	{
		$from[] = $found[0][$i];
		$value = array_get($params, $key);

		if (is_array($value)) {
			if ($value === []) {
				$empty = true;
				if ($found[1][$i] == '#') $to[] = '__PCL_EMPTY0__';
				else $to[] = '__PCL_EMPTYS__';
			}
			elseif ($found[1][$i] == '#')
				$to[] = implode(',', array_map('intval', $params[$key]));
			elseif($found[1][$i] == '?')
				$to[] = '';			
			else
				$to[] = implode("','", array_map([$this, 'escape'], $params[$key]));
		}
		else {
			if (strlen((string)$value) == 0) {
				$empty = true;
				if ($found[1][$i] == '#') $to[] = '__PCL_EMPTY0__';
				else $to[] = '__PCL_EMPTYS__';
			}
			elseif ($found[1][$i] == '#')
				$to[] = (int)$value;
			elseif($found[1][$i] == '?')
				$to[] = '';
			elseif($found[1][$i] == '!')
				$to[] = $value;
			else
				$to[] = $this->escape($value);
		}
	}
	
	$sql = str_replace($from, $to, $sql);
	if (!strpos($sql, "\n") and !$empty) return $sql;
	
	$from = array("/^\s*~ .+__PCL_EMPTY.__.*$/m", "/^\s*~ /m", "/__PCL_EMPTYS__/", "/__PCL_EMPTY0__/");
	$to = array('',' ','', '0');
	return preg_replace($from, $to, $sql);
}

/** Helper for building SELECT sql
 * @see select()
 */
protected function getSelectSql($dsstr, $args)
{
	array_shift($args);
	$dsstr = trim($dsstr);
	if(!strpos($dsstr,' ')) {
		if (strpos($dsstr,':')) list($tab, $flds) = explode(':', $dsstr);
		else {$tab = $dsstr; $flds = '*';}
		$sql = "select $flds from $tab";
		if (isset($args[0])) {
			$sql .= " where ".$this->getWhereSql($args[0], null); array_shift($args); 
		}
	}
	elseif ($this->isSql($dsstr, 'select') or $this->isSql($dsstr, '(select')) $sql = $dsstr;
	else throw new \InvalidArgumentException('Only SELECT query allowed.');
	
	$params = null;
	if (count($args)) {
		if (is_array($args[0])) $params = $args[0]; else $params = $args;
	}

	if ($params) $sql = $this->setParams($sql, $params);
	return $sql;
}

/** Helper for building WHERE clausule of sql query
 * @see update()
 */
protected function getWhereSql($cond, $args)
{
	if (is_numeric($cond)) throw new \InvalidArgumentException;
	if (is_array($cond)) {
		$s = '';
		foreach($cond as $k => $v) {
			if (is_array($v)) {
				$v = implode("','", array_map([$this, 'escape'], $v));
				$s .= " AND ".$this->escape($k,'ident')."in ('$v')";
			}
			else {
				$s .= " AND ".$this->escape($k,'ident')."='".$this->escape($v)."'";
			}
		}
		$where = substr($s, 5);
	}
	elseif (is_string($cond)) {
		if ($args) {
			if (is_array($args[0])) $params = $args[0]; else $params = $args;
			$where = $this->setParams($cond, $params);
		}
		else $where = $cond;
	}
	else throw new \InvalidArgumentException;
	return $where;
}

/** Convert string "A=B,C=D,..." to array(A=>B,C=>D,...) */
private function parseSetClausule($sql)
{
	preg_match_all("/([a-z0-9_]+)\s*=\s*?([^,]+)?/i", $sql, $found);
	$data = array();
	foreach($found[1] as $i => $key) {
		$data[$key] = trim($found[2][$i]);
		if (strcasecmp($data[$key], 'null') == 0) $data[$key] = null;
		if ($data[$key][0] == "'" and substr($data[$key],-1) == "'")
			$data[$key] = substr($data[$key],1,-1);
	}
	return $data;
}

/** True if $dsstr is SQL string (of type $type) */
protected function isSql($dsstr, $type = '')
{
	if (!is_string($dsstr)) return false;
	$dsstr = trim($dsstr);
	if (!strpos($dsstr, ' ')) return false;
	if (!$type) return true;
	$dstype = strtolower(substr($dsstr, 0, strpos($dsstr,' ')));
	return ($dstype == $type);
}

}

?>