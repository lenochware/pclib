<?php
/**
 * @file
 * Autoloader class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib\system;

/**
 * Simple autoloading of the classes.
 * Usually you will use default pclib autoloader: $pclib->autoloader.
 * Features:
 * - Register directory: addDirectory() - class names must be the same as filenames
 * - Register array of className:fileName pairs: addClasses()
 * - Register alias name for any class: addAliases()
 */
class Autoloader
{
	protected $classes = array();
	protected $aliases = array();
	protected $directories = array();

	/**
 	 * Autoloading callback - do not use directly.
 	 */
 	public function autoload($class)
	{
		if (isset($this->aliases[$class])) {
			return class_alias($this->aliases[$class], $class);
		}
		elseif (isset($this->classes[$class])) {
			require $this->classes[$class];
		}
		else {
			$classPath = trim(str_replace('\\', '/', $class), '/');
			foreach ($this->directories as $directory) {
				$opt = $directory['options'];
				if (isset($opt['namespace'])) {
					if (startsWith($classPath, $opt['namespace'])) {
						$classPath = substr($classPath, strlen($opt['namespace'])+1);
					}
					else continue;
				}

				if (!file_exists($directory['dir'].'/'.$classPath.'.php')) continue;
				require $directory['dir'].'/'.$classPath.'.php';
				return;
			}
		}

	}

	/**
 	 * Register autoloader.
 	 */
	public function register()
	{
		if (!function_exists('spl_autoload_register')) {
			throw new \pclib\Exception('Function spl_autoload not found in this version of PHP.');
		}

		spl_autoload_register(array($this, 'autoload'));
	}

	/**
 	 * Unregister autoloader.
 	 */
	public function unregister()
	{
		return spl_autoload_unregister(array($this, 'autoload'));
	}

	/**
 	 * Add directory, where search for the class will be performed.
 	 * @param string $directory
 	 * @param string $options Unused - for future extension
 	 */
	function addDirectory($directory, $options = array())
	{
		$this->directories[] = array(
			'dir' => rtrim($directory,"/\\"), 
			'options' => $options
		);
	}

	/**
 	 * Add list of classes and corresponding files where classes are defined.
 	 * @param array $classes Array of className:fileName pairs.
 	 */
	function addClasses(array $classes)
	{
		$this->classes = array_merge($this->classes, $classes);
	}

	/**
 	 * Add list of class aliases.
 	 * @param array $aliases Array of className:aliasName pairs.
 	 */
 	function addAliases(array $aliases)
	{
		$this->aliases = array_merge($this->aliases, $aliases);
	}


}

?>