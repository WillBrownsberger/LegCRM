<?php
/*
*
*	wic-entity-advanced-search-issue.php
*
*/



class WIC_Entity_Advanced_Search_Issue extends WIC_Entity_Advanced_Search_Row {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_issue';
		$this->entity_instance = $instance;
	} 

	public static function issue_fields () {
		return( WIC_Entity_Advanced_Search::get_search_field_options( 'issue' ) );
	}

	// in lieu of sanitize text field default sanitizor, test that in option values
	// sanitize text field replaces <= operators
	public static function issue_comparison_sanitizor ( $incoming ) { 
		return WIC_Entity_Advanced_Search_Constituent::constituent_comparison_sanitizor( $incoming);
	}

	protected static $entity_dictionary = array(

		'issue_comparison'=> array(
			'entity_slug' =>  'advanced_search_issue',
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
		'issue_field'=> array(
			'entity_slug' =>  'advanced_search_issue',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'issue_fields',),
		'issue_value'=> array(
			'entity_slug' =>  'advanced_search_issue',
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
			'entity_slug' =>  'advanced_search_issue',
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
		'advanced_search_general_comparisons'	=> array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'LIKE','label'=>'Begins with',),
			array('value'=>'>=','label'=>'Is greater than or equal to',),
			array('value'=>'<=','label'=>'Is less than or equal to',),
			array('value'=>'SCAN','label'=>'Contains (caution: slow search)',),
			array('value'=>'BLANK','label'=>'Is blank',),
			array('value'=>'NOT_BLANK','label'=>'Is not blank',),
			array('value'=>'IS_NULL','label'=>'Does not exist (IS NULL)',)
		),
		'advanced_search_id_comparisons' 		=> array(
			array('value' => '=', 'label' => 'Select specific issue by title.')
		),
		'advanced_search_quantitative_comparisons'=> array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'>=','label'=>'Is greater than or equal to',),
			array('value'=>'<=','label'=>'Is less than or equal to',)
		),
		'advanced_search_select_comparisons'	=>array(
			array('value'=>'=','label'=>'Equals',),
			array('value'=>'BLANK','label'=>'Is blank',),
			array('value'=>'NOT_BLANK','label'=>'Is not blank',)
		),
		'advanced_search_fulltext_comparisons'=> array(
			array('value'=>'CONTAINS','label'=>'Contains (use whole words)',)),	
	);

}