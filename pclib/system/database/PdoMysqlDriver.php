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
 * PDO mysql database driver.
 * Implements support of %mysql database engine.
 */
class PdoMysqlDriver extends PdoDriver
{

public $extension = 'pdo_mysql';

function connect($ds)
{
	$port = $ds['port']? ';port='.$ds['port'] : '';
	return $this->pdoConnect('mysql:dbname='.$ds['dbname'].';host='.$ds['host'].$port, $ds['user'], $ds['passw']);
}

function query($sql)
{
	$this->error = '';

	if ($this->limit) {
		$sql .= " LIMIT ".$this->limit[0]." OFFSET ".$this->limit[1];
		$this->limit = null;
	}

	$stmt = $this->connection->query($sql);

	if (!$stmt) {
		$this->error = $this->lastError().' Query: '.$sql;
		$msg = $this->verboseErrors? ' '.$this->error : '';
		throw new DatabaseException('Query error.'.$msg);
	}

	$this->lastQuery = $sql;	
	$this->res = $stmt;
	return $stmt;
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
	$dbname = '';
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	if (!$dbname) $dbname = $this->dbName();

	$q = $this->query(
	"SELECT * FROM information_schema.statistics
	WHERE table_schema='$dbname'
	AND table_name='$table'
	ORDER BY INDEX_NAME,SEQ_IN_INDEX"
	);

	$name = '';
	$indexes = [];
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
	if (empty($dbname)) $dbname = $this->dbName();

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
	$type = isset($native[$type])? $native[$type] : null;
	if (!$type) return array(null, $size);
	$type = explode(':', $type);
	if (empty($type[1])) $type[1] = $size;
	return $type;
}

function quoteIdent($str)
{
	return "`".Str::id($str)."`";
}

} //class

?>