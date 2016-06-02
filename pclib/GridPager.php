<?php

namespace pclib;
use pclib;

class GridPager extends system\BaseObject
{
	protected $page = 1;
	protected $maxPage = 1;
	protected $length = 0;
	protected $pageLen = 20;

	public $linkNumber = 10;
	public $baseUrl = '/?';
	public $pattern = '%s | %s | %s';
	public $patternItem = '<span class="%s">%s</span>';

	/** var Translator */
	public $translator;

	function __construct($length, $baseUrl)
	{
		parent::__construct();
		$this->setLength($length);
		$this->baseUrl = $baseUrl;
		$this->service('translator', false);
	}

	protected function clamp($num, $min, $max)
	{
		if ($max < $min) $max = $min;
		if ($num < $min) $num = $min;
		if ($num > $max) $num = $max;
		return (int)$num;
	}

	function setPage($page)
	{
		if ($page === 'all') {
			$this->setPageLen($this->length);
			$this->page = 1;
			return;
		}

		$this->page = $this->clamp($page, 1, $this->maxPage);
	}

	function setLength($length)
	{
		$this->maxPage = ceil($length / $this->pageLen);

		if ($this->page > $this->maxPage) {
			$this->setPage(1);
		}

		$this->length = $length;
	}

	function setPageLen($pageLen)
	{
		$this->pageLen = ($pageLen > 0)? $pageLen : 20;
		$this->maxPage = ceil($this->length / $this->pageLen);
		if ($this->page > $this->maxPage) {
			$this->setPage($this->maxPage);
		}
	}

	function getValue($id)
	{
		if (is_numeric($id)) return $id;

		switch ($id) {
				case "first":  return 1;
				case "last":   
				case "maxpage": return $this->maxPage;
				case "next":   return $this->clamp($this->page+1, 1, $this->maxPage);
				case "prev":
				case "previous": return $this->clamp($this->page-1, 1, $this->maxPage);
				case "pglen": return $this->pageLen;
				case "total": return $this->length;
				case "all": return 'all';
				case "active": 
				case "page": return $this->page;
		}

		throw new Exception("Unknown pager value '%s'", [$id]);
	}

	function getUrl($page)
	{
		return $this->baseUrl."page=$page";
	}

	function getHtml($id, $cssClass = 'page-item')
	{
		$plainValues = ['maxpage','pglen','total','page','active'];

		if ($id == 'pages') return $this->getPagesHtml();
		if (in_array($id, $plainValues)) {
			$val = $this->getValue($id);
		}
		else {
			$url = $this->getUrl($this->getValue($id));
			$lb = $this->t(ucfirst($id));
			$val = "<a href=\"$url\">$lb</a>";
		}
		
		return sprintf($this->patternItem, $cssClass, $val);
	}

	protected function getPagesHtml()
	{
		$pages = [];

		foreach ($this->pagerRange($this->page, $this->linkNumber) as $page) {
			if ($this->page == $page) {
				$pages[] = $this->getHtml($page, 'page-item active');
			}
			else {
				$pages[] = $this->getHtml($page);
			}
		}

		return implode(' ', $pages);
	}

	function t($s) { 
		 return $this->translator? $this->translator->translate($s) : $s;
	}

	function html()
	{
		return sprintf($this->pattern, 
			$this->getHtml('first'),
			$this->getHtml('last'),
			$this->getHtml('pages')
		);
	}

	protected function pagerRange($page, $size)
	{
		if ($this->maxPage <= 0) return [];

		$middle = floor($size / 2);

		if ($this->maxPage > $size) {
			$begin = ($page > $middle)? ($page - $middle + 1) : 1;
			
			if ($this->maxPage - $page <= $middle) {
				$begin = $this->maxPage - $size + 1;
			}

			$end = $begin + $size - 1;

			return range($begin, $end);
		}
		else return range(1, $this->maxPage);
	}

}

?>