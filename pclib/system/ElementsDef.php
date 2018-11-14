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
		'base' => [
		'block' => null,
		'default' => null,
		'noprint' => null,
		'onprint' => null,
		'escape' => null,
		'noescape' => null,
		'attr' => null,
		'html' => null,
		],
		'string' => [
			'format' => null,
		],
		'head' => [
			'noversion' => null,
			'inline' => null,
		],
		'class' => [
			'form' => null,
		],		
		'bind' => [
			//'field' => null,
		],
		'link' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'img' => null,
			'popup' => null,
			'field' => null,
			'confirm' => null,
		]
	];


	static function getElement($id, $type)
	{
		$lkpType = in_array($type, ['select', 'radio', 'check'])? 'bind' : $type;

		$elem = isset(self::$elem[$lkpType])? self::$elem[$lkpType] : [];
		$elem['id'] = $id;
		$elem['type'] = $type;
		return self::$elem['base']  + $elem;
	}

}