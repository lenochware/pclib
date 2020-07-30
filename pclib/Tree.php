<?php
/**
 * @file
 * UL-tree generator
 *
 * @author -dk- <lenochware@gmail.com>
 * @link http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Load tree structure and render it as unordered list (ul).
 * Features:
 * - Load/save tree from database table or text file
 * - Add or remove any node
 * - Display, move or delete any subtree using fast algorithm
 * - Tweak formatting of html output
 */

class Tree extends system\BaseObject
{

/** var Db Link to database object. */
public $db;

/** var Translator */
public $translator;

/** Flat array of %tree nodes.*/
public $nodes = array();

/** Pattern for %tree node with URL. */
public $patternLink = "<li id=\"{ID}\" {ATTR}><span><a href=\"{URL}\">{LABEL}</a></span></li>";

/** Pattern for %tree node. */
public $pattern = "<li id=\"{ID}\" {ATTR}><span>{LABEL}</span></li>";

/** Create link on folders too. */
public $linkFolders = true;

/** Database table for storing %tree data. */
public $table = 'TREE_LOOKUPS';

public $cssClass;

/** var Router */
protected $router;

private $id;
private $name;

private $i = 0; //current node index

/** Separator of cells in tree text file */
public $CELL_SEPAR = '|';

/** Separator of tree levels in tree text file */
public $LEVEL_SEPAR = '/';

/**
 * Create %Tree instance.
 * @param string $cssClass CSS-class of the ul-tree.
 */
function __construct($cssClass = 'pctree')
{
	parent::__construct();
	$this->cssClass = $cssClass;
	$this->service('db');
}

/**
 * Return current index to Tree::nodes array.
 */
protected function currentIndex()
{
	return $this->i;
}

/**
 * Generate HTML for list open tag - UL.
 */
protected function htmlListBegin($level)
{
		if ($this->i == 0) {
		$html = "<ul class=\"$this->cssClass\"";
		$html .= $this->name? " id=\"$this->name\">" : '>';
	}
	else $html = '<ul>';

	return $html;
}

public function htmlTemplate($path)
{
	$t = new PCTpl($path);

	$i = 0;
	$html = '';
	while($node = $this->nodes[$i++]) {
		$next = $this->nodes[$i];

		if ($node['ROUTE']) {
			$node['URL'] = $this->service('router')->createUrl($node['ROUTE']);
		}

		if (!$this->linkFolders and $node['HASCHILD']) {
			$node['URL'] = null;
		}

		if (!$next) {
			$next['LEVEL'] = 0;
		}

		if ($node['HASCHILD']) {
			$node['STATE'] = $node['EXPANDED']? 'open' : 'closed';
			$html .= $this->getHtmlTplStrip($t, 'folderBegin', $node);
		}
		else {
			$html .= $this->getHtmlTplStrip($t, 'item', $node);
		}

		if ($next['LEVEL'] < $node['LEVEL']) {
			$n = $node['LEVEL'] - $next['LEVEL'];
			$closeHtml = $this->getHtmlTplStrip($t, 'folderEnd', $node);
			$html .= str_repeat($closeHtml, $n);
		}
	}

	return $html;

	//return "<ul class=\"$this->cssClass\">$html</ul>";
}

private function getHtmlTplStrip($t, $strip, $node)
{
	$t->values = $node;
	if ($strip == 'item') return $t->html('item');
	else {
		$t->values['items'] = '__items__';
		list($begin, $end) = explode('__items__',  $t->html('folder'));
		return ($strip == 'folderBegin')? $begin : $end;
	}

}

/**
 * Generate %tree HTML. Recursive function.
 * @param array $nodes Flat array of %tree nodes
 * @param int $level Current %tree level
 * @return string $html Tree html code
 */
protected function htmlTree($level = 0)
{
	$html = $this->htmlListBegin($level);

	while($node = $this->nodes[$this->i++]) {
		
		if (!$node['ACTIVE']) {
			$this->skipInactive($node);
			continue;
		}

		if ($node['LEVEL'] > $level) {
			$this->i--;
			$html = substr($html,0,strrpos($html,'<'));
			$html .= $this->htmlTree($node['LEVEL']) . "</li>";
		}
		elseif($node['LEVEL'] < $level) {
			$this->i--;
			$html .= "</ul>";
			return $html;
		}
		else {
			if ($this->nodes[$this->i]['LEVEL'] > $node['LEVEL']) $node['HASCHILD'] = true;
			$html .= $this->htmlListNode($node);
		}
	}
	$html .= "</ul>";
	return $html;
}

private function skipInactive($inactiveNode)
{
	while($node = $this->nodes[$this->i++]) {
		if ($node['LEVEL'] <= $inactiveNode['LEVEL']) {$this->i--; return;}
	}
}

/**
 * Generate HTML for %tree node - LI.
 * Tree::pattern and Tree::pattern_link are used for drawing.
 * @param array $node Tree node (must contain LABEL, LEVEL)
 * @return string $html Node html code
 * @see htmltree()
 */
protected function htmlListNode($node)
{
	$class = $node['HASCHILD']? ($node['EXPANDED']? 'folder open' : 'folder closed') : 'item';
	$class = trim($node['CLASS'].' '.$class);
	if ($class) $node['ATTR'] = trim($node['ATTR']." class=\"$class\"");
	if (!$node['ID']) $node['ID'] = $this->i;
	if ($node['ROUTE']) {
		$node['URL'] = $this->service('router')->createUrl($node['ROUTE']);
	}

	if (!$this->linkFolders and $node['HASCHILD']) {
		$node['URL'] = null;
	}

	$this->service('translator', false);
	$node['LABEL'] = $this->translator? $this->translator->translate($node['LABEL']) : $node['LABEL'];

	$pattern = $node['URL']? $this->patternLink : $this->pattern;
	return paramstr($pattern, $node);
}

/**
 * Return HTML of current %tree.
 * Build %tree from Tree::nodes and return HTML.
 */
function html()
{
	$this->i = 0;
	return $this->htmlTree();
}

/**
 * Print current %tree.
 * Build %tree from Tree::nodes and print it.
 */
function out() { print $this->html(); }

function __toString()
{
	try {
		return $this->html();
	} catch (Exception $e) {
		trigger_error($e->getMessage(), E_USER_ERROR);
	}
}

/**
 * Load %tree from the text file.
 * The file is simple CSV file, containing columns for path and url.
 * Path to the each node are labels separated with slash '/'.
 * @param string $fileName Path to csv-file
 */
function load($fileName)
{
	if (!file_exists($fileName))
		throw new FileNotFoundException("File $fileName not found.");
	$this->name = extractpath($fileName, '%f');
	$this->id = null;
	$this->setString(file_get_contents($fileName));
}

/**
 * Load %tree from the text string.
 * @param string $str String with %tree definition
 * @see load()
 */
function setString($str)
{
	$nodes = array();
	$lines = explode("\n", $str);
	$cells = explode($this->CELL_SEPAR, trim(array_shift($lines)));
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line == '') continue;
		$nodes[] = $this->readLine($line, $cells);
	}
	$this->nodes = $nodes;
}

