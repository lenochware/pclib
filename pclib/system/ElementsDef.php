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
			'lb' => null,
		],
		'base_form' => [
			'hidden' => null,
			'required' => null,
		],		
		'string' => [
			'format' => null,
			'tooltip' => null,
		],
		'number' => [
			'format' => null,
		],
		'head' => [
			'noversion' => null,
			'inline' => null,
		],
		'class_tpl' => [
		],
		'class_grid' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'singlepage' => null,
		],
		'class_form' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'ajaxget' => null,
			'submitted' => null,
			'noformtag' => null,
			'table' => null,
			'get' => null,
			'jsvalid' => null,
			'default_print' => null,
		],
		'bind' => [
			'bitfield' => null,
			'format' => null,
			'list' => null,
			'query' => null,
			'lookup' => null,
			'emptylb' => null,
		],
		'link' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'img' => null,
			'popup' => null,
			'field' => null,
			'confirm' => null,
		],
		'pager' => [
			'ul' => null,
			'size' => null,
		],
		'input' => [
			'file' => null,
			'size' => null,
			'ajaxget' => null,
		]
	];


	static function getElement($id, $type, $templateClass)
	{
		if ($type == 'class') {
			$elem = self::$elem['class_'.$templateClass];
		}
		else {
			$lkpType = in_array($type, ['select', 'radio', 'check'])? 'bind' : $type;
			$elem = isset(self::$elem[$lkpType])? self::$elem[$lkpType] : [];			
		}

		$elem['id'] = $id;
		$elem['type'] = $type;
		
		if ($templateClass == 'form') {
			$elem = self::$elem['base_form'] + $elem;
		}

		return self::$elem['base'] + $elem;
	}

}