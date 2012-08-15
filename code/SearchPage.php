<?php
/**
 * SearchPage_Controller is a stand-alone page for searching products,
 * reachable by default at yoursite.com/search.
 * 
 * Pass a 'search' get parameter in the url to perform the search.
 * 
 * TODO:
 * 	sort results by popularity, newest
 * 	choose number of results
 * 	join with other dataobjects for fuller search
 * 	closer matches are better eg 'sam' vs 'samoa'
 * 	search module
 * 		fuzzy search
 * 		mispelling
 * 
 */
class SearchPage_Controller extends Page_Controller{
	
	static $url_segment = "search";
	
	function Link($action = null){
		if($this->isInDB()){
			return parent::Link($action);
		}
		return Controller::join_links(self::$url_segment,$action);
	}
	
	function index(){
		$phrase = null;
		if(!$this->request->getVar('search')){
			return array();
		}
		$phrase = Convert::raw2sql($this->request->getVar('search'));
		$filters = array(
			"\"Title\" LIKE '%$phrase%'",
			"\"ShowInSearch\" = 1"
		);
		$page = ((int)$this->request->getVar('page')) ? (int)$this->request->getVar('page') : 1;
		$limit = 16;
		$start = $limit + 1;
		$paging = ($page-1)*$limit.",".$start;
		$filter = implode(" AND ",$filters);
		$joins = array(
			"INNER JOIN \"SiteTree\" ON \"Product\".\"ID\" = \"SiteTree\".\"ID\""
		);
		$sort = "\"NumberSold\" ASC,\"Created\" DESC";
		$results = DataObject::get("Product",$filter,$sort,"",$paging);
		$link = $this->Link()."?search=".urlencode($phrase);
		$morelink = $previouslink = null;
		if($page > 1){
			$previouslink = $link."&page=".($page-1);
		}
		if($results && $results->Count() > $limit){
			$morelink = $link."&page=".($page+1);
			$results->pop();
		}
		return array(
			'Phrase' => Convert::raw2xml($phrase),
			'Results' => $results,
			'PrevLink' => $previouslink,
			'MoreLink' => $morelink
		);
	}
	
}