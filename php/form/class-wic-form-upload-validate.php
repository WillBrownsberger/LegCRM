<?php
/*
* class-wic-form-upload-validate.php
*
*	NOTE: This subfamily form extends the generic parent, so has available the whole data_array available, but does not follow the group
*		display logic built into most other forms and does not do submits 
*
*/

class WIC_Form_Upload_Validate extends WIC_Form_Upload  {

	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {
		
				

		// show message inviting validation
		$message =  sprintf ( 'Validate data in mapped fields for %s. ', $data_array['upload_file']->get_value() )  . $message;

		$css_message_level =  $this->message_level_to_css_convert[$message_level]; 

		$form =	'<form id = "' . $this->get_the_form_id() . '" class="wic-post-form" method="POST" autocomplete = "on">' .
				'<div id = "wic-validate-progress-bar"></div>' .	
				'<div id = "validation-results-table-wrapper"></div>' .
				 $data_array['ID']->form_control() .	
				 $data_array['serialized_upload_parameters']->form_control() .		
				 WIC_Admin_Setup::wic_nonce_field() . 
				 $this->get_the_legends( $sql ) . 
				 '</form>';
	
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $message, 'form' => $form ) ;

	}


	
	// functions not implemented.
	protected function format_message ( &$doa, $message) {}
	protected function group_special_upload_parameters ( &$doa ) { }
	protected function group_special_save_options ( &$doa ) {}
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {}
	 	
}