<?php 

namespace pclib\extensions;

use pclib;
use pclib\Exception;
use pclib\NotImplementedException;

/**
 * Can by used as $app->params service, for user editable configuration parameters stored in database.
 */
class AppParams implements pclib\IService {

	/** var App */
	protected $app;

	protected $db;
	protected $values = [];
	public $isLoaded = false;

	public $PARAMS_TAB = 'APP_PARAMS';

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
		$data = $this->db->selectPair($this->PARAMS_TAB.':PARAM_NAME,PARAM_VALUE');
		$this->values = array_merge($this->values, $data); 
		$this->isLoaded = true;
	}

	function save()
	{
		throw new NotImplementedException;
	}

	/*
	 * Setup this service from configuration file.
	 */
	public function setOptions(array $options)	{}

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