<?php 
/**
 * @file
 * Display Tree structure. 
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
 * Display tree structure. Default template will show tree as unordered list (UL).
 * Features:
 * - Load/save tree from/to database table TREE_LOOKUPS
 * - Import/export tree from/to text file
 * - Build tree from database query (using parent_id)
 */
class Tree extends system\BaseObject
{

  /** var Db Link to database object. */
  public $db;

  protected $nodes;
  protected $length;
  protected $options;
  protected $index;

  protected $router;

  /** Database table for storing %tree data. */
  public $table = 'TREE_LOOKUPS';

  /** var Tpl %Tree template. */
  public $tpl;

  public $filter;

  /**
   * Create %Tree instance.
   * @param string $path Filename of template file. By default it uses default-tree.tpl.
   */
  function __construct($options = [])
  {
    parent::__construct();

    $defaults = ['table' => 'TREE_LOOKUPS', 'template' => PCLIB_DIR.'assets/default-tree.tpl'];

    $this->options = $options + $defaults;
    $this->reset();
  }

  protected function addNode($data)
  {
    if (!isset($data['LEVEL']) or !isset($data['LABEL'])) {
      throw new Exception("Wrong tree node.");
    }

    $data['OPEN'] = '';
    if ($this->length) {
      $last = &$this->nodes[$this->length - 1];
      $last['FOLDER'] = $last['LEVEL'] < $data['LEVEL'] ? 'folder' : '';
    }

    if (empty($data['ID'])) $data['ID'] = $this->length;

    $this->index[$data['ID']] = $this->length;

    $this->nodes[] = $data;
    $this->length++;
  }

  protected function reset()
  {
    $this->nodes = [];
    $this->index = [];
    $this->length = 0;
  }