/**
 * Load %tree from the query. Expect fields ID, LABEL, PARENT_ID
 */
function setQuery($sql)
{
	$rows = $this->db->selectAll($sql);
	$all = $children = [];
	foreach ($rows as $row)
	{
		$children[$row['PARENT_ID']][] = $row['ID'];
		$all[$row['ID']] = $row;
	}

	$nodes = [];
	$this->fillNodes($nodes, $children, $all, 0, 0);
	$this->nodes = $nodes;

	//dump($nodes);
}

private function fillNodes(&$nodes, $tree, $data, $id, $level)
{
	foreach ($tree[$id] as $_id) {
		$row = $data[$_id];
		$row['LEVEL'] = $level;
		$row['ACTIVE'] = 1;
		if ($tree[$_id]) $row['HASCHILD'] = 1;
		$nodes[] = $row;
		if ($tree[$_id]) $this->fillNodes($nodes, $tree, $data, $_id, $level + 1);
	}
}

private function readLine($line, $cells)
{
	$part = explode($this->CELL_SEPAR, $line);
	$node = array();
	foreach($cells as $i=>$name) { $node[$name] = $part[$i]; }
	$path = $node['PATH']; unset($node['PATH']);
	$node['LEVEL'] = substr_count($path, $this->LEVEL_SEPAR);
	$node['LABEL'] = $node['LEVEL']? substr($path,strrpos($path, $this->LEVEL_SEPAR)+strlen($this->LEVEL_SEPAR)) : $path;
	if(!isset($node['ACTIVE'])) $node['ACTIVE'] = 1;
	return $node;
}

