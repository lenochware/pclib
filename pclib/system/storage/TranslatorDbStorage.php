<?php

namespace pclib\system\storage;
use pclib\system\BaseObject;

/**
 * Default Translator storage.
 * Save and load multilanguage texts from database table.
 */
class TranslatorDbStorage extends BaseObject
{

/** var Translator */
protected $translator;

/** var Db */
public $db;

public $TRANSLATOR_TAB, $LABELS_TAB;

function __construct(\pclib\Translator $translator)
{
	parent::__construct();
	$this->translator = $translator;
	$this->service('db');

	$this->TRANSLATOR_TAB = 'TRANSLATOR';
	$this->LABELS_TAB = 'TRANSLATOR_LABELS';
}

/**
 * Return translations for page $pagename and language $lang.
 * @returns array $texts
 */
function getPage($lang, $pageName)
{
	$params = array(
		'TRANSLATOR' => $this->getLabelId($this->translator->name, 1),
		'LANG' => $this->getLabelId($lang, 2),
		'PAGE' => $this->getLabelId($pageName, 3),
	);

	return $this->db->selectPair(
		"select T1.TSTEXT, T2.TSTEXT from TRANSLATOR T1
		join TRANSLATOR T2 on T2.TEXT_ID=T1.ID AND T2.LANG='{LANG}'
		where T1.TRANSLATOR='{TRANSLATOR}' AND T1.LANG=0 AND T1.PAGE='{PAGE}'", $params
	);
}

function getLabelId($label, $category)
{
	if (!$label) return -1;
	if ($label == 'source' and $category == 2) return 0;
	if ($label == 'default' and $category == 3) return 0;

	$id = $this->db->field($this->LABELS_TAB.':ID',
		"LABEL='{0}' AND CATEGORY='{1}'", $label, $category
	);
	if (!$id) {
		$label = array('LABEL'=>$label,'CATEGORY'=>$category,'DT'=>date('Y-m-d H:i:s'));
		$id = $this->db->insert($this->LABELS_TAB, $label);
	}
	return $id;
}

protected function removeUnusedLabel($id)
{
	$label = $this->db->select($this->LABELS_TAB, pri($id));
	switch ($label['CATEGORY']) {
		case 1: $fld = 'TRANSLATOR'; break;
		case 2: $fld = 'LANG'; break;
		default: return;
	}

	if (!$this->db->exists($this->TRANSLATOR_TAB, $fld."='{0}'", $id)) {
		$this->db->delete($this->LABELS_TAB, pri($id));
	}
}

/**
 * Save string $s to default-language table.
 */
function saveDefault($pageName, $s)
{
	if (!$pageName) throw new \pclib\Exception("Parameter 'pagename' is empty.");
	$params = array(
		'TRANSLATOR' => $this->getLabelId($this->translator->name, 1),
		'LANG' => 0,
		'PAGE' => $this->getLabelId($pageName, 3),
		'TSTEXT' => $s,
	);
	if ($this->db->exists($this->TRANSLATOR_TAB, $params)) {
		$this->db->update($this->TRANSLATOR_TAB, "DT=NOW()", $params);
	}
	else {
		$data = $params;
		$data['DT'] = date('Y-m-d H:i:s');
		$text_id = $this->db->insert($this->TRANSLATOR_TAB, $data);
		$this->db->update($this->TRANSLATOR_TAB, "TEXT_ID='$text_id'", pri($text_id));
	}
}

/**
 * Create new language table.
 * @param bool $copyDefault Copy default language table into new language.
 * @return id of new language
 */
function createLanguage($lang)
{
	if ($this->hasLanguage($lang)) {
		throw new \pclib\Exception("Language '$lang' already exists.");
	}

	$data = array(
		'TRANSLATOR' => $this->getLabelId($this->translator->name, 1),
		'LANG' => $this->getLabelId($lang, 2),
		'PAGE' => 0,
		'TEXT_ID' => 0,
		'TSTEXT' => $this->translator->name.'/'.$lang,
		'DT' => date('Y-m-d H:i:s'),
	);

	//needed placeholder
	$this->db->insert($this->TRANSLATOR_TAB, $data);

	return $data['LANG'];
}

function hasLanguage($lang)
{
	$langId = $this->getLabelId($lang, 2);
	$translatorId = $this->getLabelId($this->translator->name, 1);

	$found = $this->db->select($this->TRANSLATOR_TAB,
		"TRANSLATOR='{0}' AND LANG='{1}'", $translatorId, $langId
	);

	return $found;	
}

/**
 * Delete language table.
 */
function deleteLanguage($lang)
{
	$langId = $this->getLabelId($lang, 2);
	$translatorId = $this->getLabelId($this->translator->name, 1);

	/* Check dependencies for source */
	if ($langId == 0) {
		if ($this->db->exists($this->TRANSLATOR_TAB, "TRANSLATOR='{0}' AND LANG<>0", $translatorId)) {
			 throw new \pclib\Exception("Cannot delete 'source' - remove all other languages first.");
		}
	}

	$this->db->delete($this->TRANSLATOR_TAB, "TRANSLATOR='{0}' AND LANG='{1}'", $translatorId, $langId);

	$this->removeUnusedLabel($langId);
	$this->removeUnusedLabel($translatorId);
}

}
