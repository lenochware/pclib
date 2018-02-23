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

namespace pclib\system;

/**
 * Parse pclib template with pclib-elements code.
 * Example: $parsed = $parser->parse($templateStr);
 */
class TplParser extends BaseObject
{
	const TPL_ELEM = "\x01";
	const TPL_SEPAR = "\x02";
	const TPL_BLOCK = "\x03";

	public $translator;

	public $legacyBlockSyntax = false;

	public $translateAttrib = array('lb' => 1, 'hint' => 1, 'html_title' => 1);

	function __construct()
	{
		parent::__construct();
		$this->service('translator', false);
	}

	/** 
	 * Parse template.
	 * @param string $templateStr Template source code
	 * @return array $parsed [$elements, $body]
	 */
	function parse($templateStr)
	{ 	
		$templ = $this->split($templateStr);
		if ($partials = $this->getPartials($templ)) {
			$templ = $this->mergePartials($templ, $partials);
		}

		return $this->initBlocks($this->parseElements($templ[0]), $this->parseBody($templ[1]));
	}

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

		$elms['pcl_document'] = array(
			'type' => 'block',
			'begin'=> 0, 'end' => 0
		);

		if (trim($s) == '') return $elms;

		$s = str_replace('\"', '&quot;', $s);
		$lines = explode("\n", $s);
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line == '') continue;
			$elem = $this->parseLine($line);
			
			if (isset($elms[$elem['id']])) {
				throw new \pclib\Exception("Duplicate element '%s'", array($elem['id']));
			}

			$elms[$elem['id']] = $elem;
			$typelist[$elem['type']] = $elem['id'];
		}

		$elms['pcl_document']['typelist'] = $typelist;

		return $elms;
	}

	/**
	 * Parse element definition line and return $element array.
	 * @param string $line pclib-element line - example: "input EMAIL required"
	 * @return array $element
	 */
	function parseLine($line)
	{
		$terms = preg_split("/[\s]+/u", $line);
		$type = array_shift($terms);
		$id = array_shift($terms);

		$elem = array(
			'id' => $id,
			'type' => $type,
		);

		while ($term = array_shift($terms)) {
			$value = ($terms and $terms[0][0] == "\"")? $this->readQTerm($terms) : 1;

			if ($this->translator and isset($this->translateAttrib[$term])) {
				$value = $this->translator->translate($value);
			}

			if (strpos($term,'html_')===0) {
				if ($value === 1) $elem['html'][] = substr($term,5);
				else $elem['html'][substr($term,5)] = $value;
			}
			else
				$elem[$term] = $value;
		}

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

		$rep[0] = self::TPL_SEPAR . self::TPL_ELEM  . self::TPL_SEPAR . '\\1' . self::TPL_SEPAR;
		$rep[1] = self::TPL_SEPAR . self::TPL_BLOCK . self::TPL_SEPAR . '\\2:\\1' . self::TPL_SEPAR;
		$rep[2] = self::TPL_SEPAR . self::TPL_BLOCK . self::TPL_SEPAR . 'END:\\1' . self::TPL_SEPAR;

		if ($this->legacyBlockSyntax) {
			$pat[3] = "/<!--\s*BLOCK\s+([a-z0-9_]+)\s*-->/i";
			$rep[3] = self::TPL_SEPAR . self::TPL_BLOCK . self::TPL_SEPAR . '\\1' . self::TPL_SEPAR;
		}

		return explode(self::TPL_SEPAR, preg_replace($pat, $rep, $def));
	}

	protected function getPartials($templ)
	{
		preg_match_all("/^\s*(include.+)$/m",$templ[0], $found);
		return $found[1]? $found[1] : false;
	}

	protected function getPath($dir)
	{
		global $pclib;
		if (!$pclib->app) return $dir;
		return paramStr($dir, $pclib->app->paths);
	}

	protected function mergePartials($templ, $partials, $level = 1)
	{
		if ($level > 10) {
			throw new \pclib\Exception("Maximum template nesting level of '10' reached, aborting!");
		}

		foreach ($partials as $line) {
			$partial = $this->parseLine($line);

			if (!$partial['file']) {
				throw new \pclib\NoValueException("Attribute 'file' in 'include' must not be empty.");
			}


			$path = $this->getPath($partial['file']);

			if (!file_exists($path)) {
				throw new \pclib\FileNotFoundException("Include file '".$path."' not found.");
			}

			$templateStr = file_get_contents($path);
			$tpart = $this->split($templateStr);

			if ($tpartPartials = $this->getPartials($tpart)) {
				$tpart = $this->mergePartials($tpart, $tpartPartials, ++$level);
			}

			$templ[0] = str_replace($line, $tpart[0], $templ[0]);
			$templ[1] = str_replace('{'.$partial['id'].'}', $tpart[1], $templ[1]);
		}

		return $templ;
	}

	private function initBlocks($elements, $document)
	{
		$elements['pcl_document']['end'] = count($document);

		$bstack = array(); $block = null;
		foreach ($document as $key=>$strip) {
			if ($strip == self::TPL_ELEM) {
				list($id,$sub) = explode('.',$document[$key+1]);
				if ($elements[$id] and !$elements[$id]['block'])
					$elements[$id]['block'] = $block;
			}

			if ($strip != self::TPL_BLOCK) continue;
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