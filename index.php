<?php
/**
 * http://code.google.com/p/rcrd/
 */
ini_set('include_path', dirname(__FILE__).'/lib'.PATH_SEPARATOR.ini_get('include_path'));
require "core/util/Logger.php";
require "core/Request.php";
require "core/Response.php";
require "core/Domain.php";
$logger = new Logger(Logger::DEBUG,'./log/app.log');
$domain_request = new Request(dirname(__FILE__));
//Response::send_client_error_exit($domain_request, Response::BAD_REQUEST,"Testing send client error exit");
$logger->debug('Seeing $domain_request:'.print_r($domain_request,1));
require "core/Persist.php";
$result = $domain_request->process();
if($result) {
    echo $result;
} else {
   echo("Welcome to swapp http://code.google.com/p/swapp/");
}

