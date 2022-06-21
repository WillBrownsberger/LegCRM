<?php
/*
*
*  class-form-issue-update.php
*
*/

class WIC_Form_Issue extends WIC_Form_Parent  {

	// define form buttons
	protected function get_the_buttons ( &$data_array ) {
		return parent::get_the_buttons ( $data_array );
	}

		
	// define form message
	protected function format_message ( &$data_array, $message ) {
		$title = $this->format_name_for_title ( $data_array );
		return ( $this->get_the_verb ( $data_array ) . ' ' . ( $title ? $title :  'Issue' )  . '. ' . $message );
	}

	protected function format_name_for_title ( &$data_array ) {
		$title = $data_array['post_title']->get_value();
		return  ( $title );
	}

	protected function pre_button_messaging( &$data_array ) {}	
	
	protected function group_special ( $group ) {
		return 'activity' == $group || 'delete' == $group;	
	}

	protected function group_special_delete ( &$doa ) {
		$button_args_main = array(
			'entity_requested'			=> 'issue',
			'action_requested'			=> 'delete',
			'button_label'				=> 'Delete',
			'type'						=> 'button',
			'name'						=> 'wic-issue-delete-button',
			'id'						=> 'wic-issue-delete-button',
			'title'						=>  0 == $doa['ID']->get_value() ? 'No issue to delete' : 'Start issue delete dialog.',
			'value'						=> $doa['ID']->get_value(),
			'disabled'					=> 0 == $doa['ID']->get_value(),
		);	
		return $this->create_wic_form_button ( $button_args_main ) ;
	}


	// function to be called for special group
	protected function group_special_activity ( &$doa ) {
		return WIC_Form_Activity::create_wic_activity_area();
	}	

	// slot for activity form and contents of delete box
	protected function post_form_hook ( &$data_array )  {
		echo '<div id="hidden-blank-activity-form"></div>'. 
		'<div id="issue_delete_shell"><h4>' . 'Immediately and permanently delete this issue but NOT associated activities?' . '</h4>' .
			'<p>Best practice is to delete or reassign any associated activities before deleting the issue.' . '</p>' . 
			'<p>Orphaned activity records will remain associated with constituents.</p>' .
			'<p><strong>' . 'This action cannot cannot be undone.' . '</strong></p>' .
		'</div>'; 	
	}

	// hooks not implemented
	protected function supplemental_attributes() {}

	
	public static $form_groups = array(
		'issue_content'=> array(
		   'group_label' => 'Content',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('is_changed','post_title','post_content','post_category','wic_live_issue')),
		'issue_management'=> array(
		   'group_label' => 'Management',
		   'group_legend' => '',
		   'initial_open' => '0',
		   'sidebar_location' => '0',
		   'fields' => array('issue_staff','follow_up_status','review_date')),
		'issue_creation'=> array(
		   'group_label' => 'Codes',
		   'group_legend' => 'These fields are not updateable except through bulk uploads.',
		   'initial_open' => '0',
		   'sidebar_location' => '0',
		   'fields' => array('last_updated_by','last_updated_time','ID','OFFICE')),
		'delete'=> array(
		   'group_label' => 'Delete',
		   'group_legend' => 'Start issue delete dialog',
		   'initial_open' => '0',
		   'sidebar_location' => '0',
		   'fields' => array('')),
		'activity'=> array(
		   'group_label' => 'Activities',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('')),

	);
}