<?php
class interest extends Domain {
    
    public function  __construct() {
        parent::__construct();
        $this->config = array(
            'filters' => array(
                'name'  => array('filter'=>FILTER_SANITIZE_STRING),
                'file_name' => array('filter'=>FILTER_SANITIZE_STRING),
                'url_slug'   => array('filter'=>FILTER_SANITIZE_STRING),
		'file_type'   => array('filter'=>FILTER_SANITIZE_STRING),
		'file_size'   => array('filter'=>FILTER_VALIDATE_INT)
            ),
            'input_sources' => array(
                'get' => INPUT_GET,
                'add' => INPUT_POST,
                'edit' => INPUT_GET,
                'delete' => INPUT_GET
            ),
	    'relations' => array(
		'has_many' => 'marker'
	    )
        );
    }
    
    public function before_save() {
	$this->set_data('url_slug',preg_replace('/\W/', '_',$this->data('name')));
	return true;
    }
    /**
     * Store the uploaded file then run Domain::process_action()
     * @return string
     */
    public function process_action(Request $request) {
        $response = null;
	if(in_array($request->requested_action,array('add','edit')) && $_FILES && $this->store_file_upload($request)) {
	    // set the is_templated_response to true
	    return parent::process_action($request, true);
	}
        return parent::process_action($request);
    }
    /**
     *
     * @param array $process_request_result
     * @return string The contructed response
     */
    public function build_templated_response($process_request_result) {
	$this->logger->debug(__METHOD__." \$process_request_result:".print_r($process_request_result,1));
	$return_uri_template = isset($process_request_result['meta']['return_uri'])?$process_request_result['meta']['return_uri']:'';
	$interest_id = isset($process_request_result[0]['id'])?urlencode($process_request_result[0]['id']):'';
	$return_uri = str_replace('id=?', "id={$interest_id}", $return_uri_template);
	$this->logger->debug(__METHOD__." \$return_uri:".print_r($return_uri,1));
	ob_start();
	include "domains/templates/file_upload_response.php";
	return ob_get_clean();
    }
    /**
     * extra is added to the data result array of any get calls for a domain
     * @param array $result_data
     * @return array This should be the original $result_data with any mutations
     */
    public static function extra($result_data) {
        $result_data['image_uri'] = BASE_URL."/uploads/{$result_data['file_name']}";
        return $result_data;
    }
}