<?php

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
			//Subshop "\"Title\" LIKE '%$phrase%'",
			//Artist?
			//Tag
		);
	
		//sort by popularity, newer
		//join with stores and tags
		//closer matches are better eg 'sam' vs 'samoa'
		//search module
			//fuzzy search
			//mispellings
		$page = ((int)$this->request->getVar('page')) ? (int)$this->request->getVar('page') : 1;
		$limit = 16;
		
		$start = $limit + 1;
		$paging = ($page-1)*$limit.",".$start;
		
		$filter = implode(" AND ",$filters);
		
		$joins = array(
			"INNER JOIN \"SiteTree\" ON \"Product\".\"ID\" = \"SiteTree\".\"ID\"",
			//"LEFT JOIN \"SubShop\" ON \"Product\".\"SubShopID\" = \"SubShop\".\"ID\"",
			//"LEFT JOIN \"Product_Tags\" ON \"Product\".\"ID\" = \"Product_Tags\".\"ProductID\"",
			//"LEFT JOIN \"Tag\" ON \"Product_Tags\".\"TagID\" = \"Tag\".\"ID\""
		);
		
		$sort = "\"NumberSold\" ASC,\"Created\" DESC";
		
		//query
		/*
		$query = new SQLQuery();
		$query->select = array(
			'"Product".*',
			//'"Tag"."Title" AS "TagTitle"',
			//'"SubShop"."Title" AS "SubShopTitle"'
		);
		$query->from = array_merge(array('Product'),$joins);
		$query->where = $filters;
		$results = singleton('Product')->buildDataObjectSet($query->execute());
		*/
		
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