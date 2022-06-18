<?php
/*
*
*	wic-entity-email-compose.php
*
*/
Class WIC_Entity_Email_Compose extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) {
		$this->entity = 'email_compose';
		$this->entity_instance = '';
	} 

	// alternative construct because only supporting action_requested = 'new_blank_form' 
	public function __construct ( $action_requested, $args ) { 
		$this->set_entity_parms( $args );
		$this->$action_requested( $args );
	}

	protected function new_blank_form( $args = '', $guidance= '') { 
		$this->initialize_data_object_array();
		$this->set_special_defaults ( $this->data_object_array );
		$form =  'WIC_Form_' . $this->entity;
		$new_blank_form = new $form;
		$new_blank_form->layout_form( $this->data_object_array, $args, 'dummy', 'dummy' );
	}	
	

	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}


	protected static $entity_dictionary = array(

		'compose_bcc'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'compose_cc'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'compose_content'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '0',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'compose_issue'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '1',
			'field_type' =>  'selectmenu_issue',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Issue',
			'option_group' =>  'get_issue_options',),
		'compose_subject'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'compose_to'=> array(
			'entity_slug' =>  'email_compose',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),

	);

	// define the form message (return a message)
    // hooks not implemented
    protected function format_message ( &$data_array, $message ){}
	protected function group_special( $group_slug ) { 	}
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 


}