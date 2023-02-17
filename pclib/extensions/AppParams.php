<?php 

namespace pclib\extensions;
use pclib;

/**
 * Can by used as $app->params service, for user editable configuration parameters stored in database.
 */
class AppParams implements pclib\IService {

	/** var App */
	protected $app;

	protected $db;
	protected $values = [];
	public $isLoaded = false;

	function __construct()
	{
		global $pclib;
		$this->app = $pclib->app;
		$this->db = $this->app->db;
	}

	function load()
	{
		$this->values += $this->db->selectPair("select PARAM_NAME,PARAM_VALUE from APP_PARAMS");
		$this->isLoaded = true;
	}

	function get($name, $default = null)
	{
		if (!$this->isLoaded) $this->load();

		return $this->values[$name] ?? $default;
	}

	function set($name, $value)
	{
		$this->values[$name] = $value;
	}

	function add($list, $name, $value)
	{
		if (!isset($this->values[$list])) $this->values[$list] = [];
		if (!is_array($this->values[$list])) throw new Exception('Not a list!');
		$this->values[$list][$name] = $value;
	}

	function delete($list, $name)
	{
		unset($this->values[$list][$name]);
	}


	public function __get($name)
	{
		if (!$this->isLoaded) $this->load();

		if (!isset($this->values[$name])) {
			throw new Exception("Parameter '$name' is not defined.");
		}

		return $this->get($name);
	}

}

 ?>