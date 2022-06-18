<?php
/*
*
*  class-wic-form-upload.php
*
*/

class WIC_Form_Upload extends WIC_Form_Parent  {

	// associate form with entity 
	protected function get_the_entity() {
		return 'upload';
	}

	// layout form consistent with other upload forms that will use the upload-form-slot for fast load
	public function layout_form (  &$data_array, $message, $message_level, $sql = '' ) {
	
				

		echo '<div id="wic-forms">'; 

			$first_form_object = $this->get_form_object (  $data_array, $message, $message_level, $sql = ''  );

			// output message 
			echo '<div id="post-form-message-box" class = "' .  $first_form_object->css_message_level . '" >' . safe_html( $first_form_object->message ) . '</div>';

			//output form
			echo '</ul></div>' .
			'<div id="upload-form-slot">' .
				$first_form_object->form  .
			'</div>';

		echo '</div>'; // end wic-forms
	}

	// this standard message function only used when load form directly.
	protected function format_message ( &$data_array, $message ) {
		return sprintf ( 'File %s successfully copied from client to server -- now check settings and parse the file. ', $data_array['upload_file']->get_value() ) ;
	} 

	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {

				
		
		$css_message_level	= $this->message_level_to_css_convert[$message_level];
		
		$buttons			= $this->get_the_buttons( $data_array );
		$group_array = $this->generate_group_content_for_entity( $data_array );
		extract ( $group_array );
		$form 	= 	'<form id = "' . $this->get_the_form_id() . '" ' . $this->supplemental_attributes() . 'class="wic-post-form" method="POST" autocomplete = "on">' .
					$buttons .	'<div id = "wic-upload-progress-bar"></div><div id = "wic-upload-console"></div>' .
					'<div id="wic-form-body">' . '<div id="wic-form-main-groups">' . $main_groups . '</div>' .
					'<div id="wic-form-sidebar-groups">' . $sidebar_groups . '</div>' . '</div>' . 
					'<div class = "wic-form-field-group" id = "bottom-button-group">' .
						WIC_Admin_Setup::wic_nonce_field() .
						$this->get_the_legends( $sql ) .
					'</div>' .								
					'</form>';
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $this->format_message ( $data_array, $message ), 'form' => $form ) ;
		
	}



	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {

		$button_args_main = array(
			'entity_requested'			=> 'upload',
			'action_requested'			=> 'form_save_update',
			'name'						=> 'wic_upload_verify_button',
			'id'						=> 'wic_upload_verify_button',
			'type'						=> 'button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Parse File',
		);	

		$buttons = $this->create_wic_form_button ( $button_args_main );
						
		return ( $buttons  ) ;
	}


	// group screen
	protected function group_screen( $group ) {
		return 
			'save_options' == $group->group_slug ||   
			'initial' == $group->group_slug; 
	}
	
	// special group handling for the upload parameters group
	protected function group_special ( $group ) {
		return false;
	}


	protected function supplemental_attributes () {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {}
	
	
	public static $form_groups = array(
		'initial'=> array(
		   'group_label' => 'Initial Upload',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('ID','upload_file','upload_status','serialized_upload_parameters','serialized_column_map','serialized_match_results','serialized_default_decisions','serialized_final_results','OFFICE')),
		'summary_results'=> array(
		   'group_label' => 'Constituent matching results',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('')),
		'save_options'=> array(
		   'group_label' => 'File Parse Settings',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('includes_column_headers','delimiter','enclosure','escape','charset','max_line_length','max_execution_time')),
		'constituent_match'=> array(
		   'group_label' => 'Constituent add/update choices',
		   'group_legend' => 'Choose how cases will be handled in the final upload process',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('add_unmatched','update_matched','protect_identity','protect_blank_overwrite')),
		'upload_parameters'=> array(
		   'group_label' => 'Upload Parameters',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('')),
		'history'=> array(
		   'group_label' => 'History',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('upload_time','upload_by')),
		'constituent'=> array(
		   'group_label' => 'Constituent default values',
		   'group_legend' => 'The constituent fields below have not been mapped. You can set defaults for them.',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('')),
		'mappable'=> array(
		   'group_label' => 'Mappable Fields',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('')),
		'address'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('address_type','city','state','zip')),
		'phone'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('phone_type')),
		'email'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('email_type')),
		'activity'=> array(
		   'group_label' => 'Activity default values ',
		   'group_legend' => 'The activity fields below have not been mapped. You can set default values for them. \nIf any activity fields are mapped or defaulted, an activity record will be created from each record in the input file.',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('activity_date','activity_type','pro_con','issue')),
		'issue'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('')),
		'new_issue_creation'=> array(
		   'group_label' => 'New Issue Titles ',
		   'group_legend' => 'Back at the mapping step, you mapped an input field to Issue Title (and did not map a field to Issue, which would override Issue Title). \nSome of the titles in your input data do not match existing Issue Titles. If you wish, WP Issues CRM will create new posts/issues, using the titles\n and content that you have mapped. You must either check to accept this or go back and unmap Issue Title.',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('create_issues')),
	);

}