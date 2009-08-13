<?php
/* 
 * Response object
 */

/**
 * Response object for transforming response data and
 * sending back HTTP responses
 *
 * @author sam
 */
class Response {
    const BAD_REQUEST ="HTTP/1.0 400 Bad Request";
    public static function transform($response_data, array $response_options) {
        $transformed_response = null;
	switch ($response_options['format']) {
                case '':
                case 'json':
                    if(isset($response_options['json_callback'])&&!empty($response_options['json_callback'])) {
                        $transformed_response = "{$response_options['json_callback']}(".json_encode($response_data).")";
                    } else {
                        $transformed_response = json_encode($response_data);
                    }
                    break;
                case 'sphp':
                    $transformed_response = serialize($response_data);
                    break;
                case 'debug':
                    $transformed_response = '<pre class="debug">'.print_r($response_data,1).'</pre>';
                    break;
                case 'info':
                    // @TODO TBD delivers field info
                    break;
                default:
                    $this->logger->warn(__METHOD__.": response format unrecognized [{$response_options['format']}]");
                    break;
            }
	    return $transformed_response;
    }
    public static function send_client_error_exit(Request $request, $error_header, $message=null) {
        global $logger;
	$logger->error(__METHOD__. " Sending http error [{$error_header}] with message [$message]");
        header($error_header, true);
        echo(self::transform(array('_error_message' => $message), array(
                'format'=>$request->response_format,
                'json_callback'=>$request->json_callback
            )
        ));
	exit();
    }
}