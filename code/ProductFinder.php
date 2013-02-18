<?php
/**
 * ProductFinder is a stand-alone page for searching and filtering products,
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
class ProductFinder extends Page_Controller{
	
	static $url_segment = "products";
	
	function Title(){
		return _t("ProductFinder.TITLE","Products");
	}
	
	function Link($action = null){
		if($this->isInDB()){
			return parent::Link($action);
		}
		return Controller::join_links(self::$url_segment,$action);
	}
	
	function Form(){
		$query = $this->request->getVar("search");
		$fields = new FieldSet(new TextField("search","",$query));
		$actions = new FieldSet($searchaction = new FormAction("index","Search"));
		$searchaction->setFullAction(null);
		$form = new Form($this,"SearchForm",$fields,$actions);
		$form->setFormAction($this->Link());
		$form->setFormMethod("GET");
		$form->disableSecurityToken();
		return $form;
	}
	
	function index(){
		$phrase = $this->request->getVar('search');
		$start = (int)$this->request->getVar('start');
		return array(
			'Phrase' => $phrase,
			'Products' => $this->results($phrase, $start)
		);
	}
	
	protected function results($phrase = null, $start = 0){
		$length = 16;
		$query = $this->query($phrase);
		$count = $query->unlimitedRowCount();
		if(!$count){ //don't bother building result set, if there are none
			return null;
		}
		$query->limit("$start, $length");
		$products = $this->queryToSet($query);
		$products->Start = $start + 1;
		$products->TotalSize = $count;
		$products->setPageLimits($start, $length, $count);
		return $products;
	}
	
	protected function queryToSet($query){		
		$results = $query->execute();
		$set = singleton("Product")->buildDataObjectSet($results);
		return $set;
	}
	
	protected function query($phrase){
		$query = singleton("Product")->extendedSQL(); //get base query
		//TODO: get from live only
		//TODO: join with categories
		if($philters = $this->phraseFilters($phrase)){
			$query->where("(".implode(" OR ",$philters).")");
		}
		$query->where("\"SiteTree_Live\".\"ShowInSearch\" = 1");
		$query->orderby("\"NumberSold\" ASC, \"SiteTree_Live\".\"Created\" DESC");
		return $query;
	}
	
	protected function phraseFilters($phrase){
		$phrase = Convert::raw2sql(strtolower(trim($phrase)));
		if(empty($phrase)){
			return null;
		}
		return array(
			"LOWER(\"SiteTree_Live\".\"Title\") LIKE '%$phrase%'"
		);
	}
	
}