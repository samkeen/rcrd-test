<?php
class marker extends Domain {
    // define the attributes of this domain and associated filters

    public function  __construct() {
        parent::__construct();
        $this->config = array(
            'filters' => array(
                'interest_id'  => array('filter'=>FILTER_VALIDATE_INT),
                'coordinate_label' => array('filter'=>FILTER_SANITIZE_STRING),
                'coordinate_x'   => array('filter'=>FILTER_VALIDATE_INT),
                'coordinate_y'   => array('filter'=>FILTER_VALIDATE_INT),
                'bound_north'   => array('filter'=>FILTER_VALIDATE_INT),
                'bound_south'   => array('filter'=>FILTER_VALIDATE_INT),
                'bound_west'   => array('filter'=>FILTER_VALIDATE_INT),
                'bound_east'   => array('filter'=>FILTER_VALIDATE_INT)
            ),
            'input_sources' => array(
                'get' => INPUT_GET,
                'add' => INPUT_GET,
                'edit' => INPUT_GET,
                'delete' => INPUT_GET
            ),
	    'relations' => array(
		'has_many' => array(),
		'belongs_to' => array('interest')
	    )
        );
    }
}