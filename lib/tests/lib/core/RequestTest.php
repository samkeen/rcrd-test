<?php
ini_set("include_path", "../core".PATH_SEPARATOR."../../../core".PATH_SEPARATOR.ini_get("include_path"));
require_once 'PHPUnit/Framework.php';

require_once "util/Logger.php";
require_once 'Request.php';
$logger = new Logger(Logger::DEBUG);
/**
 * Test class for Request.
 * Generated by PHPUnit on 2009-07-29 at 21:19:15.
 */
class RequestTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    Request
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        
	$this->object = new Request(array(),array());
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }

    public function testParse_request_url__null() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url(null);
        $this->assertTrue($result==array(),
	    '$result did not match:'.print_r($result,1)
	);
    }
    public function testParse_request_url__slash() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url('/');
        $this->assertTrue($result==array(),
	    '$result did not match:'.print_r($result,1)
	);
    }
    public function testParse_request_url__single_domain() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url('/domain1');
        $this->assertTrue($result==array(0 => array(
		'name' => 'domain1',
		'id' => null,
		'ext' => null
	    )),
	    '$result did not match:'.print_r($result,1)
	);
    }
    public function testParse_request_url__single_domain_w_id() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url('/domain1/42');
        $this->assertTrue($result==array(0 => array(
		'name' => 'domain1',
		'id' => '42',
		'ext' => null
	    )),
	    '$result did not match:'.print_r($result,1)
	);
    }

    public function testParse_request_url__single_domain_w_id_and_ext() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url('/domain1/42.json');
        $this->assertTrue($result==array(0 => array(
		'name' => 'domain1',
		'id' => '42',
		'ext' => 'json'
	    )),
	    '$result did not match:'.print_r($result,1)
	);
    }

    public function testParse_request_url__multii_domain_w_id_and_ext() {
        // Remove the following lines when you implement this test.
	$result = $this->object->parse_request_url('/domain1/42/domain2/99.json');
        $this->assertTrue($result==array(
		0 => array(
		    'name' => 'domain1',
		    'id' => '42',
		    'ext' => null
		),
		1 => array(
		    'name' => 'domain2',
		    'id' => '99',
		    'ext' => 'json'
		)
	    ),
	    '$result did not match:'.print_r($result,1)
	);
    }
}
?>
