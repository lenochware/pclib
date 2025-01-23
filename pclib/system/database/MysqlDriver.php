<?php
/**
 * @file
 * PClib database driver.
 * Database drivers are stored in pclib/system/database/ directory and loaded by class db automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system\database;
use pclib\DatabaseException;
use pclib\Str;

/**
 * MySQL database driver.
 * Implements support of %mysql database engine.
 */
class MysqlDriver extends AbstractDriver
{

public $extension = 'mysql';

function connect($ds)
{
	$ok = false;
	$port = $ds['port']? ':'.$ds['port'] : '';
	$res = @mysql_connect($ds['host'].$port, $ds['user'], $ds['passw'], $this->forceReconnect);
	if ($res) $ok = mysql_select_db($ds['dbname'], $res);
	if (!$ok) {
		$this->error = $this->lastError();
		$msg = $this->verboseErrors? ' '.$this->error : '';
		throw new DatabaseException('Connection error.'.$msg);
	}
	$this->connection = $res;
	return $this->connection;
}

function close()
{
	if (!$this->connection) return;
	mysql_close($this->connection);
	$this->connection = false;
}

function seek($res, $rowno)
{
	return mysql_data_seek($res, $rowno);
}

function getInsertId()
{
	if ($this->connection) return mysql_insert_id($this->connection);
	else return mysql_insert_id();
}

function numRows($res = null)
{
	if (!$res) $res = $this->res;
	return $res? mysql_num_rows($res) : 0;
}

function affectedRows($res = null)
{
	return mysql_affected_rows($this->connection);
}

function query($sql)
{
	$this->error = '';

	if ($this->limit) {
		$sql .= " LIMIT ".$this->limit[0]." OFFSET ".$this->limit[1];
		$this->limit = null;
	}

	if ($this->connection) $res = mysql_query($sql, $this->connection);
	else $res = mysql_query($sql);

	if (!$res) {
		$this->error = $this->lastError().' Query: '.$sql;
		$msg = $this->verboseErrors? ' '.$this->error : '';
		throw new DatabaseException('Query error.'.$msg);
	}
	$this->res = $res;
	return $res;
}

function fetch($res = null, $fmt = 'a')
{
	if (!$res) return array();
	switch ($fmt) {
		case 'f' : $row = @mysql_fetch_row($res);
							 return $row[0];
		case 'o' : return @mysql_fetch_object($res);
		case 'r' : return @mysql_fetch_row($res);
		case 'ar': return @mysql_fetch_array($res);
		case 'a' :
		default  : return @mysql_fetch_assoc($res);
	}
}

function lastError()
{
	if (!$this->connection) 
		return mysql_error().'(error '.mysql_errno().')';
	return mysql_error($this->connection).'(error '.mysql_errno($this->connection).')';
}

function codePage($cp)
{
	$this->query("SET NAMES '$cp'");
	return $this->error? false:true;
}

function dbName()
{
	$q = $this->query("select DATABASE()");
	return $this->fetch($q,'f');
}

function indexes($table)
{
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	if (!$dbname) $dbname = $this->dbName();

	$q = $this->query(
	"SELECT * FROM information_schema.statistics
	WHERE table_schema='$dbname'
	AND table_name='$table'
	ORDER BY INDEX_NAME,SEQ_IN_INDEX"
	);

	$indexes = array();
	while ($row = $this->fetch($q, 'o')) {
		if ($row->INDEX_NAME != $name) {
			$name = $row->INDEX_NAME;
			$indexes[$name] = array(
			'name' => $name,
			'type' => $row->INDEX_TYPE,
			'nullable' => ($row->NULLABLE == 'YES'),
			'unique' => ($row->NON_UNIQUE != 1),
			'comment' => $row->COMMENT,
			);
		}
		$indexes[$name]['columns'][] = $row->COLUMN_NAME;
	}
	return $indexes;
}

function columns($table)
{
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	if (!$dbname) $dbname = $this->dbName();

	$q = $this->query(
	"SELECT * FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = '$table'
	AND TABLE_SCHEMA='$dbname'"
	);
	
	$columns = array();
	while ($row = $this->fetch($q, 'o')) {
		$type = $this->u_type($row->DATA_TYPE, $row->CHARACTER_MAXIMUM_LENGTH);

		$columns[$row->COLUMN_NAME] = array(
		'name' => $row->COLUMN_NAME,
		'type' => $type[0],
		'size' => $type[1],
		'nativetype' => $row->DATA_TYPE,
		'nullable' => ($row->IS_NULLABLE == 'YES'),
		'default' => $row->COLUMN_DEFAULT,
		'autoinc' => ($row->EXTRA == 'auto_increment'),
		'comment' => $row->COLUMN_COMMENT,
		);
	}
	return $columns;
}

function version()
{
	$q = $this->query('select version()');
	$row = $this->fetch($q, 'r');
	return $row[0];
}

private function u_type($type, $size)
{
	$native = array(
	'tinyint' => 'int:1','smallint' => 'int:2','mediumint' => 'int:3','int' => 'int:4',
	'integer' => 'int:4','bigint' => 'int:8', 'float' => 'float:4','double' => 'float:8',
	'date' => 'date:1','datetime' => 'date:2','timestamp' => 'date:2','char' => 'string',
	'varchar' => 'string','tinyblob' => 'binary','tinytext' => 'string','blob' => 'binary',
	'text' => 'string','mediumblob' => 'binary','mediumtext' => 'string',
	'longblob' => 'binary','longtext' => 'string','bool'=>'bool','boolean'=>'bool',
	);
	$type = $native[$type];
	if (!$type) return array(null, $size);
	$type = explode(':', $type);
	if (!$type[1]) $type[1] = $size;
	return $type;
}

function quoteIdent($str)
{
	return "`".Str::id($str)."`";
}

function quote($str)
{
	return "'".mysql_real_escape_string ($str, $this->connection)."'";
}

function escape($str, $type = 'string')
{
	if ($type == 'ident') return $this->quoteIdent($str);
	if (!$str or is_numeric($str)) return $str;

	if ($this->connection)
		return mysql_real_escape_string ($str, $this->connection);
	else
		return mysql_escape_string ($str);
}


} //class

?>