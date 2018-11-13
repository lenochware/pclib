<?php 
/**
 * @file
 * ElementsDef class
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
 * Elements definitions.
 */
class ElementsDef extends BaseObject
{
	static $elem = [
		'block' => null,
		'default' => null,
		'noprint' => null,
		'onprint' => null,
		'escape' => null,
		'noescape' => null,
		'attr' => null,
	];

	static function getElement($id, $type)
	{
		$elem = self::$elem;
		$elem['id'] = $id;
		$elem['type'] = $type;
		return $elem;
	}

}