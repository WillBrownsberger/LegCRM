<?php
/*
*
*	wic-entity-email.php
*
*/



class WIC_Entity_Email extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'email';
		$this->entity_instance = $instance;
	} 

	public function row_form() {
	
		// include send email button 
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-email-alt"></span>',
			'type'						=> 'button',
			'id'						=> '',			
			'name'						=> '',
			'title'						=> 'Compose new email',
			'button_class'				=> 'wic-form-button email-action-button in-line-email-compose-button email-compose-button',
			'value'						=> 'mailto,' . $this->get_email_address_id() . ',0',
		);	
		
		$message = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
		$new_update_row_object = new WIC_Form_Email ( $this->entity, $this->entity_instance );
		$new_update_row = $new_update_row_object->layout_form( $this->data_object_array, $message, 'email_row' );
		return $new_update_row;
	}
		
	public function get_email_address() {
		return ( $this->data_object_array['email_address']->get_value() );	
	}
	
	public function get_email_address_id() {
		return ( $this->data_object_array['ID']->get_value() );
	}
	
	// build an array for use in email composition from email id 
	public static function get_email_address_array_from_id ( $id ) {
		if ( ! $id ) {
			return false;
		}
		global $sqlsrv;
		$email_table = 'email';
		$constituent_table = 'constituent';
		$result = $sqlsrv->query ( "
			SELECT c.id as cid, first_name, last_name, email_address 
			FROM $constituent_table c 
			INNER JOIN $email_table e on e.constituent_id = c.id 
			WHERE e.id = ? AND c.OFFICE =?", 
			array( $id, get_office() ) );
		if ( ! $result ) {
			return false;
		} else {
			return array ( array ( trim( $result[0]->first_name . ' ' . $result[0]->last_name ), $result[0]->email_address, $result[0]->cid ) ); 
		}

	} 
	protected static $entity_dictionary = array(

		'constituent_id'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Constituent ID for Email',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'email_address'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '0',
			'field_type' =>  'email',
			'field_label' =>  'Email Address',
			'required' =>  'group',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Email Address',
			'length' => 200,
			'option_group' =>  '',),
		'email_type'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Email Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Type',
			'length' => 30,
			'option_group' =>  'email_type_options',),
		'ID'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Internal ID for Email',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_changed'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'last_updated_by'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Email Updated By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Email Updated Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'screen_deleted'=> array(
			'entity_slug' =>  'email',
			'hidden' =>  '0',
			'field_type' =>  'deleted',
			'field_label' =>  'x',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
	);

	public static $option_groups = array(
		'email_type_options'=> array(
		  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Personal',),
			  array('value'=>'1','label'=>'Work',),
			  array('value'=>'2','label'=>'Other',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
	   
	  );

}