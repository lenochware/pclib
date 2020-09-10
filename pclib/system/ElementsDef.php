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
			'skip' => null,
			'format' => null,
			'tooltip' => null,
			'size' => null,
			'required' => null,
		],

		'class' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'singlepage' => null,
			'ajaxget' => null,
			'ajax' => null,
			'submitted' => null,
			'noformtag' => null,
			'table' => null,
			'get' => null,
			'jsvalid' => null,
			'default_print' => null,
		],

		'pager' => [
			'ul' => null,
			'size' => null,
			'nohide' => null,
			'pglen' => null,
		],

		'selector' => [
			'bitfield' => null,
			'format' => null,
			'list' => null,
			'query' => null,
			'lookup' => null,
			'datasource' => null,
			'emptylb' => null,
			'columns' => null,
			'noemptylb' => null,
			'hint' => null,
			'ajaxget' => null,
			'hidden' => null,
		],

		'link' => [
			'href' => null,
			'action' => null,
			'route' => null,
			'img' => null,
			'glyph' => null,
			'popup' => null,
			'field' => null,
			'confirm' => null,
			'tag' => null,
			'onclick' => null,
			'submit' => null,
			'hint' => null,
			'ajaxget' => null,
		],

		'input' => [
			'date' => null,
			'file' => null,
			'multiple' => null,
			'maxlength' => null,
			'password' => null,
			'email' => null,
			'number' => null,
			'pattern' => null,
			'range' => null,
			'hint' => null,
			'ajaxget' => null,
			'hidden' => null,
		],
	];


	static function getElement($id, $type)
	{
		switch ($type) {
			case 'class':
				$elem = self::$elem['class'];
				break;
		
			case 'select':
			case 'radio':
			case 'check':
			case 'bind':
					$elem = self::$elem['selector'];
				break;

			case 'input':	
			case 'text':
			case 'listinput':
					$elem = self::$elem['input'];
				break;

			case 'button':
			case 'link':
						$elem = self::$elem['link'];
				break;

			case 'pager':
						$elem = self::$elem['pager'];
				break;

			default:
						$elem = [];
				break;
		}

		$elem['id'] = $id;
		$elem['type'] = $type;

		return self::$elem['base'] + $elem;
	}

}