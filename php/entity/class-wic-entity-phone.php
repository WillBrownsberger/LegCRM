<?php
/*
*
*	wic-entity-phone.php
*
*/



class WIC_Entity_Phone extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'phone';
		$this->entity_instance = $instance;
	} 

	public static function phone_number_sanitizor ( $raw_phone ) { 
		return ( preg_replace("/[^0-9]/", '', $raw_phone) ) ;
	}
	
	public static function phone_number_formatter ( $raw_phone ) {
		   	
		$phone = preg_replace( "/[^0-9]/", '', $raw_phone );
   	
		if ( 7 == strlen($phone) ) {
			return ( substr ( $phone, 0, 3 ) . '-' . substr($phone,3,4) );		
		} elseif ( 10  == strlen($phone) ) {
			return ( '(' . substr ( $phone, 0, 3 ) . ') ' . substr($phone, 3, 3) . '-' . substr($phone,6,4) );	
		} else {
			return ($phone);		
		}

	}

	protected static $entity_dictionary = array(

		'constituent_id'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Constituent ID for Phone',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 10,
			'option_group' =>  '',),
		'extension'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Phone Extension',
			'required' =>  'group',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Ext.',
			'length' => 10,
			'option_group' =>  '',),
		'ID'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Internal ID for Phone',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_changed'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'length' => 1,
			'option_group' =>  '',),
		'last_updated_by'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Phone Updated By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Phone Updated Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'phone_number'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Phone Number',
			'required' =>  'group',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Number',
			'length' => 18,
			'option_group' =>  '',),
		'phone_type'=> array(
			'entity_slug' =>  'phone',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Phone Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Type',
			'option_group' =>  'phone_type_options',),
		'screen_deleted'=> array(
			'entity_slug' =>  'phone',
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
		'phone_type_options'=> array(
		  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Home',),
			  array('value'=>'1','label'=>'Cell',),
			  array('value'=>'2','label'=>'Work',),
			  array('value'=>'3','label'=>'Fax',),
			  array('value'=>'4','label'=>'Other',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
	   
	  );


}