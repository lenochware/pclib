<?php
/**
 * @file
 * PClib database driver.
 * Database drivers are stored in pclib/system/database/ directory and loaded by class db automatically.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

namespace pclib\system\database;
use pclib\DatabaseException;

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/**
 * PostgreSQL database driver.
 * Implements support of PostgreSQL database engine.
 */
class PgsqlDriver extends AbstractDriver
{

public $extension = 'pgsql';

/**
 * Convert array keys of result set to UPPERCASE, if > 0.
 * Hack for access to pclib system tables, which assumes uppercase field names.
 **/
public $ucase = 0;
public $noquote = false;

function connect($ds)
{
	if ($ds['host'] != 'socket') $host = "host=".$ds['host'];
	$res = pg_connect(
		$host." dbname=$ds[dbname] user=$ds[user] password=$ds[passw]",
		$this->forceReconnect? PGSQL_CONNECT_FORCE_NEW : 0
	);
	if (!$res) {
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
	pgsql_close($this->connection);
	$this->connection = false;
}

function seek($res, $rowno)
{
	return pg_result_seek($res, $rowno);
}

function getInsertId()
{
	$res = @pg_query($this->connection, "select lastval()");
	if (!$res) return 0;
	$row = pg_fetch_row($res);
	return $row[0];
}

function numRows($res = null)
{
	if (!$res) $res = $this->res;
	return $res? pg_num_rows($res) : 0;
}

function affectedRows($res = null)
{
	return $this->res? pg_affected_rows($res?$res:$this->res):0;
}

function query($sql)
{
	$this->error = '';

	if ($this->limit) {
		$sql .= " LIMIT ".$this->limit[0]." OFFSET ".$this->limit[1];
		$this->limit = null;
	}

	$res = pg_query($this->connection, $sql);

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
		case 'f' : $row = @pg_fetch_row($res);
							 return $row[0];
		case 'o' : $data = @pg_fetch_object($res); break;
		case 'r' : $data = @pg_fetch_row($res);    break;
		case 'ar': $data = @pg_fetch_array($res);  break;
		case 'a' :
		default  : $data = @pg_fetch_assoc($res);  break;
	}

	if (!$data) return array();
	return ($this->ucase and is_array($data))? array_change_key_case($data, CASE_UPPER) : $data;
}

function lastError()
{
	return pg_last_error($this->connection);
}

function codePage($cp)
{
 $ret = pg_set_client_encoding($cp);
 if ($ret == -1) { $this->seterror('Failed set client encoding.'); return false; }
 return true;
}

function dbName()
{
	return pg_dbname ($this->connection);
}

function indexes($table)
{
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	if (!$dbname) $dbname = $this->dbName();

	$q = $this->query(
	"select
		i.relname as index_name,  a.attname as column_name
		from pg_class t, pg_class i, pg_index ix, pg_attribute a
		where
		t.oid = ix.indrelid
		and i.oid = ix.indexrelid
		and a.attrelid = t.oid
		and a.attnum = ANY(ix.indkey)
		and t.relkind = 'r'
		and t.relname like '$table'
		order by t.relname, i.relname"
	);

	$name = '';
	$indexes = array();
	while ($row = $this->fetch($q, 'o')) {
		if ($row->index_name != $name) {
			$name = $row->index_name;
			$indexes[$name] = array(
			'name' => $name,
			);
		}
		$indexes[$name]['columns'][] = $row->column_name;
	}
	return $indexes;
}

function columns($table)
{
	if (strpos($table, '.')) list($dbname, $table) = explode('.', $table);
	if (!$dbname) $dbname = $this->dbName();

	$q = $this->query(
	"SELECT * FROM information_schema.columns
	WHERE table_name ilike '$table'
	AND table_catalog ilike '$dbname'"
	);

	$columns = array();
	while ($row = $this->fetch($q, 'o')) {
		$type = $this->u_type($row->data_type, $row->character_maximum_length);

		$columns[$row->column_name] = array(
		'name' => $row->column_name,
		'type' => $type[0],
		'size' => $type[1],
		'nativetype' => $row->data_type,
		'nullable' => ($row->is_nullable == 'YES'),
		'default' => $row->column_default,
		'autoinc' => strpos('-'.$row->column_default, 'nextval('),
		);
	}
	return $columns;
}

function version()
{
	$q = $this->query('show server_version');
	$row = $this->fetch($q, 'r');
	return $row[0];
}

private function u_type($type, $size)
{
	$native = array(
	'bigint' => 'int:8','bigserial' => 'int:8','boolean' => 'bool','bytea' => 'binary',
	'character varying' => 'string','character' => 'string','date' => 'date:1',
	'double precision' => 'float:8','integer' => 'int:4','real' => 'float:4',
	'smallint' => 'int:2','serial' => 'int:4','text' => 'string:65535',
	'timestamp' => 'date:2',
	);
	$type = $native[$type];
	if (!$type) return array(null, $size);
	$type = explode(':', $type);
	if (!$type[1]) $type[1] = $size;
	return $type;
}

function quoteIdent($str)
{
	if ($this->noquote) return $str;
	return '"'.pcl_ident($str).'"';
}

function escape($str, $type = 'string')
{
	if ($type == 'ident') return $this->quoteIdent($str);
	if (!$str or is_numeric($str)) return $str;
	return pg_escape_string($str);
}

} //class

?>