  /**
   * Import %Tree from text string.
   * @param string $s Source string. See https://pclib.brambor.net/demo/?r=source/tree.txt
   */
  function importText($s)
  {
    $this->reset();
    $lines = explode("\n", $s);
    $cells = explode('|', trim(array_shift($lines)));

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line == '') continue;
      $data = $this->readLine($line, $cells);
      $this->addNode($data);
    }
  }
  
    /**
   * Export current %Tree to text string.
   * @return string $s %Tree as text.
   */
  function exportText()
  {
    $s = "PATH|ROUTE\n";

    $branch = [];

    foreach($this->nodes as $node)
    {
      $branch[$node['LEVEL']] = $node['LABEL'];
      $s .= implode('/', array_slice($branch, 0, $node['LEVEL'] + 1));
      $s .= '|'.$node['ROUTE']."\n";
    }

    return $s;
  }

  /**
   * Import %Tree from array of $nodes.
   * @param array $nodes [['ID'=>'','LABEL'=>'','LEVEL'=>'',...],...]
   */
  function fromArray($nodes)
  {
    $this->reset();
    foreach ($nodes as $node) {
      $this->addNode($node);
    }
  }

  /**
   * Export %Tree as array of $nodes.
   * @return array $nodes 
   */
  function toArray()
  {
    return $this->nodes;
  }

  private function readLine($line, $cells)
  {
    $part = explode('|', $line);
    $node = [];
    foreach($cells as $i=>$name) { $node[$name] = array_get($part, $i); }
    $path = $node['PATH']; unset($node['PATH']);
    $node['LEVEL'] = substr_count($path, '/');
    $node['LABEL'] = $node['LEVEL']? substr($path,strrpos($path, '/') + strlen('/')) : $path;
    if(!isset($node['ACTIVE'])) $node['ACTIVE'] = 1;
    return $node;
  }

  /**
   * Get %Tree node.
   * @param int $nodeId
   * @return array $node
   */
  function get($nodeId)
  {
    $key = $this->index[$nodeId];
    return $this->nodes[$key] ?: null;
  }

  /**
   * Set %Tree node values.
   * @param int $nodeId
   * @param array $data Data of node to be set.
   */
  function set($nodeId, $data)
  {
    if (!isset($this->index[$nodeId])) throw new Exception("Node not found.");
    $key = $this->index[$nodeId];
    $this->nodes[$key] = $data + $this->nodes[$key];
  }

  /**
   * Find %Tree node by node parameter.
   * @param string $key Node key
   * @param string $value Value of node key
   * @return array $node
   */
  function find($key, $value)
  {
    foreach ($this->nodes as $node) {
      if ($node[$key] == $value) return $node;
    }
  }

  /**
   * Load %tree from database query. Expected fields: ID, LABEL, PARENT_ID.
   * @param string $sql Database query
   * @param int $topId
   */
  function fromQuery($sql, $topId = 0)
  {
    $this->service('db');

    $rows = $this->db->selectAll($sql);
    $all = $children = [];

    foreach ($rows as $row)
    {
      $row = array_change_key_case($row, CASE_UPPER);

      $children[$row['PARENT_ID']][] = $row['ID'];
      $all[$row['ID']] = $row;
    }

    $nodes = [];
    $this->reset();
    $this->fillNodes($nodes, $children, $all, $topId, 0);
    $this->fromArray($nodes);
  }

  private function fillNodes(&$nodes, $tree, $data, $id, $level)
  {
    foreach ((array)$tree[$id] as $_id) {
      $row = $data[$_id];
      $row['LEVEL'] = $level;
      if (isset($tree[$_id])) $row['FOLDER'] = 'folder';
      $nodes[] = $row;
      if (isset($tree[$_id])) $this->fillNodes($nodes, $tree, $data, $_id, $level + 1);
    }
  }

  /**
   * Load %tree from table tree_lookups.
   * @param int $treeId 
   */
  function load($treeId, $topId = 0, $maxLevel = 0)
  {
    $this->service('db');

    $table = $this->options['table'];

    $flt = '';

    if ($topId) {
      $first = $this->db->select($table, pri($topId));     
      $last = $this->db->select($table, "TREE_ID='$treeId' AND ID>'{ID}' AND LEVEL<='{LEVEL}' ORDER BY ID", $first);
      $flt .= $last? sprintf("AND ID BETWEEN %d AND %d", $first['ID'], $last['ID'] - 1) : "AND ID>".$first['ID'];
    }

    if ($maxLevel) {
      $flt .= ' AND LEVEL<='.$maxLevel;
    }

    $nodes = $this->db->selectAll($table, "TREE_ID={#0} AND ACTIVE=1 $flt ORDER BY ID", $treeId);
    $this->fromArray($nodes);
  }

  /**
   * Save current %tree to table tree_lookups.
   * @param int $treeId 
   */
  function save($treeId)
  {
    $this->service('db');

    $table = $this->options['table'];

    $this->db->delete($table, "TREE_ID={#0}", $treeId);

    foreach ($this->nodes as $node)
    {
      $data = [
        'TREE_ID' => $treeId,
        'LABEL' => $node['LABEL'],
        'LEVEL' => $node['LEVEL'],
        'ROUTE' => $node['ROUTE'],
        'URL'   => $node['URL'],
        'RKEY'  => $node['RKEY'],
        'ACTIVE' => $node['ACTIVE'],
      ];

      $this->db->insert($table, $data);
    }
  }

  /**
   * Expand path(s) in current %tree.
   * @param int|array Id of nodes, which should be expanded (opened)
   * Ex: $tree->expand(1,4,20) Expand %tree to nodes 1,4,20
   */
  function expand()
  {
    $list = func_get_args();
    if (is_array($list[0])) $list = $list[0];

    foreach($this->nodes as $i => $node)
    {
      if (in_array($node['ID'], $list)) $this->expandPath($i);
      elseif (!$list) $this->nodes[$i]['OPEN'] = 'open';
    }
  }

    /**
   * Expand (open) current %tree to node $nodeKey.
   * @param int $nodeKey Key in Tree::nodes array
   * @see expand()
   */
  protected function expandPath($nodeKey)
  {
    $level = 999;
    
    for($i = $nodeKey; $i>=0; $i--)
    {
      if ($this->nodes[$i]['LEVEL'] < $level) {
        $this->nodes[$i]['OPEN'] = 'open';
        $level = $this->nodes[$i]['LEVEL'];
      }
    }
  }

  /**
   * Expand %tree to level $level.
   */
  function expandLevel($level)
  {
    foreach($this->nodes as $i => $node) {
      if ($node['LEVEL'] <= $level) $this->nodes[$i]['OPEN'] = 'open';
    }
  }

  /**
   * Return html output of the current tree.
  **/
  public function html()
  {
    $t = new PCTpl($this->options['template']);

    $i = 0;
    $html = '';
    while($node = array_get($this->nodes, $i++))
    {
      if (is_callable($this->filter)) {
        $node = call_user_func($this->filter, $this, $node);
      }

      if (!$node['ACTIVE']) {
        $i = $this->nextSibling($i - 1);
        continue;
      }

      $next = array_get($this->nodes, $i);

      if (!empty($node['ROUTE'])) {
        $node['URL'] = $this->service('router')->createUrl($node['ROUTE']);
      }

      if (!$next) {
        $next['LEVEL'] = 0;
      }

      if (!empty($node['FOLDER'])) {
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
  }

  function __toString()
  {
    try {
      return $this->html();
    } catch (Exception $e) {
      trigger_error($e->getMessage(), E_USER_ERROR);
    }
  }

  private function nextSibling($i)
  {
    $start = $this->nodes[$i++];
    while($node = $this->nodes[$i++]) {
      if ($node['LEVEL'] <= $start['LEVEL']) return --$i;
    }
    return $i;
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

}

 ?>