function setValue($node_id, $name, $value)
{
	foreach ($this->nodes as $i => $node) {
		if ($node['ID'] == $node_id) {
			$this->nodes[$i][$name] = $value;
			return;
		}
	}
}

/**
 * Load %tree $tree_id from table Tree::table.
 * @param int $tree_id Unique id of the %tree
 * @param int $node_id If set, this will load subtree of node $node_id
 * @param int $maxlevel Load %tree up to level $maxlevel
 */
function getTree($tree_id, $node_id = null, $maxlevel = null)
{
	if ($node_id) {
		$this->getSubtree($node_id, $maxlevel);
	}
	else {
		$flt = isset($maxlevel)? 'AND LEVEL<='.$maxlevel : '';
		$this->nodes = $this->db->selectAll($this->table, "TREE_ID={#0} $flt ORDER BY NR", $tree_id);
	}

	$this->id = $tree_id;
	$this->name = 'tree'.$tree_id;
}

/**
 * Load subtree of node $node_id from table Tree::table.
 * @param int $node_id Tree node
 * @param int $maxlevel Load %tree up to level $maxlevel
 * @see gettree()
 */
function getSubTree($node_id, $maxlevel = null)
{
	$flt = isset($maxlevel)? 'AND LEVEL<='.$maxlevel : '';
	$start = $this->getNode($node_id);
	$next = $this->nextNode($node_id);
	if ($next)
		$this->nodes = $this->db->selectAll($this->table,
			"TREE_ID={0} AND NR BETWEEN {1} AND {2} $flt ORDER BY NR",
			$start['TREE_ID'], $start['NR'], $next['NR'] - 1
		);
	else {
		$this->nodes = $this->db->selectAll($this->table,
			"TREE_ID={0} AND NR>={1} $flt ORDER BY NR", $start['TREE_ID'], $start['NR']
		);
	}

	$this->id = null;
	$this->name = 'subtree'.$start['TREE_ID'];
}

/**
 * Add current %tree to the database table.
 * @param int $tree_id Unique id of the %tree
 * @param int $node_id If set, %tree is added under $node_id as subtree
 */
function addTree($tree_id, $node_id = null)
{
	if ($node_id) $this->addSubtree($node_id);
	else {
		$this->db->delete($this->table, "TREE_ID='{0}'", $tree_id);
		$i = 1;
		foreach($this->nodes as $node) {
			unset($node['ID']);
			$node['TREE_ID'] = $tree_id;
			$node['NR'] = $i++;
			$this->db->insert($this->table, $node);
		}
	}

	$this->getTree($tree_id);
}

/**
 * Add current %tree as subtree of $node_id.
 * @param int $node_id Tree is added under $snode_id into database table
 * @see addtree()
 */
