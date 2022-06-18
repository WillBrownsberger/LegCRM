<?php
/*
*
*	wic-entity-email-settings.php
*
*	entity invoked by WIC_Form_Email_Inbox::setup_settings_form 
*/
class WIC_Entity_Email_Settings extends WIC_Entity_Parent {

	/*
	*
	* basic entity functions
	*
	*
	*/
	protected function set_entity_parms( $args ) {
		$this->entity = 'email_settings';
		$this->entity_instance = '';
	} 

	// dummy to pass to constructor
	protected function no_action ( $args ) {}

	// populated by js
	public function settings_form ( $args = '', $guidance = '' ) {
		
		// set up blank array
		$this->initialize_data_object_array();
		// get option values, including refreshed defaults for text areas
		$options_object = WIC_Entity_Email_Process::get_processing_options()['output'];
		/* 
		* populate array with saved option values
		*
		* note that blank is never a valid option for any of the fields that might have non-blank defaults
		*
		*/
		foreach ( $this->data_object_array as $field_slug => $control ) { 
			if ( isset ( $options_object->$field_slug ) &&  $options_object->$field_slug > '' ) { 
				$control->set_value ( $options_object->$field_slug  );
			}
		} 
		$new_form = new WIC_Form_Email_Settings;
		return $new_form->prepare_settings_form( $this->data_object_array, $guidance );
	}	



	protected static $entity_dictionary = array(
		'dear_token_value' =>array(
			'entity_slug' =>  'email_settings',
			'length' => 20,
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Dear Token Value',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => 'Dear',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'dear_token_value_options',),
		'forget_date_interval'=> array(
			'entity_slug' =>  'email_settings',
			'hidden' =>  '0',
			'field_type' =>  'integer',
			'field_label' =>  'Days in interval',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => 60,
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'non_constituent_response_message'=> array(
			'entity_slug' =>  'email_settings',
			'hidden' =>  '0',
			'field_type' =>  'textarea',
			'field_label' =>  'Non Constituent Response Text (should include signature -- no signature will be added)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'non_constituent_response_subject_line'=> array(
			'entity_slug' =>  'email_settings',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Non Constituent Response Subject Line',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'use_non_constituent_responder'=> array(
			'entity_slug' =>  'email_settings',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Use Non-constituent Responder?',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '1',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'use_non_constituent_responder_options',),
 	);

	 public static $option_groups = array(
		'use_non_constituent_responder_options'=> array(
		  	  array('value'=>'1','label'=>'Disabled: Never send standard non-constituent reply',),
			  array('value'=>'2','label'=>'Send standard non-constituent reply only to those successfully parsed as non-constituents',),
			  array('value'=>'3','label'=>'Send standard non-constituent reply to all those not successfully parsed as constituents',)),
		'dear_token_value_options'=> array(
		  	  array('value'=>'Dear','label'=>'Dear',),
			  array('value'=>'Hi','label'=>'Hi',),
			  array('value'=>'Hey','label'=>'Hey',)),			  
	  ); 

}