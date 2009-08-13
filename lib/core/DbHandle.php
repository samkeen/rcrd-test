<?php
class Model_DbHandle {
    private $hostname;
    private $database;
    private $username;
    private $password;
    private $logger;
    public $escape_quote_char;

    private $db_handle;

    /**
     *
     * @param array $config
     * <code>
     * array(
     * 	'type'	   => '...',
     *  'hostname' => '...',
     *  'username' => '...',
     *  'password' => '...',
     *  'database' => '...'
     * )
     * </code>
     */
    public function __construct(array $config) {
        global $logger;
        $this->logger = $logger;
        $this->hostname = $config['hostname'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->database = $config['database'];
        $this->connect($config['type']);
    }
    public function __destruct() {
        $this->db_handle = null;
    }
    /**
     * Run a strait query verbatium
     */
    public function query($sql) {
        $result = null;
        try {
            $result_set = $this->db_handle->query($sql);
            if(!$result_set) {
                $this->logger->warn("Failed result set for query [{$sql}] with Error Info: ".print_r($this->db_handle->errorInfo(),1));
            } else {
                $result = $result_set->fetch(PDO::FETCH_ASSOC);
            }

        } catch (Exception $e) {
            $this->logger->error(__METHOD__.$e->getMessage());
        }
        return $result;
    }
    public function execute($sql) {
        $count = null;
        try {
            $count = $this->db_handle->exec($sql);
        } catch (Exception $e) {
            $this->logger->error(__METHOD__.$e->getMessage());
        }
        return $count;
    }
    public function prepare($statement_text) {
        return $this->db_handle->prepare($statement_text);
    }
    public function last_insert_id($sequence_id=null) {
        $last_insert_id = null;
        try {
            $last_insert_id = $this->db_handle->lastInsertId($sequence_id);
        } catch ( Exception $e ) {
            $this->logger->error(__METHOD__.$e->getMessage());
        }
        return $last_insert_id;
    }
    private function connect($type) {
        $connected = false;
        try {
            if (strtoupper($type)=='PGSQL') {
                $this->db_handle = new PDO("pgsql:dbname={$this->database};host={$this->hostname}", $this->username, $this->password);
                $this->db_handle->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
                $this->escape_quote_char = '"';
            } else if(strtoupper($type)=='MYSQL') {
                $this->db_handle = new PDO('mysql:host='.$this->hostname.';dbname='.$this->database, $this->username, $this->password);
                $this->escape_quote_char = '`';
            }
            $connected = true;
        } catch ( Exception $e ) {
            $this->logger->error(__METHOD__.$e->getMessage());
        }
        return $connected;
    }
}
