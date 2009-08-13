<?php
/**
 * $this->domain_request->requested_action
 * $this->domain_request->response_format
 * $this->domain_request->meta_params
 * $this->domain_request->json_callback
 * $this->domain_request->_get
 * $this->domain_request->_post
 *
 *
 */
class Domain {

    const FILE_UPLOAD_DIR = '/uploads';

    private $name;
    private $id;
    private $ext;
    private $sibling;
    private $parent;

    protected $logger;
    private $domain_model = null;
    private $domain_request;
    private $base_dir;
    private $data = array('get' => null, 'add' => null, 'edit' => null, 'delete' => null);
    private $calculated_data = array('get'=>null,'add'=>null,'edit'=>null,'delete'=>null);
    private $callable_actions = array('get','add','edit','delete');
    // define the attributes of this domain and associated filters
    protected $config = array(
        'filters' => array(),
	// at this point we allow inputs from get or post for any rest request
        'input_sources' => array(
            'get' => INPUT_GET,
            'add' => INPUT_GET,
            'edit' => INPUT_GET,
            'delete' => INPUT_GET
        ),
	'relations' => array(
	    'has_many' => array(),
	    'has_one' => array()
	)
    );

    public function  __construct() {
	$this->name = get_class($this);
	global $logger;
        $this->logger = $logger;
    }
    /**
     * hook that domain classes can implement. They can mutate data and/or hault
     * persistance by returning false if neccessary.  $this->before_save() is called in
     * $this->process_action() jsut before persisting data.
     * @return boolean
     */
    public function before_save(){return true;}

    /**
     *
     * @param array $is_templated_response Defines the parameters of a templated response.
     * With a templated response, the raw response data is applied to a template defined in
     * the implmentation domain class
     * Currently used for file uploads.
     * 
     * @return string the contructed response
     */
    public function process_action(Request $request, $is_templated_response=false) {
	$this->domain_request = $request;
	if( ! in_array($this->domain_request->requested_action, $this->callable_actions)){
            Response::send_client_error_exit($this->domain_request, Response::BAD_REQUEST, "Invalid action requested [{$this->domain_request->requested_action}]");
	}
	$this->domain_model = new Persist($this->domain_request, $this->config);
        $this->set_data_for_action();
        $this->logger->debug('data: '.print_r($this->data,1));
	if( ! $this->before_save()) {
	    $this->logger->error(__METHOD__.' $this->before_save returned non-true restult');
            Response::send_client_error_exit($this->domain_request, Response::BAD_REQUEST, "");
	}
	$this->logger->debug('data after before_save() hook'. print_r($this->data,1));
	// call the this->get|add|edit|delete method
        $result = $this->{$this->domain_request->requested_action}();
	$this->logger->debug(__METHOD__.' $this->domain_request->response_format = ['.$this->domain_request->response_format.'] $result = '. print_r($result,1));
	if($is_templated_response) {
	    // jsut give the templated response the raw data
	    $result['meta']=$this->domain_request->meta_params;
	    $result = $this->build_templated_response($result);
	} else {
	    $result = Response::transform($result, array(
                    'format'=>$this->domain_request->response_format,
                    'json_callback'=>$this->domain_request->json_callback
                )
            );
	    $this->logger->debug(__METHOD__.' $result after processing $this->domain_request->response_format = ['.$this->domain_request->response_format.'] $result = '. print_r($result,1));
	}
	return $result;
    }
    /**
     * In addition to retriving the get data, we allow every domain involved (ant that
     * appeared in the request) to fire their after_get hooks and possible mutate the
     * result data.
     * @return array The data that results from the get query.
     */
    public function get() {
	return $this->domain_model->get($this->data['get'],$this->domain_request->domains);
    }
    public function add() {
        return $this->domain_model->add($this->data['add'],$this->domain_request->domains,true);
    }
    public function edit() {
        return $this->domain_model->edit($this->data['edit'],$this->domain_request->domains,true);
    }
    public function delete() {
        return $this->domain_model->delete();
    }
    /**
     * Returns the related domains to this domain for the given relation
     * @param string $relation_type The type of relation ['belongs_to'|'has_many'|'has_one']
     * @return array of Domain
     */
    public function relation($relation_type) {
        $relations = isset($this->config['relations'][$relation_type])?$this->config['relations'][$relation_type]:array();
        return is_array($relations)?$relations:array($relations);
    }
    /**
     * for the given input method defined for the given action,
     * collect and set the filtered data.
     */
    public function set_data_for_action() {
        $data_pool = array();
        $source_for_action = $this->config['input_sources'][$this->domain_request->requested_action];
        $calcd_data = $this->calculated_data[$this->domain_request->requested_action]
	    ? $this->calculated_data[$this->domain_request->requested_action]
	    : array();
	$this->logger->debug(__METHOD__." \$calcd_data: [".print_r($calcd_data,1)."]");
	// only keys in the data source to only those that appear in the filters
        if($source_for_action==INPUT_GET && isset($this->domain_request->_get[$this->name])) {
	    $data_pool = array_merge($this->domain_request->_get[$this->name],$calcd_data);
	} else if($source_for_action==INPUT_POST && isset($this->domain_request->_post[$this->name])) {
            $data_pool = array_merge($this->domain_request->_post[$this->name],$calcd_data);
        } else {
            $this->logger->warn(__METHOD__." Datapool for action [{$this->domain_request->requested_action}] does not exist");
        }
	$this->data[$this->domain_request->requested_action] = $data_pool;
	$this->logger->debug(__METHOD__." \$this->data: [".print_r($this->data,1)."]");
	// make sure we leave with atleast an empty array
        $this->data[$this->domain_request->requested_action] = $this->data[$this->domain_request->requested_action]==null?array():$this->data[$this->domain_request->requested_action];
    }

