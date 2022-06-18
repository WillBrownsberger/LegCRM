<?php
/*
* class-wic-form-upload-match.php
*
*
*/

class WIC_Form_Upload_Match extends WIC_Form_Upload  {

	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {
		
				
		$css_message_level = $this->message_level_to_css_convert[$message_level];
		$message =  sprintf ( 'Match records from %s to your previously saved constituents in WP Issues CRM. ', $data_array['upload_file']->get_value() );

		$match_strategies = new WIC_Entity_Upload_Match_Strategies ();
		$match_layout = 	$match_strategies->layout_sortable_match_options( $data_array['ID']->get_value(), true ); // note that true value resets match results array 

		$button_args_main = array(
			'name'						=> 'wic-upload-match-button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Match',
			'type'						=> 'button',
			'id'						=> 'wic-upload-match-button',
			'disabled'					=> '' == $match_layout,
		);	
		$buttons = $this->create_wic_form_button ( $button_args_main );

		$button_args_main = array(
			'button_class'				=> 'wic-form-button second-position',
			'name'						=> 'wic-upload-back-to-map-button',
			'button_label'				=> 'Back: Redo Map',
			'type'						=> 'button',
			'id'						=> 'wic-upload-back-to-map-button',
		);	
		$buttons .= $this->create_wic_form_button ( $button_args_main );

		$form =	'<form id = "' . $this->get_the_form_id() . '" class="wic-post-form" method="POST" autocomplete = "on">' .
			$buttons . 
			'<div id = "upload-match-wrap">' .
				( ( $match_layout == '' ) ? '<h3>No apparent identifiers mapped for matching.  Redo Map.</h3> 
					<p>Consider running <em>Express</em> after mapping  -- express bypasses matching altogether.</p>' : $match_layout ) .
			'</div><div class = "horbar-clear-fix"></div>' .
			$data_array['ID']->form_control() .
			$data_array['serialized_upload_parameters']->form_control() .		
			WIC_Admin_Setup::wic_nonce_field() . 
			$this->get_the_legends( $sql ) . 
			'</div>' .								
		'</form>'; 
		
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $message, 'form' => $form ) ;
		
	}
	 	
}