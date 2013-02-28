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
	protected $sorter = null;
	
	function Title(){
		return _t("ProductFinder.TITLE","Products");
	}
	
	function Link($action = null){
		if($this->isInDB()){
			return parent::Link($action);
		}
		return Controller::join_links(self::$url_segment,$action);
	}
	
	function getSorter(){
		if($this->sorter){
			return $this->sorter;
		}
		$sorter =  new SortControl($this->class.$this->ID);
		$sorter->addSort("Relevance","Relevance", array(
			//Relevance sort is added additionally below
			"Popularity" => "DESC",
			"Created" => "DESC"
		));
		$sorter->addSort("Popularity","Most Popular", array(
			"Popularity" => "DESC",
			"Created" => "DESC"
		));
		$sorter->addSort("Alphabetical","Alphabetical", array(
			"Title" => "ASC",
			"Created" => "DESC"
		));
		$sorter->addSort("Newest","Newest", array(
			"Created" => "DESC"
		));
		$sorter->addSort("LowPrice","Lowest Price", array(
			"BasePrice" => "ASC"
		));
		$sorter->addSort("HighPrice","Highest Price", array(
			"BasePrice" => "DESC"
		));
		return $this->sorter = $sorter;
	}
	
	function Form(){
		$query = $this->request->getVar("search");
		$fields = new FieldSet(
			new TextField("search","",$query)
		);
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
		$phrase = Convert::raw2sql($phrase); //prevent sql injection
		$phrasewords = explode(" ",$phrase);
		$SQL_matchphrase = "".implode("* ",$phrasewords)."*";
		$query = singleton("Product")->extendedSQL(); //get base query (_Live)	
		$orderby = $this->getSorter()->getSortSQL();
		if(!empty($phrase) && $fields = $this->matchFields()){
			$scoresum = array();
			//give weight to match fields, based on order
			$maxstrength  = count($fields);
			foreach($fields as $weight => $field){
				$strength = (count($fields) - $weight);
				$query->select[] = "(MATCH($field) AGAINST ('$phrase' IN BOOLEAN MODE)) * $maxstrength AS \"Relevance{$weight}_exact\"";
				$query->select[] = "(MATCH($field) AGAINST ('$SQL_matchphrase' IN BOOLEAN MODE)) * $strength AS \"Relevance{$weight}\"";
				$scoresum[] = "\"Relevance{$weight}\" + \"Relevance{$weight}_exact\""; //exact match gets priority
			}
			/*
			//give weight to order of words in phrase
			foreach($phrasewords as $weight => $word){
				$query->select[] = "MATCH(".implode(",",$fields).") AGAINST ('+$word*' IN BOOLEAN MODE) AS \"Relevance{$weight}\"";
				$scoresum[] = "\"Relevance{$weight}\" * ".(1 + (count($fields) - $weight + 1) * 0.1);
			}*/
			$likes = array();
			foreach($fields as $field){
				foreach($phrasewords as $word){
					$likes[] = $field." LIKE '%$word%'";
				}
			}
			$query->where("(".implode(" OR ",$likes).")");
			if($this->getSorter()->getSortName() == "Relevance"){
				$orderby = implode(" + ",$scoresum)." DESC, ".$orderby;
			}
		}
		$query->orderby($orderby);
		
		$query->where("\"SiteTree_Live\".\"ShowInSearch\" = 1");
		$query->groupby("Product_Live.ID");
		return $query;
	}
	
	protected function matchFields(){
		return array(
			"SiteTree_Live.Title"	
		);
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
	
	function SortForm(){
		$fields = new FieldSet(
			new DropdownField("sort","",$this->getSorter()->getSortOptions(),$this->getSorter()->getSortName())
		);
		$actions = new FieldSet(
			$setsort = new FormAction("setSort","Update Sort")
		);
		$setsort->addExtraClass("btn btn-primary");
		return new Form($this,"SortForm",$fields,$actions);
	}
	
	function setSort($data, $form){
		$this->getSorter()->setSort($data['sort']);
		$this->redirectBack();
	}
	
}