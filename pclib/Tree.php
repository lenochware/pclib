<?php 
/**
 * @file
 * Display Tree structure. 
 *
 * @author -dk- <lenochware@gmail.com>
 * @link https://pclib.brambor.net/
 * @license MIT (https://opensource.org/licenses/MIT)
 */

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
  protected $index;

  protected $router;

  /** Database table for storing %tree data. */
  public $table = 'TREE_LOOKUPS';

  /** var Tpl %Tree template. */
  public $tpl;

  public $values;

  /** If set, it will check user rights against RKEY column. */
  public $auth = null;

  /**
   * Create %Tree instance.
   * @param string $path Filename of template file. By default it uses default-tree.tpl.
   */
  function __construct($path = '')
  {
    parent::__construct();

    if (!$path)  $path = PCLIB_DIR.'tpl/default-tree.tpl';

    $this->tpl = new PCTpl($path);
    $this->reset();
  }

  /**
   * Called everytime when adding node to the tree.
   */
  protected function addNode($data)
  {
    if (!isset($data['LEVEL']) or !isset($data['LABEL'])) {
      throw new Exception("Wrong tree node.");
    }

    $data['OPEN'] = '';
    if ($this->length) {
      $last = &$this->nodes[$this->length - 1];
      $last['FOLDER'] = $last['LEVEL'] < $data['LEVEL'] ? 'folder' : '';
      if ($last['FOLDER']) $last['OPEN'] = 'closed';
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
   * @param string $format Exported cells - list of cell names, separated by "|"
   * @return string $s %Tree as text.
   */
  function exportText($format = "PATH|ROUTE")
  {
    $s = $format . "\n";

    $cells = explode("|", $format);

    $branch = [];

    foreach($this->nodes as $node)
    {
      $branch[$node['LEVEL']] = $node['LABEL'];
      $node['PATH'] = implode('/', array_slice($branch, 0, $node['LEVEL'] + 1));

      $line = [];
      foreach ($cells as $id) {
        $line[] = $node[$id];
      }

      $s .= trim(implode("|", $line), "|")."\n";
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

    if ($this->auth) $this->map([$this, 'authFilter']);

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
   * Add %Tree node.
   * @param int $nodeId Add new node after/before this node
   * @param array $data Data of node to be added.
   */
  function add($nodeId, $data, $options = ['before' => false, 'child' => false])
  {
    if (!isset($this->index[$nodeId])) throw new Exception("Node not found.");
    $target = $this->get($nodeId);
    $key = $this->index[$nodeId] + 1;

    $data['LEVEL'] = $target['LEVEL'] + $options['child']? 1:0;
    if (!isset($data['ACTIVE'])) $data['ACTIVE'] = 1;

    if ($options['before'] and !$options['child']) $key--;

    $nodes = $this->nodes;
    array_splice($nodes, $key, 0, [$data]); // insert at position $key
    $this->fromArray($nodes);
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
   * Call $fn for each node.
   * @param callable $fn(array $node) : array Callback function. It takes %Tree $node and must return this $node.
   */
  function map(callable $fn)
  {
    foreach ($this->nodes as $i => $node) {
      $this->nodes[$i] = call_user_func($fn, $node);
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

    $flt = '';

    if ($topId) {
      $first = $this->db->select($this->table, ['ID' => $topId]);     
      $last = $this->db->select($this->table, "TREE_ID='$treeId' AND ID>'{ID}' AND LEVEL<='{LEVEL}' ORDER BY ID", $first);
      $flt .= $last? sprintf("AND ID BETWEEN %d AND %d", $first['ID'], $last['ID'] - 1) : "AND ID>".$first['ID'];
    }

    if ($maxLevel) {
      $flt .= ' AND LEVEL<='.$maxLevel;
    }

    $nodes = $this->db->selectAll($this->table, "TREE_ID={#0} AND ACTIVE=1 $flt ORDER BY ID", $treeId);
    $this->fromArray($nodes);
    $this->values['TREE_ID'] = $treeId;
  }

  /**
   * Save current %tree to table tree_lookups.
   * @param int $treeId 
   */
  function save($treeId)
  {
    $this->service('db');

    $this->db->delete($this->table, "TREE_ID={#0}", $treeId);

    foreach ($this->nodes as $node)
    {
      $data = [
        'TREE_ID' => $treeId,
        'LABEL' => $node['LABEL'],
        'LEVEL' => $node['LEVEL'],
        'ROUTE' => array_get($node, 'ROUTE'),
        'URL'   => array_get($node, 'URL'),
        'RKEY'  => array_get($node, 'RKEY'),
        'ACTIVE' => array_get($node, 'ACTIVE'),
      ];

      $this->db->insert($this->table, $data);
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

  protected function authFilter($node)
  {
    if (isset($node['RKEY']) and !$this->auth->hasRight($node['RKEY'])) {
      $node['ACTIVE'] = 0;
    }

    return $node;
  }

  /**
   * Return html output of the current tree.
  **/
  public function html()
  {
    $this->tpl->values = $this->values;
    $this->tpl->values['items'] = '__items__';
    $rootHtml = $this->tpl->html('root');

    $i = 0;
    $html = '';
    while($node = array_get($this->nodes, $i++))
    {
      if (!$node['ACTIVE']) {
        $i = $this->nextSibling($i - 1);

        $next = array_get($this->nodes, $i);
  
        if (!$next) {
          $next['LEVEL'] = 0;
        }
  
        if ($next['LEVEL'] < $node['LEVEL']) {
          $n = $node['LEVEL'] - $next['LEVEL'];
          $closeHtml = $this->getHtmlTplStrip($this->tpl, 'folderEnd', $node);
          $html .= str_repeat($closeHtml, $n);
        }

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
        $html .= $this->getHtmlTplStrip($this->tpl, 'folderBegin', $node);
      }
      else {
        $name = ($node['LEVEL'] == 0 and isset($this->tpl->elements['topitem']))? 'topitem' : 'item';
        $html .= $this->getHtmlTplStrip($this->tpl, $name, $node);
      }

      if ($next['LEVEL'] < $node['LEVEL']) {
        $n = $node['LEVEL'] - $next['LEVEL'];
        $closeHtml = $this->getHtmlTplStrip($this->tpl, 'folderEnd', $node);
        $html .= str_repeat($closeHtml, $n);
      }
    }

    return str_replace('__items__', $html, $rootHtml);
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
    while($node = $this->nodes[$i++] ?? null) {
      if ($node['LEVEL'] <= $start['LEVEL']) return --$i;
    }
    return $i;
  }

  private function getHtmlTplStrip($t, $strip, $node)
  {
    $t->values = $node;
    if ($strip == 'item') return $t->html('item');
    elseif ($strip == 'topitem') return $t->html('topitem');
    else {
      $t->values['items'] = '__items__';
      list($begin, $end) = explode('__items__',  $t->html('folder'));
      return ($strip == 'folderBegin')? $begin : $end;
    }
  }

}

 ?>