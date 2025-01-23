<?php 
/**
 * @file
 * ElementsDef class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

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
			'hidden' => null,
			'multiple' => null,
			'nosave' => null,
			'noedit' => null,
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
			'hash' => null,
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
			'hidden' => null,
			'nosave' => null,
			'noedit' => null,
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
					$elem = self::$elem['input'];
				break;

			case 'listinput':
					$elem = self::$elem['input'] + self::$elem['selector'];
			break;				

			case 'button':
			case 'link':
						$elem = self::$elem['link'];
				break;

			case 'pager':
						$elem = self::$elem['pager'];
				break;

			case 'variable':
						$elem = ['skip' => 1];
				break;

			default:
						$elem = [];
				break;
		}

		$elem['id'] = $id;
		$elem['type'] = $type;

		return  $elem + self::$elem['base'];
	}

}