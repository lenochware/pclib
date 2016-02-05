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

require_once PCLIB_DIR . 'Tpl.php';

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

/** array pager - Configuration and state of %grid pager. */
public $pager = array();

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

private $hash;

/**
 * Initialization - must be called after load()
 */
protected function _init()
{
	parent::_init();

	$this->baseUrl = $this->getBaseUrl();

	$pgid = ifnot($this->header['pager'], 'pager');
	$this->pager += (array)$this->elements[$pgid];

	if ($_GET['grid'] == $this->name or !$_GET['grid']) {
		if ($_GET['page']) {
			$page = $_GET['page'];
			$this->pager['page'] = ($page === 'all')? 'all' : (int)$page;
		}
		if (isset($_GET['sort'])) $this->setSort($_GET['sort']);
	}
}

/**
 * Display datagrid.
 * @param string $block If set, only block $block will be shown.
 */
protected function _out($block = null)
{
	$this->getPager();
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

	$this->length = $this->db->count($sql);

	if ($lpos = stripos($sql, ' limit ')) $sql = substr($sql, 0, $lpos);
	$this->sql = $sql;
	if (!$this->document) $this->create($this->sql);
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
	$this->length = $totalLength? $totalLength : count($this->dataArray);
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

	$cmd = "return (\$a['pos'] < \$b['pos']) ? -1:+1;";
	uasort($this->sumArray, create_function('$a,$b', $cmd));
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

function setPage($page)
{
	$this->pager['page'] = $page;
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
	$this->pager['page'] = $s['page'];
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
		'page'   => $this->pager['page'],
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
	$this->app->deleteSession("$sessName.hash");
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
 * You can reimplement this in descendant for your own pager.
 * @copydoc tag-handler
 */
function print_Pager($id, $sub)
{
	if ($this->pager['hidden']) return;

	//default pager
	if (!$sub) {
		if ($this->pager['ul']) {
			$this->print_Pager($id, 'first');
			$this->print_Pager($id, 'pages');
			$this->print_Pager($id, 'last');
		}
		else {
			$this->print_Pager($id, 'first');
			print " | ";
			$this->print_Pager($id, 'last');
			print " | ";
			$this->print_Pager($id, 'pages');
		}
		return;
	}

	//list of pages
	if ($sub == 'pages') {
		$page  = $this->pager['page'];
		if ($page == 'all') return;
		$begin = $this->pager['begin'];
		$end   = $this->pager['end'];

		$activefmt = $this->pager['active'];

		for ($i = $begin; $i <= $end; $i++) {
			if ($i == $page) {
				$activepage = $activefmt? sprintf ($activefmt, $i) : $i;
				$this->printPagerItem($activepage, $sub, null, true);
			}
			else {
				$url = $this->pagerUrl($i);
				$this->printPagerItem($i, $sub, $url);
			}
		}
		return;
	}

	switch ($sub) {
			case "total":   print $this->length;           break;
			case "maxpage": print $this->pager['maxpage']; break;
			case "page":    print $this->pager['page'];    break;
			case "first":  $url = $this->pagerUrl(1);     break;
			case "last":   $url = $this->pagerUrl($this->pager['maxpage']); break;
			case "next":   $url = $this->pagerUrl($this->pager['next']);    break;
			case "all":    $url = $this->pagerUrl('all');  break;
			case "prev":
			case "previous": $url = $this->pagerUrl($this->pager['prev']); break;
	} //switch

	if ($url) {
		$lb = $this->t(ucfirst($sub));
		$this->printPagerItem($lb, $sub, $url);
	}
}

protected function printPagerItem($lb, $sub, $url = null, $active = false)
{
	$cls = '';
	if ($active) $cls .= ' active';
	if (!$url)   $cls .= ' disabled';
	if ($cls) $cls = ' class="'.trim($cls).'"';
	if ($this->pager['ul']) {
		print "<li$cls><a href=\"$url\">$lb</a></li>";
	}
	else {
		$s = $url? "<a href=\"$url\">$lb</a>" : $lb;
		print $this->pager['separ']."<span$cls>$s</span>";
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
protected function print_Class_Item($id, $sub)
{
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

/**
 * Load values for current page from database (fill \ref tpl::values array).
 * tpl::values are not cleared, so you can add additional fields from the code.
 */
protected function getValues()
{
	if ($this->dataArray) return $this->getDataArray();

	$q = $this->getQuery();
	if (!$q) {$this->length = 0; return array(); }

	$rows = $this->db->fetchAll($q);

	//sumgrid hack...
	if (count($rows) > $this->pager['pglen']) $last = array_pop($rows);
	if ($this->sumArray) $this->sumArray['items']['last'] = $last;

	return $rows;
}


/**
 * Load values for current page from dataarray (array-based %grid).
 * @see getvalues(), setarray()
 */
protected function getDataArray()
{
	$pglen = $this->pager['pglen'];

	$begin = ($this->pager['page'] - 1) * $this->pager['pglen'];

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
			$orderby .= ','.(($sort and $sort != '1')? $sort : $id);
			if ($id != $sval) $orderby .= ' desc';
		}
		if ($orderby) $sql .= ' order by '.substr($orderby, 1);
	}

	$page  = $this->pager['page'];
	$pglen = $this->pager['pglen'];

	if ($page != 'all')
		$this->db->setLimit($pglen + 1, ((int)$page-1) * $pglen);

	return $this->db->query($sql);
}

/**
 * Build default grid template.
 * @see Tpl::create()
 */
function create($dsstr, $fileName = null, $template = null)
{
	$trans = array('<:' => '<', ':>' => '>', '{:' => '{', ':}' => '}');
	if (!$template) $template = PCLIB_DIR.'assets/def_grid.tpl';

	$table = $this->db->tableName($dsstr);
	$columns = $this->db->columns($table);

	$fields = $this->getFields($dsstr);
	$head = $body = array();
	foreach($fields as $id) {
		$col = $columns[$id];
		$lb = ifnot($col['comment'], $id);
		$elem .= "string $id lb \"$lb\" sort";
		if ($col['type'] == 'date') $elem .= ' date';
		$elem .= "\n";
		$head[]['LABEL'] = '{'.$id.'.lb}';
		$body[]['FIELD'] = '{'.$id.'}';
	}

	$t = new Tpl($template);
	$t->values['NAME'] = $this->db->tableName($dsstr);
	$t->values['HEAD'] = $head;
	$t->values['BODY'] = $body;
	$t->values['ELEMENTS'] = trim($elem);
	$html = strtr($t->html(), $trans);

	if ($fileName) {
		$ok = file_put_contents($fileName, $html);
		if (!$ok) throw new IOException("Cannot write file $fileName.");
		else @chmod($fileName, 0666);
	}
	else {
		$this->loadString($html);
		$this->init();
	}
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

/**
 * Fill #$pager array with up-to-date values.
 * @see print_pager()
 */
function getPager()
{
	if (!$this->pager['page'])  $this->pager['page'] = 1;
	if (!$this->pager['size'])  $this->pager['size'] = 10;
	if (!$this->pager['separ']) $this->pager['separ'] = ' ';
	if (!$this->pager['pglen']) $this->pager['pglen'] = $this->length;

	if (!$this->pager['pglen']) $this->pager['maxpage'] = 0;
	else $this->pager['maxpage'] = ceil($this->length / $this->pager['pglen']);

	if ($this->pager['page'] > $this->pager['maxpage'])
		$this->pager['page'] = $this->pager['maxpage'];

	if ($this->pager['maxpage'] < 2 and !$this->pager['nohide'])
		$this->pager['hidden'] = true;
	else
		$this->pager['hidden'] = false;

	if ((string)$this->pager['page'] == 'all') {
		$this->pager['pglen'] = $this->length;
	}

	list($this->pager['begin'], $this->pager['end'])
		= $this->pagerRange($this->pager['page'], $this->pager['size']);

	$this->pager['prev'] = $this->pager['page'] - 1;
	$this->pager['next'] = $this->pager['page'] + 1;
	if ($this->pager['prev'] < 1) $this->pager['prev'] = 1;
	if ($this->pager['next'] > $this->pager['maxpage'])
		$this->pager['next'] = $this->pager['maxpage'];
}

/** Return \<begin,end> interval of pages around $page, with $size = end - begin.
 * Interval is always inside total range of pages.
 * @see getpager()
 */
protected function pagerRange($page, $size)
{
	$maxpage = $this->pager['maxpage'];
	$middle = floor($size / 2);

	if ($maxpage > $size) {
		if ($page > $middle) $begin = $page - $middle + 1; else $begin = 1;
		if ($maxpage - $page <= $middle) $begin = $maxpage - $size + 1;
		$end = $begin + $size - 1;
		return array($begin, $end);
	}
	else return array(1, $this->pager['maxpage']);

}

/** Return %grid url for page $page.
 * @see print_pager()
 */
protected function pagerUrl($page)
{
	return $this->baseUrl."page=$page";
}


private function sumFieldEquals(array $sum)
{
	$rowno = $this->elements['items']['rowno'];
	if ($this->sumArray['items']['pos'] > $sum['pos']) $rowno--;
	$v1 = $this->values['items'][$rowno][$sum['field']];
	$v2 = $this->values['items'][$rowno+1][$sum['field']];
	if ($rowno == $this->pager['pglen']-1) $v2 = $this->sumArray['items']['last'][$sum['field']];
	return ($v1 == $v2);
}

protected function print_BlockRow($block, $rowno = null)
{
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
}

protected function parseLine($line)
{
	$id = parent::parseLine($line);
	if ($this->elements[$id]['type'] == 'pager')
		$this->header['pager'] = $id;
	return $id;
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