function addSubTree($node_id)
{
	$to = $this->getNode($node_id);

	$n_nodes = count($this->nodes);
	$this->makeGap($to, $n_nodes);

	for($i = 0; $i < $n_nodes; $i++) {
		$node = $this->nodes[$i];
		unset($node['ID']);
		$node['TREE_ID'] = $to['TREE_ID'];
		$node['NR'] = $to['NR']+$i+1;
		$node['LEVEL'] = $to['LEVEL'] + $node['LEVEL']+1;
		$this->db->insert($this->table, $node);
	}

	$this->getTree($to['TREE_ID']);
}

protected function getLastNode($tree_id)
{
	return $this->db->select($this->table, "TREE_ID='{#0}' ORDER BY NR desc", $tree_id);
}

/**
 * Insert node $node after $node_id.
 * @param int $node_id Node in database table
 * @param array $node Tree node
 * @return int $node_id Id of added node
 */
function addNode($node_id, array $node)
{
	if (!$node_id) {
		if (!$node['TREE_ID']) throw new NoValueException('Missing TREE_ID.');

		$last = $this->getLastNode($node['TREE_ID']);
		
		$node['NR'] = $last? $last['NR']+1 : 1;
		$node['LEVEL'] = 0;
		$id = $this->db->insert($this->table, $node);

		return $id;
	}

	$to = $this->getNode($node_id);
	$next = $this->nextNode($node_id);

	$node['LEVEL'] = $to['LEVEL'];
	$node['TREE_ID'] = $to['TREE_ID'];

	if ($next) {
		$node['NR'] = $next['NR'];
		$this->makeGap($next, 1, true);
		$id = $this->db->insert($this->table, $node);
	}
	else {
		$last = $this->getLastNode($node['TREE_ID']);
		$node['NR'] = $last['NR']+1;
		$id = $this->db->insert($this->table, $node);
	}

	return $id;
}

/**
 * Insert node $node before $node_id.
 * @param int $node_id Node in database table
 * @param array $node Tree node
 * @return int $node_id Id of added node 
 */
function addNodeBefore($node_id, array $node)
{
	$to = $this->getNode($node_id);

	$node['NR']    = $to['NR'];
	$node['LEVEL'] = $to['LEVEL'];
	$node['TREE_ID'] = $to['TREE_ID'];

	$this->makeGap($to, 1, true);
	$id = $this->db->insert($this->table, $node);
	return $id;
}

/**
 * Insert node $node as child of $node_id.
 * @param int $node_id Node in database table
 * @param array $node Tree node
 * @return int $node_id Id of added node 
 */
function addChild($node_id, array $node)
{
	$to = $this->getNode($node_id);
	$children = $this->getChildren($node_id);
	$last = $children? end($children) : $to;
	
	$node['NR']    = $last['NR'] + 1;
	$node['LEVEL'] = $to['LEVEL'] + 1;
	$node['TREE_ID'] = $to['TREE_ID'];

	$this->makeGap($last);
	$id = $this->db->insert($this->table, $node);
	return $id;
}

/**
 * Return children of node_id as flat array.
 * @param int $node_id Node id in database table
 * @return array $nodes Flat array of nodes
 */
function getChildren($node_id)
{
	$node = $this->getNode($node_id);
	$next = $this->nextNode($node_id);

	if ($next) {
			$children = $this->db->selectAll($this->table, 
				'TREE_ID={#0} AND NR BETWEEN {#1} AND {#2} ORDER BY NR', 
				$node['TREE_ID'], $node['NR'] + 1, $next['NR'] - 1
			);
	}
	else {
		$children = $this->db->selectAll($this->table, 
			"TREE_ID='{TREE_ID}' AND NR>'{NR}'", $node
		);
	}

	return $children;
}

/**
 * Add children to node_id.
 * @param int $node_id Node id in database table
 * @param array $nodes Flat array of nodes
 */
