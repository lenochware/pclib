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

	/**
	 * Load parameters from database.
	 */
	function load()
	{
		$data = $this->db->selectPair("select PARAM_NAME,PARAM_VALUE from APP_PARAMS");
		$this->values = array_merge($this->values, $data); 
		$this->isLoaded = true;
	}

	/**
	 * Set and save parameter to database.
	 */
	function save($name, $value)
	{
		$this->set($name, $value);
		$this->db->insertUpdate("APP_PARAMS", 
			['PARAM_NAME' => $name, 'PARAM_VALUE' => $value, 'UPDATED_AT' => date("Y-m-d H:i:s")], ['PARAM_NAME']
		);
	}

	/**
	 * Get parameter $name.
	 * @param string $name Parameter name
	 * @param string $default Default parameter value
	 * @return parameter value | $default
	 */
	function get($name, $default = null)
	{
		if (!$this->isLoaded) $this->load();

		return $this->values[$name] ?? $default;
	}

	/**
	 * Set parameter $name to value $value.
	 */
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