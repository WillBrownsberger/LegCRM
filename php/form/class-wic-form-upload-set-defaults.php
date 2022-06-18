<?php
/*
* class-wic-form-upload-set-defaults.php
*
*
*/

class WIC_Form_Upload_Set_Defaults extends WIC_Form_Upload  {

	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {
		
				
		$css_message_level = $this->message_level_to_css_convert[$message_level];
		$message = 	sprintf ( '%s -- database update settings. ', $data_array['upload_file']->get_value() );

		$button_args_main = array(
			'name'						=> 'wic-upload-complete-button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Finish',
			'type'						=> 'button',
			'id'						=> 'wic-upload-complete-button',
		);	
		$buttons = $this->create_wic_form_button ( $button_args_main );

		$button_args_main = array(
			'button_class'				=> 'wic-form-button second-position',
			'name'						=> 'wic-upload-back-to-match-button',
			'button_label'				=> 'Back: Redo Match',
			'type'						=> 'button',
			'id'						=> 'wic-upload-back-to-match-button',
		);	
		$buttons .= $this->create_wic_form_button ( $button_args_main );


		// invoke parent form generation logic to generate controls	
		$group_array = $this->generate_group_content_for_entity( $data_array );
		extract ( $group_array );
		$form =	'<form id = "' . $this->get_the_form_id() . '" class="wic-post-form" method="POST" autocomplete = "on">' . 
				$buttons .
				'<div id="wic-upload-default-form-body">' . 
					'<div id="wic-form-main-groups">' .  
						$main_groups . 
					'</div>' .
					'<div id="wic-form-sidebar-groups">' . 
						$sidebar_groups . 
						// place for progress bar and results div for lookup of issue titles if used
						'<div id = "wic-issue-lookup-progress-bar"></div>' .
						'<div id = "wic-issue-lookup-results-wrapper"></div>' .
					'</div>' . 
				'</div>' .					// wic-upload-default-form-body
				'<div class = "horbar-clear-fix"></div>' .
				// put the upload status in here for reference (could be anywhere)
				'<div id="initial-upload-status">' . $data_array['upload_status']->get_value() . '</div>' .
				$data_array['ID']->form_control() .
				$data_array['serialized_upload_parameters']->form_control() .
				$data_array['serialized_column_map']->form_control() .		
				$data_array['serialized_match_results']->form_control() .	
				$data_array['serialized_default_decisions']->form_control() .
				WIC_Admin_Setup::wic_nonce_field() .
				$this->get_the_legends( $sql ) .
			'</div>' .								
		' </form>';
		
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $message, 'form' => $form ) ;
	}

	// group screen
	protected function group_screen( $group ) {
		return (	
			'constituent_match' == $group->group_slug  ||
			'constituent' == $group->group_slug  ||
			'address' == $group->group_slug ||
			'phone' == $group->group_slug ||
			'email' == $group->group_slug ||
			'activity' == $group->group_slug ||
			'issue' == $group->group_slug ||
			'new_issue_creation' == $group->group_slug									
		  ) ;	
	}

		 	
}