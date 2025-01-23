<?php
/**
 * @file
 * Multilanguage support.
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

namespace pclib;
use pclib;
use pclib\system\storage\TranslatorDbStorage;

/**
 * Translate strings to another language.
 * Features:
 * - Can load translated texts from php array or database table.
 * - Can dig texts from templates and source and fill database table for you.
 * - Texts can be separated to pages, you can load only some pages for better performance.
 */
class Translator extends system\BaseObject implements IService
{

/** string Current language */
public $language;

/** bool If enabled, add new texts into db-table automatically. */
public $autoUpdate  = false;

/** Current page in use. */
protected $pageName;

/** array message buffer */
protected $cache = array();

/** Translator name. Table can contains texts for different translators/applications. */
public $name;

/** var TranslatorDbStorage */
public $storage;

public $TAG_PATTERN = "/<M>(.+?)<\/M>/si";

/* var function() Return type of plural. */
public $pluralFunction;

/**
 * @param string $name Translator name
 */
function __construct($name = null)
{
	global $pclib;
	parent::__construct();

	if ($app = $pclib->app) {
		if(!$name) $name = $app->name;
	}

	$this->pluralFunction = array($this, 'getPlural');
	$this->name = $name;
}

/** Return storage object - if not exists, create one. */
protected function getStorage()
{
	global $pclib;
	if (!$this->storage) $this->storage = new TranslatorDbStorage($this);
	return $this->storage;
}

/** Load translation texts for page $pageName. */
function usePage($pageName)
{
	$this->pageName = $pageName;
	$messages = $this->getStorage()->getPage($this->language, $pageName);
	$this->cache = $messages + $this->cache;
	return count($messages);
}

/** Load translation texts from $messages array in php-file $fileName. */
function useFile($fileName)
{
	$messages = array();
	include($fileName);
	$this->cache = $messages + $this->cache;
	return count($messages);
}

/** Translate string $s to selected language. */
function translate($s, array $params = null)
{
	if ($this->autoUpdate and !array_key_exists($s, $this->cache)) {
		if (!$this->pageName) throw new Exception("Cannot save text. Text page is not selected (call usePage).");
		$this->getStorage()->saveDefault($this->pageName, $s);
	}

	$ts = array_get($this->cache, $s);
	if (!$ts) $ts = $s;
	if ($params) $ts = vsprintf ($ts, $params);
	return $ts;
}

function translateArray(array $a)
{
	array_walk_recursive($a, array($this, 'translateCallback'));
	return $a;
}

private function translateCallback(&$value, $key)
{
	$value = $this->translate($value);
}

/** Translate plural form of string $s ($n is number of items). */
function ntranslate($s, $n, array $params = null)
{
	$plural = $this->pluralFunction($n, $this->language);
	$s = ($plural? $plural.' ' : '').$s;
	return $this->translate($s, $params);
}

/** Translate each text in $s wrapped in <M></M> tags. */
function translateTags($s)
{
	preg_match_all($this->TAG_PATTERN, $s, $tags, PREG_SET_ORDER);
	if (!$tags) return $s;
	foreach($tags as $tag) {
		$trans[$tag[0]] = $this->translate($tag[1]);
	}
	return strtr($s, $trans);
}

function getClientLang()
{
	return strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',0,2));
}

function getPlural($n, $language)
{
	return ($n == 1)? '' : 'PLURAL';
}

function createLanguage($lang)
{
	return $this->getStorage()->createLanguage($lang);
}

function hasLanguage($lang)
{
	return $this->getStorage()->hasLanguage($lang);
}

function deleteLanguage($lang)
{
	$this->getStorage()->deleteLanguage($lang);
}

function getId()
{
	return $this->getStorage()->getLabelId($this->name, 1);
}

}