function addChildren($node_id, array $nodes)
{
	if (!$nodes) return;

	$to = $this->getNode($node_id);
	
	$children = $this->getChildren($node_id);

	if ($children) {
		$last = end($children);
		$nr = $last['NR'];
		$this->makeGap($last, count($nodes));
	}
	else {
		$nr = $to['NR']+1;
		$this->makeGap($to, count($nodes));		
	}

	$baseLevel = $to['LEVEL'] - $nodes[0]['LEVEL'] + 1;

	foreach ($nodes as $node) {
		$node['ID'] = null;
		$node['NR'] = $nr++;
		$node['LEVEL'] += $baseLevel;
		$node['TREE_ID'] = $to['TREE_ID'];
		$this->db->insert($this->table, $node);
	}
}

/**
 * Remove children of node_id from database table.
 * @param int $node_id Node id in database table
 */
function rmChildren($node_id)
{
	$children = $this->getChildren($node_id);
	if (!$children) return;
	$firstChild = $children[0];
	$lastChild = end($children);

	$this->db->delete($this->table, 'TREE_ID={#0} AND NR BETWEEN {#1} AND {#2}',
			$firstChild['TREE_ID'], $firstChild['NR'], $lastChild['NR']
	);
}

/**
 * Remove node from database table.
 * @param int $node_id Node id in database table
 */
function rmNode($node_id)
{
	$this->rmChildren($node_id);
	$this->db->delete($this->table, pri($node_id));
}

/**
 * Expand path(s) in current %tree.
 * @param int|array Id of nodes, which should be expanded (opened)
 * Ex: $tree->expand(1,4,20) Expand %tree to nodes 1,4,20
 */
function expand()
{
	$nodelist = func_get_args();
	if (is_array($nodelist[0])) $nodelist = $nodelist[0];
	foreach($this->nodes as $i => $node) {
		if (in_array($node['ID'], $nodelist)) $this->expandPath($i);
		elseif (!$nodelist) $this->nodes[$i]['EXPANDED'] = 1;

	}
}

/**
 * Expand %tree to level $level.
 */
function expandLevel($level)
{
	foreach($this->nodes as $i => $node) {
		if ($node['LEVEL'] <= $level) $this->nodes[$i]['EXPANDED'] = 1;
	}
}

/**
 * Pack node numbers (NR).
 */
function packTree($tree_id)
{
	$seqid = $this->db->selectOne($this->table.':ID', "TREE_ID='{0}' ORDER BY NR", $tree_id);
	$nr = 1;
	foreach($seqid as $id) {
		$this->db->update($this->table, 'NR='.($nr++), pri($id));
	}
}

/**
 * Expand (open) current %tree to node $node_key.
 * @param int $node_key Key in Tree::nodes array
 * @see expand()
 */
protected function expandPath($node_key)
{
	$level = 999;
	for($i = $node_key; $i>=0; $i--) {
		if ($this->nodes[$i]['LEVEL'] < $level) {
			$this->nodes[$i]['EXPANDED'] = 1;
			$level = $this->nodes[$i]['LEVEL'];
		}
	}
}

public function getNode($node_id)
{
	$node = $this->db->select($this->table, pri($node_id));
	if (!$node) throw new \pclib\Exception('Node '.$node_id.' not found.');
	return $node;
}

/**
 * Return next node with level <= level of $node_id.
 * @see getSubTree()
 */
protected function nextNode($node_id)
{
	$start = $this->getNode($node_id);
	$node = $this->db->select($this->table, "TREE_ID='{TREE_ID}' AND NR>'{NR}' AND LEVEL<='{LEVEL}' ORDER BY NR", $start);
	return $node;
}

/**
 * Make $n_nodes gap before/after $node. Used before adding new nodes in the middle.
 */
protected function makeGap(array $node, $n_nodes=1, $before = false)
{
	$op = $before? '>=' : '>';
	$this->db->update($this->table, "NR=NR+$n_nodes", "TREE_ID='{TREE_ID}' AND NR $op '{NR}'", $node);
}

}

?>