<?php
/*
 * request URL examples
 *
 * Add a new interest	/interest					?_method=add
 * Show a interest	/interest/{interest_id}				?_method=get
 * Update a interest	/interest/{interest_id}				?_method=edit
 * List all markers	/interest/{interest_id}/marker			?_method=get
 * Add a new marker	/interest/{interest_id}/marker			?_method=add
 * Show a marker	/interest/{interest_id}/marker/{marker_id}	?_method=get
 * Update a marker	/interest/{interest_id}/marker/{marker_id} 	?_method=edit
 * Delete a marker	/interest/{interest_id}/marker/{marker_id} 	?_method=delete
 *
 * Rules for the URL Parsing
 * - Words are expected to be domains (and should be nouns)
 * - Integers are expected to be primary keys for the domain that is to thier immediate
 *   left in the URL string.
 *	- so if specifying an existing Domain, this pattern is required:
 *		{domain_name}/{domain_id}
 *
 * - The third column above: (i.e. ?_method=add) is the required method parameter
 *   for the requested action.  We are not requiring http post, get, put, delete as
 *   we are written for html5 js clients at this point an cross domain rules prohibit
 *   this.  So at this point we us this facade of ?_method=add|get|edit|delete but
 *   the Domain code is written in a way that it can swith to required
 *   http get/post/put/delete with minimal effort
 *
 * - Description of URL
 *
 *	ex: /account/{account_id}/interest/{interest_id}/marker/{marker_id}
 *
 *	- Context Domains:	account, interest
 *	- Target Domain:	marker
 * - Parsing Pseudo code
 *	- for url: /account/1/interst/42/marker.json?_method=get
 *	- human: get all markers of interest 42 belonging to account 1
 *	- Resultant parsed structure:
 *	    array(
 *		target_domain => 'marker'
 *		context_domains => array(
 *		    0 => array('name'=>'account', 'id' => 1, 'ext' => null),
 *		    1 => array('name'=>'interest','id' => 42, 'ext' => null),
 *		    2 => array('name'=>'marker','id' => null, 'ext' => 'json')
 *		)
 *	    )
 *
 */
class Request {

    const FILE_UPLOAD_DIR = '/uploads';

    private $logger;
    private $domains;
    // shortcut names to domains[0];
    private $target_domain;
    private $target_domain_name;
    
    private $requested_action;
    private $response_format;
    private $json_callback;
    private $meta_params;
    private $_get;
    private $_post;
    private $base_dir;
    /**
     *
     * We pass GET and POST as params to ease testability of Request and classes
     * that use Request
     * 
     * @global Logger $logger
     * @param array $submitted_GET
     * @param array $submitted_POST
     */
    public function  __construct($base_dir) {
	global $logger;
	$this->logger = $logger;
	$this->_get = $_GET;
	$this->_post = $_POST;
	$this->base_dir = $base_dir;
        $this->logger->debug('Seeing $this->_get[]:'.print_r($this->_get,1));
        $this->logger->debug('Seeing $this->_post[]:'.print_r($this->_post,1));
	if(! $this->parse_request_url('/'.$this->_get[';c;']) ) {
            Response::send_client_error_exit($this, Response::BAD_REQUEST, "Unable to parse request URL [{$this->_get[';c;']}]");
	}
	$this->logger->debug('Seeing $request_domains:'.print_r($this->domains,1));
	$this->requested_action = isset($this->_get['_method'])?$this->_get['_method']:null;
	$this->json_callback = isset($this->_get['jsoncallback'])?$this->_get['jsoncallback']:null;
	$this->response_format = !empty($this->domains[0]->ext)?$this->domains[0]->ext:'json';
	if(isset ($this->_get['meta'])) {
	    $this->meta_params = $this->_get['meta'];
	} else if(isset ($this->_post['meta'])) {
	    $this->meta_params = $this->_post['meta'];
	} else {
	    $this->meta_params = array();
	}
    }
    /**
     * make attributes publicly gettable
     * @param string $name
     * @return mixed
     */
    public function  __get($name) {
	return $this->{$name};
    }

    /**
     * Parse out the {domain}/{id}.{ext} sequences in the given domain
     * @param string $url
     * i.e. http://example.com{$url}?param1=x&param...
     */
    private function parse_request_url($url) {
	$request_domains = $url_parts = array();
	preg_match_all('%/([\w-]+)/?(\d+)?(\.[\w-]+)?%',$url,$url_parts);
	$parent_domain = null;
	for($i=0;$i<count($url_parts[0]);$i++) {
	    $domain_name = $url_parts[1][$i];
            include_once "domains/{$domain_name}.php";
	    $domain = new $domain_name;
	    if($domain) { // able to instanciate domain class so add it to the chain
		$domain->id = $url_parts[2][$i];
		$domain->ext = ltrim($url_parts[3][$i],' .');
		$domain->parent = $parent_domain?$parent_domain->name:null;
		if($parent_domain!==null) { // if not on the first domain
		    $this->domains[$i-1]->sibling = $domain_name;
		}
		$this->domains[$i] = $parent_domain = $domain;
	    }
	}
	if(count($this->domains)) {
	    $this->domains = array_reverse($this->domains);
	    $this->target_domain = $this->domains[0];
	    $this->target_domain_name = $this->domains[0]->name;
	}
	return $this->valid();
    }

    public function valid() {
	$valid = true;
	$current = current($this->domains);
	while($next = next($this->domains)) {
	    if( ! $current->valid_sibling_of($next) || ! $next->valid_parent_of($current)) {
		$this->logger->warn(__METHOD__." Invalid relation: Parent[{$next}] to Sibling[{$current}]");
		$valid = false;
		break;
	    }
	}
	return $valid;;
    }
    public function process() {
	// init the target domain
        
	return $this->domains[0]->process_action($this);
    }
}