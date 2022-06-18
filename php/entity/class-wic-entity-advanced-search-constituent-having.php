<?php
/*
*
*	wic-entity-advanced-search-constituent-having.php
*
*/



class WIC_Entity_Advanced_Search_Constituent_Having extends WIC_Entity_Advanced_Search_Row {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_constituent_having';
		$this->entity_instance = $instance;
	} 

	public static function constituent_having_fields () {

		
		
		// get activity fields
		$all_activity_fields = WIC_Entity_Advanced_Search::get_search_field_options( 'activity' );
		
		// select appropriate for aggregation
		$having_fields = array();	
		foreach ( $all_activity_fields as $field ) { 
			if ( 	false !== strpos ( $field['label'], 'activity:activity_amount' ) || 
					false !== strpos ( $field['label'], 'activity:activity_date' ) ||
					false !== strpos ( $field['label'], 'activity:last_updated_time' ) 			
				 )  {
				$having_fields[] = $field;			
			}
		}
		
		return ( $having_fields );
	
	}

	public static function constituent_having_type_sanitizor( $type) {
		return slug_sanitizor ( $type );
	}

	public static function constituent_having_comparison_sanitizor ( $incoming ) { 
		return ( WIC_Entity_Advanced_Search_Constituent::constituent_comparison_sanitizor ( $incoming ) );	
	}	

	public static function constituent_having_aggregator_sanitizor ( $incoming ) {
		if ( !in_array( $incoming, array('MAX','MIN','AVG','COUNT','SUM'))) {
			return 'COUNT';
		} else {
			return $incoming;
		}
	}

	protected function manage_count_field ( $aggregatorIsCount ) {
		if ( $aggregatorIsCount ) {
			$this->data_object_array['constituent_having_field']->set_input_class_to_make_element_invisible();
		}
	} 

	protected static $entity_dictionary = array(

	
		'constituent_having_aggregator'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'advanced_search_having_aggregators',),
		'constituent_having_comparison'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'advanced_search_quantitative_comparisons',),
		'constituent_having_field'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_having_fields',),
		'constituent_having_value'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
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
		'having_count'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Count',
			'option_group' =>  '',),
		'screen_deleted'=> array(
			'entity_slug' =>  'advanced_search_constituent_having',
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
		'advanced_search_having_aggregators'=> array(
		  array('value'=>'AVG','label'=>'Average',),
			  array('value'=>'COUNT','label'=>'Count',),
			  array('value'=>'MAX','label'=>'Maximum',),
			  array('value'=>'MIN','label'=>'Minimum',),
			  array('value'=>'SUM','label'=>'Sum',)),
		'advanced_search_quantitative_comparisons'=> array(
		  array('value'=>'=','label'=>'Equals',),
			  array('value'=>'>=','label'=>'Is greater than or equal to',),
			  array('value'=>'<=','label'=>'Is less than or equal to',)),
	   
	  );
	  
}