<?php
/*
*
*  class-wic-form-advanced-search.php
*
*/

class WIC_Form_Advanced_Search extends WIC_Form_Parent  {

	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		$button_args_main = array(
			'entity_requested'			=> 'advanced_search',
			'action_requested'			=> 'form_search',
			'button_label'				=> 'Search'
		);	
		
		$button = $this->create_wic_form_button ( $button_args_main );
			
		return ( $button  ) ;
	}	
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		$formatted_message = sprintf ( 'Advanced Search.' )  . $message;
		return ( $formatted_message );
	}

	// hooks not implemented
	protected function post_form_hook( &$data_array ) {}
	public static function format_name_for_title ( &$data_array ) {}
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}

	protected static $form_groups = array(
		'search_definition'=> array(
		   'group_label' =>  'Search Definition',
		   'group_legend' =>  '',
		   'initial_open' =>  '1',
		   'sidebar_location' =>  '0',
		   'fields' =>  array('primary_search_entity')),
		'search_constituent'=> array(
		   'group_label' =>  'Constituent Search Terms',
		   'group_legend' =>  '',
		   'initial_open' =>  '1',
		   'sidebar_location' =>  '0',
		   'fields' =>  array('advanced_search_constituent','constituent_and_or')),
		'search_activity'=> array(
		   'group_label' =>  'Activity Search Terms',
		   'group_legend' =>  '',
		   'initial_open' =>  '1',
		   'sidebar_location' =>  '0',
		   'fields' =>  array('advanced_search_activity','activity_and_or')),
		'search_issue'=> array(
		   'group_label' =>  'Issue Search Terms',
		   'group_legend' =>  '',
		   'initial_open' =>  '1',
		   'sidebar_location' =>  '0',
		   'fields' =>  array('advanced_search_issue','issue_and_or')),
		'search_constituent_having'=> array(
		   'group_label' =>  'Activity Aggregate Search Terms',
		   'group_legend' =>  'Choose aggregate terms applicable to the set of activities for each selected constituent or issue.',
		   'initial_open' =>  '1',
		   'sidebar_location' =>  '0',
		   'fields' =>  array('advanced_search_constituent_having','constituent_having_and_or')),
	);

}