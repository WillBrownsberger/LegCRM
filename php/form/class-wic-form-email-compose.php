<?php
/*
* class-wic-form-email-inbox.php
*
*
*/

class WIC_Form_Email_Compose extends WIC_FORM_Parent {

	// no header tabs
	

	// customized layout_form to support tabbed groups
	public function layout_form ( &$data_array, $message, $message_level, $sql = '' ) { 
		// compatibiility:
		$args = $message;
		$search_link = '';
		$draft_id = 0;
		extract ( $args, EXTR_OVERWRITE );

		// format attachment button
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-paperclip"></span>',
			'type'						=> 'button',
			'id'						=> 'upload-attachment-button',			
			'name'						=> 'upload-attachment-button',
			'title'						=> 'Upload attachment'			
		);	
		
		$attachment_button = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
	
		//output compose form
		echo '<div id="compose-message-popup" class="compose-message-popup" title="Compose Message" class="ui-front">' .
				'<div id = "compose-envelope-wrapper" class = "envelope-edit-wrapper" >' .
					'<table><col width="60">' .
						( $search_link ? '' :
					 		(
					 		'<tr><td>To: </td><td>' . $data_array['compose_to']->form_control() . '</td></tr>' .
						 	'<tr><td>Cc: </td><td>' . $data_array['compose_cc']->form_control() . '</td></tr>' .
						 	'<tr><td>Bcc: </td><td>' . $data_array['compose_bcc']->form_control() . '</td></tr>' 
							)
						) .
						'<tr><td>Subject: </td><td>' . $data_array['compose_subject']->form_control() . '</td></tr>' .
					 	'<tr><td>Issue: </td><td>' . $data_array['compose_issue']->form_control() . '</td></tr>' .
						( $search_link ? '' : (  '<tr><td>' . $attachment_button . '</td><td><ul id="compose-attachment-list">' . WIC_Entity_Email_Attachment::generate_attachment_list( $draft_id ) . '</ul></td></tr>' )  ) .
				 	'</table>' .
				'</div>' .
				'<div id = "compose-content-wrapper">' .
					$data_array['compose_content']->form_control() .	
				'</div>' .
				 '<div id="search-link">' . $search_link . '</div>' .
			'</div>';
	}
	
	public static $form_groups = array(

		'compose_options'=> array( 
		   'group_label' => '', 
		   'group_legend' => '', 
		   'initial_open' => '1', 
		   'sidebar_location' => '1', 
		   'fields' => array('compose_to','compose_cc','compose_bcc','compose_subject','compose_issue','compose_content')),

	);

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_buttons( &$data_array ){}
	protected function format_message ( &$data_array, $message ) {}	
	protected function group_special( $group ) {}
	protected function group_screen ( $group ) {}
	protected function pre_button_messaging ( &$data_array ){}
    protected function post_form_hook ( &$data_array ) {} 

} // class