    /**
     * if upload is a success $this->data[$this->domain_request->requested_action]['file_name'] is set
     * i.e. $this->data['$this->domain_request->requested_action'post']['file_name'] = 'foo.png';
     *
     * <NOTE> DO NOT SUPPORT MULTIFILE UPLOADS YET
     *
     * $_FILE(
	    [interest] => Array(
		    [name] => Array(
			    [item] => Picture 8.png
			)
		    [type] => Array(
			    [item] => image/png
			)
		    [tmp_name] => Array(
			    [item] => /private/var/tmp/phpOtmKob
			)
		    [error] => Array(
			    [item] => 0
			)
		    [size] => Array(
			    [item] => 35356
			)
		)
	)
     * $this->calculated_data is populated here and then merged with $this->data in
     * process_action().
     * @return boolean success of the file upload
     */
    public function store_file_upload() {
	$file_name_parts = pathinfo(basename(current($_FILES[$this->name]['name'])));
	$this->logger->debug(__METHOD__. " \$file_name_parts : ".print_r($file_name_parts,1));
	$file_name = preg_replace('/\W/', '_',$file_name_parts['filename']).".{$file_name_parts['extension']}";
	$upload_file = $this->base_dir.self::FILE_UPLOAD_DIR.'/'.$file_name;
	if (move_uploaded_file(current($_FILES[$this->name]['tmp_name']), $upload_file)) {
	    $this->logger->debug(__METHOD__. " File successfully uploaded to [{$upload_file}]: ".print_r($_FILES,1));
	    $this->calculated_data[$this->domain_request->requested_action]['file_name'] = $file_name;
	    $this->calculated_data[$this->domain_request->requested_action]['file_type'] = current($_FILES[$this->name]['type']);
	    $this->calculated_data[$this->domain_request->requested_action]['file_size'] = current($_FILES[$this->name]['size']);
	} else {
	    $this->logger->error(__METHOD__. " File not uploaded to [{$upload_file}] ".print_r($_FILES,1));
	    $upload_file = null;
	}
	return $upload_file!==null;
    }
    /**
     * Allows domain classes to inspect data before persistance
     * @param string $data_element_name
     * @return mixed
     */
    protected function data($data_element_name) {
	return isset($this->data[$this->domain_request->requested_action][$data_element_name]) ? $this->data[$this->domain_request->requested_action][$data_element_name] :null;
    }
    /**
     * Allows domain classes to mutate data before persistance
     * @param string $data_element_name
     * @param mixed $data_element_value
     */
    protected function set_data($data_element_name, $data_element_value) {
	$this->data[$this->domain_request->requested_action][$data_element_name] = $data_element_value;
    }
    /**
     * Used by request->valid() to determine if request is valid
     * @param Domain $proposed_sibling
     * @return boolean
     */
    public function valid_parent_of(Domain $proposed_sibling) {
	return in_array($this->relation_to($proposed_sibling->name), array('has_many','has_one'));
    }
    /**
     * Used by request->valid() to determine if request is valid
     * @param Domain $proposed_parent
     * @return boolean
     */
    public function valid_sibling_of(Domain $proposed_parent) {
	return in_array($this->relation_to($proposed_parent->name), array('belongs_to'));
    }
    /**
     * returns this domain models relation to the given domain name
     * @param string $domain_name
     * @return string The type of relation ['belongs_to'|'has_many'|'has_one']
     */
    private function relation_to($domain_name) {
	$relation = null;
	if(isset($this->config['relations'])&&is_array($this->config['relations'])) {
	    foreach ($this->config['relations'] as $relation_type => $relations) {
		$relations = is_array($relations)?$relations:array($relations);
		if(in_array($domain_name, $relations)) {
		    $relation = $relation_type;
		    break;
		}
	    }
	}
	return $relation;
    }
    /**
     * magic method
     * @see http://us2.php.net/manual/en/language.oop5.magic.php
     * @return string
     */
    public function  __toString() {
        return "{$this->name}";
    }
    /**
     * magic method
     * @see http://us2.php.net/manual/en/language.oop5.magic.php
     * @param string $attribute_name
     * @return mixed
     */
    public function  __get($attribute_name) {
	return isset($this->$attribute_name)?$this->$attribute_name:null;
    }
    /**
     * magic method
     * @see http://us2.php.net/manual/en/language.oop5.magic.php
     * @param string $attribute_name
     * @param mixed $value
     */
    public function  __set($attribute_name, $value) {
        $settables = array('id','ext','sibling','parent');
        if(in_array($attribute_name,$settables)) {
            if($attribute_name=='id'){
                $this->id = (int)$value?(int)$value:null;
            } else {
                $this->$name = $value;
            }
        } else {
            $this->logger->error(__METHOD__. "attempting to set unsettable [$attribute_name] to value [$value]");
        }
    }
}