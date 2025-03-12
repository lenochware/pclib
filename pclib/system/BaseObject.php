<?php
/**
 * @file
 * Ancestor of all pclib classes.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib\system;
use pclib\MemberAccessException;
use pclib\Exception;

/**
 * Ancestor of all pclib classes.
 * Features:
 * - Access to undeclared members throws exception
 * - Events and object closures
 * - Object defaults
 */ 
class BaseObject
{
	/*protected*/public static $defaults = array();

	/** var function() Return service object when requested with service(). */
	public $serviceLocator;
 
	/**
	 * Set or retireve default parameters of the object. 
	 * You can set any public attribute of the object.
	 * Example: Form::defaults('useButtonTag', true); or Form::defaults($options);
	 * @param mixed Configuration parameter(s).
	 */
	public static function defaults()
	{
		$args = func_get_args();
		$classDef = &self::$defaults[get_called_class()];

		if (!$args) {
			return $classDef;
		}
		elseif(is_array($args[0])) {
			self::$defaults[get_called_class()] = array_merge($classDef, $args[0]);
			return $classDef;
		}

		list($name, $value) = $args;

		if (isset($value)) {
			$classDef[$name] = $value;
		}
		else {
			return $classDef[$name];
		}
	}

	function __construct()
	{
		$this->loadDefaults(get_called_class());
	}

	/**
	 * Load default parameters of class $className into object instance. 
	 */
	public function loadDefaults($className = null)
	{
		if (!$className) $className = get_class($this);
		if ($parentClass = get_parent_class($className)) {
			$this->loadDefaults($parentClass);
		}

		$this->setProperties(array_get(self::$defaults, $className, []));
		return $this;
	}

	/**
	 * Set public properties of object from the array. 
	 * @param array $defaults Array of parameters to be set.
	 */
 	function setProperties(array $defaults)
	{
		$closure = function($o, $defaults) {
			foreach ($defaults as $key => $value) {	
				$o->$key = $value;
			}
		};

		$closure($this, $defaults);
	}

	function on($name, $fn)
	{
		$em = $this->serviceLocator('events');
		if (!$em) return false;
		$em->on($name, $fn, $this);
	}

	function trigger($name, $data = [])
	{
		$em = $this->serviceLocator('events');
		if (!$em) return false;
		return $em->trigger($name, $data, $this);
	}

	/**
	 * Try acquire $service and load it into property $this->$service.
	 * @param string $service Service name
	 * @param mixed $default Default value when service is not found
	 * @return object Service object
	 */
	protected function service($service, $default = null)
	{
		if (!$this->$service) {

			if ($this->serviceLocator) {
				$result = $this->serviceLocator($service);
			}

			if (empty($result)) {
				if (isset($default)) {
					$result = is_string($default)? $this->service($default) : $default;
				}
				else {
					$className = get_class($this);
					throw new \pclib\Exception("Required service '$className->$service' is not set.");
				}
			}

			$this->$service = $result;
		}

		return $this->$service;
	}

	public function __call($name, $args)
	{
		// instanceof Closure
		if (isset($this->$name) and is_callable($this->$name)) {
			return call_user_func_array($this->$name, $args);
		}

		$class = get_class($this);
		throw new MemberAccessException("Call to undefined method $class->$name()."); 
	}

	public function __get($name)
	{
		$class = get_class($this);
		throw new MemberAccessException("Cannot read an undeclared property $class->$name.");
	}

	public function __set($name, $value)
	{
		$class = get_class($this);
		throw new MemberAccessException("Cannot write to an undeclared property $class->$name.");
	}

	public static function __callStatic($name, $args)
	{
		$class = get_called_class();
		throw new MemberAccessException("Call to undefined static method $class::$name().");
	}

	public function __toString()
	{
		return 'Object.'.get_class($this)/*.' '.json_encode($this, JSON_PRETTY_PRINT, 1)*/;
	}

	/**
	 * Convert object to array. 
	 * @return array Object
	 */
	public function toArray()
	{
		return (array)$this;
	}

/*
	public function __isset($name)
	{
	}

	public function __unset($name)
	{
	}
*/

}