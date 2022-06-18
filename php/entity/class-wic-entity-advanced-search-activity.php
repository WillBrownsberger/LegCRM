<?php
/*
*
*	wic-entity-advanced-search-activity.php
*
*/
class WIC_Entity_Advanced_Search_Activity extends WIC_Entity_Advanced_Search_Row 	{

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_activity';
		$this->entity_instance = $instance;
	} 
	
	public static function activity_fields () {
		
		return( WIC_Entity_Advanced_Search::get_search_field_options( 'activity' ) );
	}

	public static function activity_type_sanitizor( $type) {
		return slug_sanitizor ( $type );
	}

	public static function activity_comparison_sanitizor ( $incoming ) {
		return ( WIC_Entity_Advanced_Search_Constituent::constituent_comparison_sanitizor ( $incoming ) );	
	}	

	// supports incoming array from substituted activity-value field	
	public static function activity_value_sanitizor ( $incoming ) {
		if ( is_array ($incoming) ) {
			foreach ( $incoming as $key => $value ) {
				if ( !is_int( $value ) ) {
					Throw new Exception( sprintf ( 'Invalid value for multiselect field %s', $key ) );
				}	
			}
			return ( $incoming );			
		} else {
			return ( utf8_string_no_tags ( $incoming ) );
		}	
	}	

	// look up values including system reserved values (overwrite reservation value)
	public static function activity_type_options_all () {
		return ( WIC_ENTITY_Activity::$option_groups['activity_type_options'] );
	}

	protected static $entity_dictionary = array(

		'activity_comparison'=> array(
			'entity_slug' =>  'advanced_search_activity',
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
		'activity_field'=> array(
			'entity_slug' =>  'advanced_search_activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'activity_fields',),
		'activity_type'=> array(
			'entity_slug' =>  'advanced_search_activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'activity_type_options_all',),
		'activity_value'=> array(
			'entity_slug' =>  'advanced_search_activity',
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
			'entity_slug' =>  'advanced_search_activity',
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
		'advanced_search_id_comparisons' => array(
			array('value' => '=', 'label' => 'Equals')),
		'advanced_search_quantitative_comparisons'=> array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'>=','label'=>'Is greater than or equal to',),
			array('value'=>'<=','label'=>'Is less than or equal to',)),
		'advanced_search_select_comparisons'	=>array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'BLANK','label'=>'Is blank',),
			array('value'=>'NOT_BLANK','label'=>'Is not blank',)),
		'advanced_search_fulltext_comparisons'=> array(
			array('value'=>'CONTAINS','label'=>'Contains (use whole words)',)),			
 	 );
}