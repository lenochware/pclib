<?php
/**
 * @file
 * Grid pagination.
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
 * Provides paginator calculations and rendering of the grid pager.
 */
class GridPager extends system\BaseObject
{
	protected $page = 1;
	protected $maxPage = 1;
	protected $length = 0;
	protected $pageLen = 20;

	/** Number of page-links shown in pager. */
	public $linkNumber = 10;

	/** Base url for pager links. */
	public $baseUrl = '/?';

	/** Pattern for rendering. "first | last | pages" by default. */
	public $pattern = '%s | %s | %s';

	/** Pattern for rendering of the pager item. */
	public $patternItem = '<span class="%s">%s</span>';

	/** var Translator */
	public $translator;

	/**
	 * Create pager.
	 * @param int $length Total number of rows.
	 * @param string $baseUrl
	 */
	function __construct($length, $baseUrl)
	{
		parent::__construct();
		$this->setLength($length);
		$this->baseUrl = $baseUrl;
		$this->service('translator', false);
	}

	/**
	 * Shift $num value into interval \<$min, $max\>.
	 * @return int $num Value in specified interval.
	 */
	protected function clamp($num, $min, $max)
	{
		if ($max < $min) $max = $min;
		if ($num < $min) $num = $min;
		if ($num > $max) $num = $max;
		return (int)$num;
	}

	/**
	 * Set active (selected) page.
	 * @param int $page
	 */
	function setPage($page)
	{
		if ($page === 'all') {
			$this->setPageLen($this->length);
			$this->page = 1;
			return;
		}

		$this->page = $this->clamp($page, 1, $this->maxPage);
	}

	/**
	 * Set total number of rows.
	 * @param int $length
	 */
	function setLength($length)
	{
		$this->maxPage = ceil($length / $this->pageLen);

		if ($this->page > $this->maxPage) {
			$this->setPage(1);
		}

		$this->length = $length;
	}

	/**
	 * Set number of rows of the page.
	 * @param int $pageLen
	 */
	function setPageLen($pageLen)
	{
		$this->pageLen = ($pageLen > 0)? $pageLen : 20;
		$this->maxPage = ceil($this->length / $this->pageLen);
		if ($this->page > $this->maxPage) {
			$this->setPage($this->maxPage);
		}
	}

	/**
	 * Return value of the pager item.
	 * @param string $id Id of pager item
	 */
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

		throw new Exception("Unknown pager value '%s'", array($id));
	}

	/**
	 * Return pager url for the $page.
	 * @param int $page
	 * @return string $url
	 */
	function getUrl($page)
	{
		return $this->baseUrl."page=$page";
	}

	/**
	 * Return HTML for the pager link or other item.
	 * @param string $id Id of pager item
	 * @param string $cssClass
	 * @return string $html
	 */
	function getHtml($id, $cssClass = 'page-item')
	{
		$plainValues = array('maxpage','pglen','total','page','active');

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

	/**
	 * Return links to all pages.
	 * @return string $html
	 */
	protected function getPagesHtml()
	{
		$pages = array();

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

	protected function t($s) { 
		 return $this->translator? $this->translator->translate($s) : $s;
	}

	/**
	 * Return pager html.
	 * @return string $html
	 */
	function html()
	{
		return sprintf($this->pattern, 
			$this->getHtml('first'),
			$this->getHtml('last'),
			$this->getHtml('pages')
		);
	}

	/**
	 * Return array of page numbers arround active page.
	 * @param int $page Active page
	 * @param int $size Size of returned array
	 * @return array Page numbers
	 */
	protected function pagerRange($page, $size)
	{
		if ($this->maxPage <= 0) return array();

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