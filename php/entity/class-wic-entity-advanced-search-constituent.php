<?php
/*
*
*	wic-entity-advanced-search-constituent.php
*
*/



class WIC_Entity_Advanced_Search_Constituent extends WIC_Entity_Advanced_Search_Row {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_constituent';
		$this->entity_instance = $instance;
	} 

	public static function constituent_fields () {
		
		return( WIC_Entity_Advanced_Search::get_search_field_options( 'constituent' ) );
	}
	

	public static function constituent_type_options (){
		return ( array ( array ( 'value' => '', 'label' => 'Type is N/A' ) ) );	
	}

	public static function address_type_options() {
		return WIC_Entity_Address::get_option_group( 'address_type_options' )  ;	
	}

	public static function email_type_options() {
		return WIC_Entity_Email::get_option_group( 'email_type_options' )  ;	
	}

	public static function phone_type_options() {
		return WIC_Entity_Phone::get_option_group( 'phone_type_options' )  ;	
	}


	public static function constituent_entity_type_sanitizor( $type) {
		return slug_sanitizor ( $type );
	}

	// in lieu of sanitize text field default sanitizor, test that in option values
	// sanitize text field replaces <= operators
	public static function constituent_comparison_sanitizor ( $incoming ) { 
		// these exist in dictionary, but are entirely static -- faster/safer to had code
		$valid_comparisons = array('<=','=','>=','BLANK','CAT','IS_NULL','LIKE','NOT_BLANK','SCAN');
		if ( ! in_array ( $incoming, $valid_comparisons ) ) {
			return '=';	
		} else {
			return $incoming;
		}
	}

	// manage slot within parent interaction rules method
	protected function type_interaction( $field_entity ){
		if ( 'constituent' == $field_entity ) {
			$this->data_object_array['constituent_entity_type']->set_input_class_to_hide_element();
		} else { 
			$this->data_object_array['constituent_entity_type']->set_options( $field_entity. '_type_options' );
		}
	}
	
	
	protected static $entity_dictionary = array(

		'constituent_comparison'=> array(
			'entity_slug' =>  'advanced_search_constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'advanced_search_general_comparisons',),
		'constituent_entity_type'=> array(
			'entity_slug' =>  'advanced_search_constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_type_options',),
		'constituent_field'=> array(
			'entity_slug' =>  'advanced_search_constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_fields',),
		'constituent_value'=> array(
			'entity_slug' =>  'advanced_search_constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Enter value',
			'option_group' =>  '',),
		'screen_deleted'=> array(
			'entity_slug' =>  'advanced_search_constituent',
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
		'advanced_search_general_comparisons'=> array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'LIKE','label'=>'Begins with',),
			array('value'=>'>=','label'=>'Is greater than or equal to',),
			array('value'=>'<=','label'=>'Is less than or equal to',),
			array('value'=>'SCAN','label'=>'Contains (caution: slow search)',),
			array('value'=>'BLANK','label'=>'Is blank',),
			array('value'=>'NOT_BLANK','label'=>'Is not blank',),
			array('value'=>'IS_NULL','label'=>'Does not exist (IS NULL)',)),
			// allow more possibilities on constituent id comparisons than for activity or issue
		'advanced_search_id_comparisons' => array(
			array('value' 	=> 	'=', 'label' 	=> 'Equals',),
			array('value'	=>	'>=','label'	=>'Is greater than or equal to',),
			array('value'	=>	'<=','label'	=>'Is less than or equal to',),
		),
		'advanced_search_quantitative_comparisons'=> array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'>=','label'=>'Is greater than or equal to',),
			array('value'=>'<=','label'=>'Is less than or equal to',)),
		'advanced_search_select_comparisons'	=>array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'BLANK','label'=>'Is blank',),
			array('value'=>'NOT_BLANK','label'=>'Is not blank',)),
 	 );
}