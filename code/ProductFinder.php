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
class ProductFinder extends Page_Controller
{
    
    public static $url_segment = "products";
    
    public function Title()
    {
        return _t("ProductFinder.TITLE", "Products");
    }
    
    public function Link($action = null)
    {
        if ($this->isInDB()) {
            return parent::Link($action);
        }
        return Controller::join_links(self::$url_segment, $action);
    }

    /**
     * Allow this controller to be viewed when the site is in draft mode.
     */
    public function init()
    {
        $draftsecurity = Session::get('unsecuredDraftSite');
        Session::set("unsecuredDraftSite", true);
        parent::init();
        Session::set("unsecuredDraftSite", $draftsecurity);
    }
    
    public function getSorter()
    {
        $sorts = array(
            'Most Popular' => 'Popularity DESC',
            ListSorter_Option::create('Title (A - Z)', "Title ASC",
                ListSorter_Option::create('Title (Z - A)', "Title DESC")
                    ->setID("title desc")
            )->setID("title asc"),
            ListSorter_Option::create('Newest', "Created DESC",
                ListSorter_Option::create('Oldest', "Created ASC")
            )
        );
        $sorter = new ListSorter($this->request, $sorts);
        $this->extend('updateSorter', $sorter);
        return $sorter;
    }
    
    public function Form()
    {
        $query = $this->request->getVar("search");
        $fields = new FieldList(
            new TextField("search", "", $query)
        );
        $actions = new FieldList($searchaction = new FormAction("index", "Search"));
        $searchaction->setFullAction(null);
        $form = new Form($this, "SearchForm", $fields, $actions);
        $form->setFormAction($this->Link());
        $form->setFormMethod("GET");
        $form->disableSecurityToken();
        return $form;
    }
    
    public function index()
    {
        $phrase = $this->request->getVar('search');
        return array(
            'Phrase' => $phrase,
            'Products' => $this->results($phrase)
        );
    }
    
    protected function results($phrase = null)
    {
        $products = new DataList("Product");
        $products = $products->setDataQuery($this->query($phrase))
                        ->sort("Popularity", "DESC");
        $products = $this->getSorter()->sortList($products);
        $products = new PaginatedList($products, $this->request);
        $products->setPageLength(16);

        return $products;
    }
    
    protected function query($phrase)
    {
        $phrase = Convert::raw2sql($phrase); //prevent sql injection
        $query = Product::get()
            ->filter("ShowInSearch", true)
            ->dataQuery();
        if (!empty($phrase) && $fields = $this->matchFields()) {
            $scoresum = array();
            $phrasewords = explode(" ", $phrase);
            $maxstrength  = count($fields);
            $SQL_matchphrase = "".implode("* ", $phrasewords)."*";
            foreach ($fields as $weight => $field) {
                //TODO: get this working
                $strength = (count($fields) - $weight);
                $query->select[] = "(MATCH($field) AGAINST ('$phrase' IN BOOLEAN MODE)) * $maxstrength AS \"Relevance{$weight}_exact\"";
                $query->select[] = "(MATCH($field) AGAINST ('$SQL_matchphrase' IN BOOLEAN MODE)) * $strength AS \"Relevance{$weight}\"";
                $scoresum[] = "\"Relevance{$weight}\" + \"Relevance{$weight}_exact\""; //exact match gets priority
            }
            $likes = array();
            foreach ($fields as $field) {
                foreach ($phrasewords as $word) {
                    $likes[] = $field." LIKE '%$word%'";
                }
            }
            $query->where("(".implode(" OR ", $likes).")");
        }

        return $query;
    }

    protected function matchFields()
    {
        $stage = Versioned::current_stage() === "Stage" ? "" : "_Live";
        return array(
            "SiteTree{$stage}.Title"
        );
    }
    
    protected function phraseFilters($phrase)
    {
        $phrase = Convert::raw2sql(strtolower(trim($phrase)));
        if (empty($phrase)) {
            return null;
        }
        $stage = Versioned::current_stage() === "Stage" ? "" : "_Live";
        return array(
            "LOWER(\"SiteTree{$stage}\".\"Title\") LIKE '%$phrase%'"
        );
    }
}
