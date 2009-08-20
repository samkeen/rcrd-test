<?php
/**
 * 
 * @author Sam keen
 *
 **/
require "core/DbHandle.php";
require "conf/db.php";
class Persist {

    private $name;
    private $db_handle;
    private $config;
    private $logger;

    private $target_domain;
    
    protected $bind_params = array();

    public $last_inserted_id = null;

    public function __construct(Request $request, array $config) {
        global $_db_config;
	global $logger;
	$this->name = get_class($this);
        $this->config = $config;
        $this->logger = $logger;
        $this->db_handle = new Model_DbHandle($_db_config);
        $this->target_domain = $request->target_domain;
        $this->requested_action = $request->requested_action;
    }
    public function get($field_values = array(),array $domains) {
	$field_values = $this->filter_data($field_values);
	return $this->find($field_values, $domains);
    }
    public function add(array $submitted_data, array $domains, $return_affected_rows=false) {
	$submitted_data = $this->filter_data($submitted_data);
        return $this->save($submitted_data, $domains, $return_affected_rows);
    }
    public function edit(array $submitted_data, array $domains, $return_affected_rows=false) {
	$submitted_data = $this->filter_data($submitted_data);
        return $this->save($submitted_data, $domains, $return_affected_rows);
    }
    public function delete() {
        $existing_id = $this->target_domain->id?$this->target_domain->id:null;
        $this->logger->debug(__METHOD__." deleteting {$this->name}[{$existing_id}]" );
        if ($existing_id) {
            $result = null;
            $delete_sql = $this->build_delete_statement($this->target_domain->name, 'id');
            
            try {
                $statement = $this->db_handle->prepare($delete_sql);
                $statement->bindValue(':id', $existing_id);
                $this->logger->debug(__METHOD__." Executing SQL Delete statement: {$delete_sql}");
                $this->logger->debug(__METHOD__." with query param :id={$existing_id}");
                $result = $statement->execute();
            } catch (Exception $e) {
                $this->logger->error(__METHOD__.'-'.$e->getMessage());
            }
        } else {
	    $this->logger->error(__METHOD__." No Id given to delete [{$this->name}]" );
	}
        return $result;
    }
    /**
     *
     * @param array $submitted_data
     * @return array
     * ex:
     * array(
     *  0 => array('id'=>1,'name'=>'fred',...),
     *  1 => array('id'=>4,'name'=>'joe',...)
     * )
     */
    protected function save(array $submitted_data, array $domains, $return_affected_rows=false) {
	// assert format array(0=>array(),1=>array(),...)
        $submitted_data = is_array(current($submitted_data)) ? $submitted_data : array($submitted_data);
        foreach ($submitted_data as $index => $submitted_datum) {
	    // add foreign keys to submitted data
	    $submitted_datum = array_merge($submitted_datum, $this->foreign_keys($domains));
            $existing_id = $this->target_domain->id?$this->target_domain->id:null;
            // build appropriate INSERT or UPDATE statement
            $save_statement = $existing_id
                ? $this->build_update_statement($submitted_datum)
                : $this->build_insert_statement($submitted_datum);
            $this->logger->debug(__METHOD__.' built save QUERY: '.$save_statement);
            $this->logger->debug(__METHOD__.' data: '.print_r($submitted_datum,1));
            $statement = null;
            try {
                if( ! $statement = $this->db_handle->prepare($save_statement)) {
                    $this->logger->error(__METHOD__.' - $statement::prepare failed for query: '
                        .$save_statement."\n".print_r($this->db_handle->errorInfo(),1));
                }
                foreach ($submitted_datum as $field_name => $field_value) {
                    $statement->bindValue(':'.$field_name, $field_value);
                }
                if ($existing_id) {
                    $statement->bindValue(':id', $existing_id);
                }
                /*
                 * build the return array
                 */
                $result = $rows_affected[$index]['_affected'] = $statement->execute();
                if ($result!==false) {
                    $affected_id = $existing_id?$existing_id:$this->db_handle->last_insert_id();
                    if($return_affected_rows) {
                        $new_row = $this->find_by_id($this->target_domain->name,$affected_id);
			$this->logger->debug(__METHOD__.' $new_row :: '.print_r($new_row,1));
                        $rows_affected[$index] = $new_row;
                    } else {
                        $rows_affected[$index]['id'] = $affected_id;
                    }
                } else {
                    $this->logger->error(__METHOD__.' - $statement->execute() failed for query: '
                        .$save_statement."\n".print_r($statement->errorInfo(),1));
                    $rows_affected[$index]['_error'] = 'Save failed for unknown reasons';
                }
            } catch (Exception $e) {
                $this->logger->error(__METHOD__.'-'.$e->getMessage());
            }
        }
	$this->logger->debug(__METHOD__.' $rows_affected :: '.print_r($rows_affected,1));
        return $rows_affected;
    }

