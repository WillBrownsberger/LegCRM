<?php
/*
*
*	wic-entity-office.php
*
*
*/

class WIC_Entity_Office extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a top level entity does not process them -- no instance arg
		$this->entity = 'office';
	} 

	public static function get_office_email() {
		global $sqlsrv;
		$sqlsrv->query ( "SELECT office_email FROM office WHERE ID = ?", array(get_office()));
		return $sqlsrv->last_result[0]->office_email;
	}
		
	// set values from update process to be visible on form after save or update
	protected function special_entity_value_hook ( &$wic_access_object ) { 
		$time_stamp = $wic_access_object->db_get_time_stamp( $this->data_object_array['ID']->get_value() );
		$this->data_object_array['last_updated_time']->set_value( $time_stamp->last_updated_time );
		$this->data_object_array['last_updated_by']->set_value( $time_stamp->last_updated_by );
	}


	protected function list_offices () {
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		// retrieve all
        $wic_query->search ( array(), array( 'retrieve_limit' => 9999 ) );
		$lister_class = 'WIC_List_' . $this->entity ;
		$lister = new $lister_class;
		$list = $lister->format_entity_list( $wic_query, '' ); 
		echo $list;
	}


    // listing does not confer rights
    public static function get_super_admin_options() {
        global $current_user;
        $options= array();
        foreach( $current_user->office_user_list() as $id => $user ) {
            $options[] = array(
                'value' => $id,
                'label' => $user->user_name
            );
        }
        return $options;
    }

	// expecting comma seperated list of emails
	public static function user_list_formatter ( $list ) {
		// sorts user emails and returns them pipe separated
		$label_array = explode ( ',', $list );
		sort( $label_array );
		return ( implode ( ' | ', $label_array ) );
	}


	protected static $entity_dictionary = array(

		'office_email'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'email',
			'field_label' =>  'Office Email',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
            'placeholder' =>  'office email address',
            'length'    => 200,
			'option_group' =>  '',),
		'office_enabled'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Office Enabled',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '1',
			'transient' =>  '0',
            'placeholder' =>  '',
			'option_group' =>  'enabled_options',),
		'office_outlook_categories_enabled'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Outlook Categories',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'enabled_options',),			
		'is_changed'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
            'transient' =>  '1',
            'length'    => 1,
			'placeholder' =>  '',
			'option_group' =>  '',),
		'office_label'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Office Name',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
            'transient' =>  '0',
            'length'    => 50,
    		'placeholder' =>  'legislator name',
            'option_group' =>  '',),
		'office_last_delta_token_refresh_time'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Last Sync',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'length'    => 30,
			'placeholder' =>  '',
			'option_group' =>  '',),			
		'ID'=>array(
            'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'integer',
			'field_label' =>  'Office Number',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
            'transient' =>  '0',
    		'placeholder' =>  'System supplied',
            'option_group' =>  '',           
        ),
        'office_secretary_of_state_code'=> array(
            'entity_slug' =>  'office',
            'hidden' =>  '0',
            'field_type' =>  'text',
            'field_label' =>  'SS Code',
            'required' =>  '',
            'dedup' =>  '0',
            'readonly' =>  '0',
            'field_default' => '',
            'transient' =>  '0',
            'length'    => 5,
            'placeholder' =>  'code',
			'option_group' =>  '',),
		'office_send_mail_held'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Mail Held',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'office_send_mail_held_options',),			
		'office_type'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Office Type',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  'office_type_options',),
        'user'=> array(
            'entity_slug' =>  'office',
            'hidden' =>  '0',
            'field_type' =>  'multivalue',
            'field_label' =>  'Users',
            'required' =>  '',
            'dedup' =>  '0',
            'readonly' =>  '0',
            'field_default' => '',
            'transient' =>  '0',
            'placeholder' =>  '',
            'option_group' =>  '',),  
         'last_updated_by'=> array(
			'entity_slug' =>  'office',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Updated By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet created',
			'option_group' =>  'get_super_admin_options',),
		'last_updated_time'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'On',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet created',
			'option_group' =>  '',),
	);

	public static $option_groups = array(
		'enabled_options'=> array(
		   array('value'=>'0','label'=>'Disabled',),
           array('value'=>'1','label'=>'Enabled',)
        ),
        'office_type_options'=> array(
            array('value'=>'state_rep_district','label'=>'House',),
            array('value'=>'state_senate_district','label'=>'Senate',),
		),
		'office_send_mail_held_options'=> array(
			array('value'=>'1','label'=>'Held',),
			array('value'=>'0','label'=>'Released',)
		 ),	
    );
  

}