<?php
/**
 * @file
 * Displaying tabular data in table layout.
 *
 * @author -dk- <lenochware@gmail.com>
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Displaying tabular data in table layout.
 * Features:
 * - Uses template
 * - Read data from database or php array
 * - Pagination
 * - Sorting columns, filtering and summarization
 * - Can use generic layout with default template
 * 
 * Class %Grid implements following template tags: \n
 * class, sort, pager \n
 * See also \ref grid-tags
 */
class Grid extends Tpl
{

/** Number of rows of the %grid. */
public $length = 0;

/** array filter - Values of current filter. */
public $filter = array();

/** enable sorting by more than one column. */
public $multiSort = false;

public $renderSortIcons = true;

/** var GridPager */
public $pager;

/** Array of values for array-based %grid. */
protected $dataArray;

/** Summary fields (if any). */
protected $sumArray;

/** Current sorting. */
public $sortArray = array();

/** %grid sql query. */
protected $sql;

protected $baseUrl;

/** Name of the 'class' element */
protected $className = 'grid';

protected $page;

private $hash;

/** Occurs before output of the row. */
public $onBeforeRow;

/** Occurs after output of the row. */
public $onAfterRow;

/**
 * Initialization - must be called after load()
 */
protected function _init()
{
	parent::_init();

	$this->baseUrl = $this->getBaseUrl();

	if ($_GET['grid'] == $this->name or !$_GET['grid']) {
		if ($_GET['page']) {
			$this->page = $_GET['page'];
		}
	}
	if (isset($_GET['sort'])) $this->setSort($_GET['sort']);

	$this->initPager();
}

/**
 * Create and configure grid pager.
 */
protected function initPager()
{
	$pager = $this->getPager();

	$pgid = $this->elements['pcl_document']['typelist']['pager'];
	$el = $this->elements[$pgid];

	if ($el['ul']) {
		$pager->pattern = '%1$s%3$s%2$s';
		$pager->patternItem = '<li class="%s">%s</li>';
	}
	if ($el['pglen']) {
		$pager->setPageLen($el['pglen']);
	}
	if ($el['size']) {
		$pager->linkNumber = $el['size'];
	}

	$pager->setPage($this->page);

	$this->pager = $pager;
}

/**
 * Return instance of GridPager.
 */
protected function getPager()
{
	return new GridPager($this->length, $this->baseUrl);
}

/**
 * Set active (selected) page.
 * @param int $page Page number
 */
function setPage($page)
{
	$this->page = $page;
	$this->pager->setPage($page);
}

/**
 * Display datagrid.
 * @param string $block If set, only block $block will be shown.
 */
protected function _out($block = null)
{
	$this->values['items'] = $this->getValues();

	if ($this->config['pclib.compatibility']['tpl_syntax'] and !$this->elements['items']['else']) {
		$empty = !$this->values['items'];
		$this->elements['items']['noprint'] = $empty;
		$this->elements['noitems']['noprint'] = !$empty;
	}

	parent::_out($block);
	$this->saveSession();
}

/**
 * Set sql query which will be used as datasource for the grid.
 * For info about SQL parameters and %grid filtering see \ref dynamic-sql.
 * @param string $sql Sql query. Only SELECT-queries are allowed.
 * @see setArray()
 */
function setQuery($sql)
{
	$this->service('db');

	$args = func_get_args();
	$hash = crc32(serialize(array($args, $this->filter)));
	if (!$this->document and $this->sql) $this->create($this->sql);
	if ($this->hash == $hash) return;

	$this->hash = $hash;
	array_shift ($args);
	if (is_array($args[0])) $args = $args[0];

	$sql = $this->db->setParams($sql, $args + (array)$this->filter);
	$sql = str_replace("\n", " \n ", strtr($sql, "\r\t","  "));

	if (!$this->document) $this->create($sql);

	$this->setLength($this->db->count($sql));

	if ($lpos = stripos($sql, ' limit ')) $sql = substr($sql, 0, $lpos);
	$this->sql = $sql;
}

/**
 * Set php array which will be used as datasource for the grid.
 * @param string $dataArray - Array of rows: [$row1, $row2, ...].
 * @param string $totalLength Total number of rows (optional)
 * (Use this, if you desire send to %grid not all rows, but only one page
 * at the time).
 * @see setQuery()
 */
function setArray(array $dataArray, $totalLength = 0)
{
	$this->dataArray = $this->applyFilter($dataArray);
	$this->setLength($totalLength ?: count($this->dataArray));
}

function setSelection(orm\Selection $sel)
{
	$this->setQuery($sel->getSql());
}

/**
 * Enable summarization rows. When $field value changes, summary block
 * is pushed into %grid output.
 * @param string $field GROUP BY field
 * @param string $sql Sumarization query
 * @param string $sumblock Name of the summary block (must exists in template)
**/
function summary($field, $sql = '', $sumblock = 'summary')
{
	$this->elements[$sumblock]['noprint'] = 1;

	if (!$this->sumArray)
		$this->sumArray['items']['pos'] = $this->elements['items']['begin'];

	$this->sumArray[$sumblock]['query'] = $sql;
	$this->sumArray[$sumblock]['field'] = $field;
	$this->sumArray[$sumblock]['pos'] = $this->elements[$sumblock]['begin'];

	preg_match_all("/\[[a-z0-9_]+\]/i", $sql, $params);
	$this->sumArray[$sumblock]['params'] = $params[0];

	uasort($this->sumArray, function($a, $b) { 
		return ($a['pos'] < $b['pos']) ? -1:+1;
	});
}

/**
 * Enable sorting. Example: $grid->setSort('field1,field2');
 * @param string $s fieldlist
 */
function setSort($s)
{
	$this->sortArray = array();
	preg_match_all("/(\w+)(:\w+)?/", $s, $found);
	if (!$found) return;
	foreach($found[1] as $i => $k) {
		$this->sortArray[$k] = $found[0][$i];
	}
}

/**
 * Load %grid object from session.
 * Called when $sessname in constructor is set. Do not call directly.
 * "Session" %grid is necessary, if you need remember current page,
 * sorting and filter after page-reload.
**/
function loadSession()
{
	if (!$this->sessName) return;
	$s = $this->app->getSession($this->sessName);

	$this->sql    = $s['sql'];
	$this->hash   = $s['hash'];
	$this->length = $s['length'];
	$this->filter = $s['filter'];
	$this->sortArray = $s['sortarray'];
	$this->page = $s['page'];
}

/**
 * Save %grid object to session.
 * Called when $sessname in constructor is set.  Do not call directly.
 * "Session" %grid is necessary, if you need remember current page,
 * sorting and filter after page-reload.
**/
function saveSession()
{
	if (!$this->sessName) return;
	$s = array(
		'sql'    => $this->sql,
		'hash'   => $this->hash,
		'length' => $this->length,
		'filter' => $this->filter,
		'page'   => $this->pager->getValue('active'),
		'sortarray' => $this->sortArray,
	);

	$this->app->setSession($this->sessName, $s);
}

/**
 * Invalidate %grid $sessname.
 * Update number of pages of session %grid, if it is changed for example
 * by adding/deleting rows in table.\n
**/
static function invalidate($sessName)
{
	global $pclib;
	$pclib->app->deleteSession("$sessName.hash");
}


/**
 * This function is called for each template tag when it is printed.
 * @copydoc tag-handler
**/
function print_Element($id, $sub, $value)
{
	$elem = $this->elements[$id];

	if ($sub == 'lb')  {
		if (isset($elem['sort']))
			$this->print_Sort($id, $sub);
		else
			print $elem['lb']? $elem['lb']:$id;
		return;
	}
	elseif ($sub == 'value') {
		print $value;
		return;
	}

	switch ($elem['type']) {
		case 'sort':
			$this->print_Sort($id, $sub);
			break;
		case 'pager':
			$this->print_Pager($id, $sub);
			break;
		case 'class':
			$this->print_Class($id, $sub, null);
			break;
		default:
			parent::print_Element($id,$sub,$value);
			break;
	}
}

/**
 * Print %grid pager.
 * {pager} tag will show default pager, for modificators - see \ref grid-tags.
 * @copydoc tag-handler
 */
function print_Pager($id, $sub)
{
	$pgid = $this->elements['pcl_document']['typelist']['pager'];
	$el = $this->elements[$pgid];

	if ($this->pager->getValue('maxpage') < 2 and !$el['nohide']) return;

	if ($this->values[$pgid]) {
		print $this->values[$pgid];
		return;
	}

	if ($sub) {
		print $this->pager->getHtml($sub);
	}
	else {
		print $this->pager->html();
	}
}

/** Return url for sortlink */
protected function sortUrl($id)
{
	$sa = $this->sortArray;

	if ($this->multiSort) {
		if ($sa[$id] == $id.':d') unset($sa[$id]);
		else $sa[$id] = ($sa[$id] == $id)? $id.':d' : $id;
		$s = implode(',', $sa);
	}
	else {
		$s = ($sa[$id] == $id)? $id.':d' : $id;
	}

	return $this->baseUrl."sort=$s&page=1";
}

/**
 * Print sort-link.
 * @copydoc tag-handler
 */
function print_Sort($id, $sub)
{
	$url = $this->sortUrl($id);
	$dir = ($this->sortArray[$id] == $id)? 'up':'dn';
	print "<a href=\"$url\" class=\"sort $dir\">";
	print $this->elements[$id]['lb']? $this->elements[$id]['lb']:$id;
	print "</a>";

	if (!$this->renderSortIcons) return;
	$imageDir = $this->config['pclib.directories']['assets'];
	if (!$this->sortArray[$id]) $dir = 'no';
	print "<img src=\"$imageDir/sort_$dir.gif\"".($this->useXhtml? ' />' : '>');
}

/**
 * Implements {grid.labels} and {grid.items} placeholders.
 * @see Tpl::print_class()
 * @copydoc tag-handler
 */
protected function trPrintElement($elem)
{
	$id = $elem['id'];
	$sub = $elem['sub'];

	if ($sub == 'labels') {
		print '<th>';
		$this->print_Element($id, 'lb', null);
		print '</th>';
	}
	elseif($sub == 'fields') {
		print "<td class=\"$id\">";
		$value = $this->getValue($id);
		if (!$this->fireEventElem('onprint', $id, '', $value))
			$this->print_Element($id, '', $value);
		print '</td>';
	}
}

/** Get template variable _tvar_... */
protected function getVariable($id)
{
	$page = $this->pager->getValue('page');
	$maxpage =  $this->pager->getValue('maxpage');

	switch ($id) {
		case '_tvar_first': 
			$value = ($page == 1 and parent::getVariable('_tvar_top'))? '1':'0';
		break;	
		case '_tvar_last': 
			$value = ($page == $maxpage and parent::getVariable('_tvar_bottom'))? '1':'0';
		break;	
		default: return parent::getVariable($id);
	}
	
	return $this->escapeHtmlFunction($value);
}

/**
 * Load values for current page from database (fill \ref tpl::values array).
 * tpl::values are not cleared, so you can add additional fields from the code.
 */
protected function getValues()
{
	if ($this->dataArray) return $this->getDataArray();

	$q = $this->getQuery();
	if (!$q) {$this->setLength(0); return array(); }

	$rows = $this->db->fetchAll($q);

	//sumgrid hack...
	if (count($rows) > $this->pager->getValue('pglen')) $last = array_pop($rows);
	if ($this->sumArray) $this->sumArray['items']['last'] = $last;

	return $rows;
}

/**
 * Set grid->length (total number of rows).
 * @param int $length Number of rows
 */
protected function setLength($length)
{
	$this->length = $length;
	$this->pager->setLength($length);
	$this->pager->setPage($this->page);
}

/**
 * Load values for current page from dataarray (array-based %grid).
 * @see getvalues(), setarray()
 */
protected function getDataArray()
{
	$pglen = $this->pager->getValue('pglen');
	$begin = ($this->pager->getValue('active') - 1) * $this->pager->getValue('pglen');

	if ($this->sortArray) {
		if (!isset($this->dataArray[0])) $begin = 0; //fix reseting keys in usort
		reset($this->sortArray);
		$id = key($this->sortArray);
		usort($this->dataArray, $this->cmpFunc($id));
	}

	return array_slice($this->dataArray, $begin, $pglen);
}

/** Get summary row for summarization grid */
protected function getSummary($block)
{
	$sum = $this->sumArray[$block];
	if (!$sum['query']) return array();
	if ($sum['params']) {
		foreach($sum['params'] as $param) {
			$trans[$param] = $this->getValue(substr($param,1,-1));
		}
		$sql = strtr($sum['query'], $trans);
	}
	else $sql = $sum['query'];

	return $this->db->select($sql);
}

/** Apply filter on arraygrid. */
protected function applyFilter(array $dataArray)
{
	if (!$this->filter) return $dataArray;

	$result = array();
	for($i = 0; $i < count($dataArray); $i++) {
		$skip = 0;
		foreach ($this->filter as $col => $v) {
			if (!fnmatch($this->filter[$col], $dataArray[$i][$col])) {
				$skip = 1;
				break;
			}
		}
		if (!$skip) $result[] = $dataArray[$i];
	}
	return $result;
}

/**
 * Build %grid query and return result (resource).
 * setquery() is required before calling this function.
 */
protected function getQuery()
{
	$sql = $this->sql;
	if (!$sql) return null;

	if ($this->sortArray) {
		if ($lpos = stripos($sql, ' order by ')) $sql = substr($sql, 0, $lpos);
		foreach($this->sortArray as $id => $sval) {
			if (!$this->elements[$id]) {
				unset($this->sortArray[$id]);
				continue;
			}
			$sort = $this->elements[$id]['sort'];
			$orderby .= ','.$this->getOrderByField($id);
			if ($id != $sval) $orderby .= ' desc';
		}
		if ($orderby) $sql .= ' order by '.substr($orderby, 1);
	}

	$page  = $this->pager->getValue('active');
	$pglen = $this->pager->getValue('pglen');

	if ($page != 'all')
		$this->db->setLimit($pglen + 1, ((int)$page-1) * $pglen);

	return $this->db->query($sql);
}

//sort bind alphabetically by labels
protected function getOrderByField($id)
{
	$sort = $this->elements[$id]['sort'];
	if ($this->elements[$id]['type'] == 'bind') {
		$sortedIds = array_keys($this->getItems($id));
		if ($sortedIds) return "FIELD($id, ".implode(',', $sortedIds).')';
	} 
	return ($sort and $sort != '1')? $sort : $id;
}


/**
 * Use default template for displaying database table content.
 */
function create($tableName)
{
	$tableName = $this->db->tableName($tableName);
	$this->createFromTable($tableName, PCLIB_DIR.'assets/default-grid.tpl');
}

/**
 * Return content of the grid as csv-text.
 * @param array $options [csv-separ: ';', csv-row-separ: "\r\n"]
 * @return string csv-text
 */
function getExportCsv($options = [])
{
	$this->pager->setPageLen($this->length);
	$elements = $this->elements;
	$values = $this->getValues();
	$this->values['items'] = $values;

	$options += ['csv-separ' => ';', 'csv-row-separ' => "\r\n"];
	
	$ignore_list = array('class','block','pager','sort');

	$elms = [];
	foreach($this->elements as $id => $elem) {
		if ($elem['noprint'] or $elem['skip'] or in_array($elem['type'], $ignore_list)) continue;
		unset($elem['title'], $elem['size']);
		$elms[$id] = $elem;
		$last_id = $id;
	}
	$this->elements = $elms;

	ob_start();

	foreach($elms as $id => $elem) {
		print $elem['lb'] ?: $elem['id'];
		if ($id != $last_id) print $options['csv-separ'];
	}
	print $options['csv-row-separ'];

	foreach ($values as $i => $row) {
		foreach($elms as $id => $elem) {
			if (!$this->fireEventElem('onprint',$id,'',$row[$id])) {
			$this->print_Element($id, '', $row[$id]);
			}
			if ($id != $last_id) print $options['csv-separ'];
		}
		if($values[$i+1]) print $options['csv-row-separ'];
	}

	$this->elements = $elements;

	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

/**
 * Show download dialog for csv-file with content of the grid.
 * @param array $options
 * @see getExportCsv()
 */
function exportCsv($fileName, $options = array())
{
	ob_clean();
	header('Content-type: text/csv');
	header('Content-Disposition: attachment; filename="'.$fileName.'"');
	print $this->getExportCsv($options);
	die();
}

/** get proper base url for %grid sort and pager & other links */
protected function getBaseUrl()
{
	$url = $this->getUrl($this->header);
	if (!$url) {
		$a = clone $this->service('router')->action;
		unset($a->params['sort'], $a->params['page']);
		if (!$this->header['singlepage']) $a->params['grid'] = $this->name;
		$url = $this->service('router')->createUrl($a);
	}

	return $url . ((strpos($url,'?') === false)? '?' : '&');
}

private function sumFieldEquals(array $sum)
{
	$rowno = $this->elements['items']['rowno'];
	if ($this->sumArray['items']['pos'] > $sum['pos']) $rowno--;
	$v1 = $this->values['items'][$rowno][$sum['field']];
	$v2 = $this->values['items'][$rowno+1][$sum['field']];
	if ($rowno == $this->pager->getValue('pglen')-1) $v2 = $this->sumArray['items']['last'][$sum['field']];
	return ($v1 == $v2);
}

protected function print_BlockRow($block, $rowno = null)
{
	if ($block == 'items') {
		$this->onBeforeRow($this->values[$block][$rowno], $rowno);
	}

	if ($this->sumArray and $block == 'items') {
		ob_start();
		parent::print_BlockRow($block, $rowno);
		$html = ob_get_contents();
		ob_end_clean();

		foreach($this->sumArray as $sumblock => $sum) {
			if ($sumblock == 'items') {
				print $html;
				continue;
			}
			if ($this->sumFieldEquals($sum)) continue;
			$this->values[$sumblock][0] = $this->getSummary($sumblock);
			$this->print_Block($sumblock);
		}
	}
	else {
		parent::print_BlockRow($block, $rowno);
	}

	if ($block == 'items') {
		$this->onAfterRow($this->values[$block][$rowno], $rowno);
	}
}

/**
 * Return function for sorting dataarray (array-based %grid)
 */
private function cmpFunc($id)
{
	$dir = ($id == $this->sortArray[$id])? '-1:+1' : '+1:-1';
	$cmd = "if (\$a['$id'] == \$b['$id']) return 0;\n";
	$cmd .= "return (\$a['$id'] < \$b['$id']) ? $dir;";
	return create_function('$a,$b', $cmd);
}

} // end class

?>