    /**
     *
     * @param array $field_values {optional, we could have set the
     * various field values on the model prior to calling this method
     * @param string $table_to_query if given we query that table not
     * the one belonging to this model (for internal use only)
     */
    protected function find($field_values = array(), array $domains) {
        $find_statement = $this->get_target_domain_sql($domains);
	// get the result for this target domain
        $result[$this->target_domain->name] = $this->execute_find_statment($find_statement);
	$this->logger->debug(__METHOD__.' QUERY $result: '.print_r($result,1));
	// now grab any related domains
	$sibling_domain = $this;
	foreach ($this->parent_domains($domains) as $parent_domain) {
//	    if($this->parent_related_to_sibling($parent_domain,$sibling_domain)) {
		$result[$parent_domain->name] = $this->find_by_id($parent_domain->name, $parent_domain->id);
                $updated_result = call_user_func(array($parent_domain->name,'extra'),$result[$parent_domain->name]);
                if($updated_result) {
                    $result[$parent_domain->name] = $updated_result;
                }
//	    }
	}
        return $result;
    }
    private function find_by_id($domain_name,$id) {
	$this->bind_params = array(array('id' => $id));
	$q = $this->db_handle->escape_quote_char;
	$find_statement = "SELECT * FROM {$q}{$domain_name}{$q} WHERE id = :id";
	return current($this->execute_find_statment($find_statement));
    }
    private function execute_find_statment($statement_text) {
        $result = null;
        $this->logger->debug(__METHOD__.' executing QUERY: '.$statement_text);
        $this->logger->debug(__METHOD__.' DATA: '.print_r($this->bind_params,1));
        try {
            $statement = $this->db_handle->prepare($statement_text);
            foreach ($this->bind_params as $binding) {//$field_name => $field_value) {
                $statement->bindValue(':'.key($binding), current($binding));
		$this->logger->debug(__METHOD__." BINDING [:".key($binding)."] to value [".current($binding)."]");
            }
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error(__METHOD__.'-'.$e->getMessage());
        }
        return $result;
    }

