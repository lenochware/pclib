<?php 
/**
 * @file
 * TplParser class
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

/*
TODO: merge, zacykleni pri merge, pclib.compatibility.tpl_syntax?, public parseElements?
*/

namespace pclib\system;

/**
 * Parse pclib template format with "elements" data definition language.
 */
class TplParser extends BaseObject
{
	protected $TPL_ELEM;
	protected $TPL_SEPAR;
	protected $TPL_BLOCK;

	public $onParseLine;

	public $translator;

	function __construct()
	{
		parent::__construct();
		$this->TPL_ELEM = chr(1);
		$this->TPL_SEPAR = chr(2);
		$this->TPL_BLOCK = chr(3);
		$this->service('translator', false);
	}

	function parse($templateStr)
	{ 	
		$templ = $this->split($templateStr);
		if ($partials = $this->getPartials($templ)) {
			$templ = $this->mergePartials($templ, $partials);
		}

		return $this->initBlocks($this->parseElements($templ[0]), $this->parseBody($templ[1]));
	}

	//public? partial template
	protected function split($s)
	{ 
		$tag_start = '<?elements';
		$tag_end = '?'.'>';

		$start = strpos($s, $tag_start);
		$end = strpos($s, $tag_end);

		if ($start === false or $end === false or $end < $start) {
			$elements_def = '';
			$template_def = $s;
		}
		else {
			$elements_def = substr($s, $start + strlen($tag_start), $end-$start-strlen($tag_start));
			$template_def = trim(substr($s, $end-$start+strlen($tag_end)),"\r\n");
		}

		return array($elements_def, $template_def);
	}

	protected function parseElements($s)
	{
		$elms = array();

		if (trim($s) == '') return $elms;

		$s = str_replace('\"', '&quot;', $s);
		$lines = explode("\n", $s);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line == '') continue;
			$elem = $this->parseLine($line);
			$elms[$elem['id']] = $elem;
		}

		return $elms;
	}

	/**
	 * Parse element definition line and save element to $elements array.
	 */
	function parseLine($line)
	{
		$terms = preg_split("/[\s]+/", $line);
		$type = array_shift($terms);
		$id = array_shift($terms);

		$elem = array(
			'id' => $id,
			'type' => $type,
		);

		while ($term = array_shift($terms)) {
			$value = ($terms and $terms[0][0] == "\"")? $this->readQTerm($terms) : 1;
			if (strpos($term,'html_')===0) {
				if ($value === 1) $elem['html'][] = substr($term,5);
				else $elem['html'][substr($term,5)] = $value;
			}
			else
				$elem[$term] = $value;
		}

		if ($elem['lb'] and $this->translator) {
			$elem['lb'] = $this->translator->translate($elem['lb']);
		}

		//$this->onParseLine($line, $elem);

		return $elem;
	}

	/** Read quoted value of attribute */
	private function readQTerm(&$terms)
	{
		$term = array_shift($terms);
		while ((substr($term, -1) != "\"" and count($terms)) or strlen($term) == 1) {
			$term .= " " . array_shift($terms);
		}
		return str_replace('&quot;', '"', substr($term, 1, -1));
	}

	protected function parseBody($s)
	{
		if ($this->translator and strpos('-'.$s,'<M>')) {
			$s = $this->translator->translateTags($s);
		}

		return $this->getDocument($s);
	}

	private function getDocument($def)
	{
		$pat[0] = "/{([a-z0-9_.]+)}/i";
		$pat[1] = "/{(BLOCK|IF|IF NOT)\s+([a-z0-9_]+)}/i";
		$pat[2] = "%{/(BLOCK|IF)}%i";

		$rep[0] = $this->TPL_SEPAR . $this->TPL_ELEM  . $this->TPL_SEPAR . '\\1' . $this->TPL_SEPAR;
		$rep[1] = $this->TPL_SEPAR . $this->TPL_BLOCK . $this->TPL_SEPAR . '\\2:\\1' . $this->TPL_SEPAR;
		$rep[2] = $this->TPL_SEPAR . $this->TPL_BLOCK . $this->TPL_SEPAR . 'END:\\1' . $this->TPL_SEPAR;

		// if ($this->config['pclib.compatibility']['tpl_syntax']) {
		// 	$pat[3] = "/<!--\s*BLOCK\s+([a-z0-9_]+)\s*-->/i";
		// 	$rep[3] = TPL_SEPAR . TPL_BLOCK . TPL_SEPAR . '\\1' . TPL_SEPAR;
		// }

		return explode($this->TPL_SEPAR, preg_replace($pat, $rep, $def));
	}

	protected function getPartials($templ)
	{
		return false;
	}

	protected function mergePartials($templ, $partials)
	{
		foreach ($partials as $line) {
			$partial = $this->parseLine($line);

			$templateStr = file_get_contents($partial['file']);
			$tpart = $this->split($templateStr);

			if ($tpartPartials = $this->getPartials($tpart)) {
				$tpart = $this->mergePartials($tpart, $tpartPartials);
			}

			$templ[0] .= $tpart[0];
			$templ[1] .= $tpart[1];
		}

		return $templ;
	}

	private function initBlocks($elements, $document)
	{
		$elements['pcl_document'] = array(
			'type' => 'block',
			'begin'=> 0, 'end' => count($document)
		);

		$bstack = array(); $block = null;
		foreach ($document as $key=>$strip) {
			if ($strip == $this->TPL_ELEM) {
				list($id,$sub) = explode('.',$document[$key+1]);
				if ($elements[$id] and !$elements[$id]['block'])
					$elements[$id]['block'] = $block;
			}

			if ($strip != $this->TPL_BLOCK) continue;
			list($name,$type) = explode(':',$document[$key+1]);

			$type = strtoupper($type);
			$section = strtoupper($name);

			if ($section == 'END') {
				if ($block) $elements[$block]['end'] = $key;
				$block = array_pop($bstack);
			}
			elseif ($section == 'ELSE') {
				if ($block) $elements[$block]['else'] = $key + 2;
				$document[$key]   = null;
				$document[$key+1] = null;
			}
			else {
				array_push($bstack, $block);
				$block = $name;

				if ($type == 'IF') {
					$block = '__if'.$key;
					$elements[$block]['if'] = $name;
				}
				elseif($type == 'IF NOT') {
					$block = '__if'.$key;
					$elements[$block]['ifnot'] = $name;
				}

				$document[$key+1] = $block;
				if ($elements[$block]['begin']) {
					throw new Exception("Block name '%s' is already used.", array($block));
				}
				$elements[$block]['id']    = $block;
				$elements[$block]['type']  = 'block';
				$elements[$block]['block'] = end($bstack);
				$elements[$block]['begin'] = $key + 2;
				$elements[$block]['end'] = $elements['pcl_document']['end'];
			}
		}

		return array($elements, $document);
	}

}

?>