    private function build_insert_statement($submitted_data) {
        $q = $this->db_handle->escape_quote_char;
	$this->logger->debug(__METHOD__." Building INSERT Statement with merged submitted data:".print_r($submitted_data,1));
        // need to add any fk's for related 'belongs_to' domains
        $belong_to_domains = $this->target_domain->relation('belongs_to');
//>>>>>>>>>>>>>>> $data should be siloed by domain name.
        foreach ($belong_to_domains as $belong_to_domain) {
            if(/*supplied an id*/0) {
                $submitted_data["{$belong_to_domain}_id"] = $supplied_id;
            }
        }
        $insert_statement =
            'INSERT INTO '.$this->target_domain->name."( {$q}".implode("{$q},{$q}",array_keys($submitted_data))."{$q}, {$q}modified{$q}, {$q}created{$q} )"
            .' VALUES ( :'.implode(', :',array_keys($submitted_data)).', now(), now() )';
        return $insert_statement;
    }
    /**
     *
     * @param string $table_name
     * @param array $fieldnames_for_condition Fields used to build the WHERE clause
     * @return string The constructed sql query
     */
    private function build_delete_statement($table_name, $fieldnames_for_condition) {
        $q = $this->db_handle->escape_quote_char;
        $fieldnames_for_condition = is_array($fieldnames_for_condition)?$fieldnames_for_condition:array($fieldnames_for_condition);
        $statement = "DELETE FROM {$q}{$table_name}{$q} WHERE ";
        $and = '';
        foreach ($fieldnames_for_condition as $fieldname) {
            $statement .= " $and {$q}{$fieldname}{$q} = :{$fieldname} ";
            $and = 'AND';
        }
        return $statement;
    }
    private function build_update_statement($submitted_data) {
	$this->logger->debug(__METHOD__." \ Building UPDATE using data".print_r($submitted_data,1));
        $q = $this->db_handle->escape_quote_char;
        $update_statement = "UPDATE {$q}".$this->target_domain->name."{$q} SET modified = now(), ";
        $comma = '';
        $this->logger->debug(__METHOD__." \$this->config['filters']".print_r($this->config['filters'],1));
        foreach ($submitted_data as $field_name => $field_value) {
            if (array_key_exists($field_name,$this->config['filters'])) {
                $update_statement .= $comma.$q.$field_name."{$q} = :".$field_name;
                $comma = ', ';
            }
        }
        return $update_statement . " WHERE {$q}id{$q} =  :id ";
    }
    /**
     * This essentially for a 'get' action, Translates the URL arguments into a SELECT
     * statement.
     * ex: /account/1/interest/42?_method=get
     * == Translates To ==
     * SELECT interest.* FROM interest
     * INNER JOIN account ON interest.account_id = account.id
     * WHERE account.id = 1 AND interest.id = 42
     * 
     * @return string The constructed SQL SELECT statement
     */
    private function get_target_domain_sql(array $domains) {
	$q = $this->db_handle->escape_quote_char;
	$this->logger->debug(__METHOD__.' $target_domain:'.print_r($this->target_domain,1));
	$quoted_target_domain = $q.$this->target_domain->name.$q;
	$sql = "SELECT $quoted_target_domain.* FROM $quoted_target_domain ";
	$where_clauses = array();
	if($this->target_domain->id) {
	    $where_clauses = array("$quoted_target_domain.id = :{$this->target_domain->name}_id");
	    array_push($this->bind_params, array("{$this->target_domain->name}_id"=>$this->target_domain->id));
	}
	foreach ($this->parent_domains($domains) as $requested_domain) {
	    $sql .= "\nINNER JOIN {$q}{$requested_domain->name}{$q} ON {$quoted_target_domain}.{$requested_domain->name}_id = {$q}{$requested_domain->name}{$q}.id";
	    if($requested_domain->id) {
		array_push($where_clauses, "{$q}{$requested_domain->name}{$q}.id = :{$requested_domain->name}_id");
                array_push($this->bind_params, array("{$requested_domain->name}_id"=>$requested_domain->id));
	    }
	}
	return $sql.(count($where_clauses)?"\nWHERE ".implode("\nAND ",$where_clauses):"");
    }
    private function foreign_keys(array $domains) {
	$foreign_keys = array();
	foreach ($this->parent_domains($domains) as $domain) {
	    $foreign_keys["{$domain->name}_id"] = $domain->id;
	}
	return $foreign_keys;
    }
    /**
     * Reduces the data keys to that which we have filters for.
     * Then the filters are applied
     * @param array $data
     * @return array The filtered data_pool
     */
    private function filter_data($data) {
	$this->logger->debug(__METHOD__." \$this->config['filters'] [".print_r($this->config['filters'],1)."]");
	$data_pool = array_intersect_key($data, $this->config['filters']);
	$this->logger->debug(__METHOD__." \$data after intersect with filter keys: [".print_r($data_pool,1)."]");
	// get rid of any unused filters
	$filters = array_intersect_key($this->config['filters'], $data_pool);
	$this->logger->debug(__METHOD__." \$filters after intersect with data keys: [".print_r($filters,1)."]");
        $this->data[$this->requested_action] = filter_var_array(
            $data_pool,$filters
        );
	// remove auto-fields that have been sent to us
	return $data_pool;
    }
    private function parent_domains($domains) {
        $result = array();
        if(is_array($domains)&&count($domains)) {
            $result = array_slice($domains, 1);
        }
        return $result;
    }
}