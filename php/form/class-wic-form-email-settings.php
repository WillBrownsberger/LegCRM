<?php
/*
* class-wic-form-settings.php
*
*
*/

class WIC_Form_Email_Settings extends WIC_Form_Parent  {

	
	// note that some of the variables in this form are presented in WIC_Form_Email_Inbox::layout_inbox ( group inbox_options )
	// all variables are swept into js option save function the scope of which is not bounded by the form
	public function prepare_settings_form( &$data_array, $guidance ) { 
		
			
		ob_start();
		?>
		
		<form id = "<?php echo $this->get_the_form_id(); ?>" <?php $this->supplemental_attributes(); ?> class="wic-post-form" method="POST" autocomplete = "on">

			<?php	
			// set up buffer for all group content and buffer for tabs
			$group_output = '';
			$group_headers = '';

			// setup save options button
			$button_args = array (
				'title'				=>  'Save settings for email processing',
				'name'				=> 'wic_save_email_settings',
				'type'				=> 'button',
				'button_class'		=> 'wic-form-button wic_save_email_settings',
				'button_label'		=>	'Saved',
			);	
			$save_options_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;

			$groups = $this->get_the_groups($this->get_the_entity());
			foreach ( $groups as $group ) { 

				$group_headers .= '<li class = "wic-form-field-group-' . safe_html( $group->group_slug  ) . '"><a href="#wic-field-group-' . safe_html( $group->group_slug  ) . '">' . $group->group_label  . '</a></li>';		
				$group_output .= '<div class = "wic-form-field-group" id = "wic-field-group-' . safe_html( $group->group_slug  ) . '">';				
			
				$group_output .= '<div id = "wic-inner-field-group-' . safe_html( $group->group_slug ) . '">';					
				if ( safe_html ( $group->group_legend ) > '' ) {
					$group_output .= '<p class = "wic-form-field-group-legend">' . safe_html ( $group->group_legend )  . '</p>';
				}
				// here is the main content -- either   . . .
				if ( $this->group_special ( $group->group_slug ) ) { 			// if implemented returns true -- run special function to output a group
					$special_function = 'group_special_' . $group->group_slug; 	// must define the special function too 
					$group_output .= $this->$special_function( $data_array );
				} else {	// standard main form logic 	
					$group_output .= $this->the_controls ( $group->fields, $data_array );
					$group_output .= $save_options_button;
				}
				$group_output .= '</div></div>';	
	  		} // close foreach group		
		
			// assemble tabbable output
			echo '<div id = "wic-form-tabbed">'  .
				'<ul>' .
					$group_headers .
				'</ul>' .		
				$group_output .
			'</div>';
		
			// final button group div
			echo '<div class = "wic-form-field-group" id = "bottom-button-group">'; 	
		 		echo WIC_Admin_Setup::wic_nonce_field(); 
				echo $this->get_the_legends();
			echo '</div></form>';								
		
		$this->post_form_hook( $data_array ); 

		return ob_get_clean();
	}

	protected  function group_special_user_sig () {

		global $current_user;
		$button_args = array (
			'title'				=>  'Save signature for emails from ' . $current_user->get_display_name() ,
			'name'				=> 'wic_save_current_user_sig',
			'id'				=> 'wic_save_current_user_sig',
			'type'				=> 'button',
			'button_class'		=> 'wic-form-button',
			'button_label'		=>	'Saved Signature',
		);	
		$save_sig_button = WIC_Form_Parent::create_wic_form_button( $button_args ) ;

		$sig_control = WIC_Control_Factory::make_a_control ( 'textarea' );
		$sig_control->initialize_default_values( 'signature', 'signature', '' );
		$sig_control->set_value( $current_user->get_signature());
		return 	
			'<div id="currently_logged_in">Signature for ' . safe_html( $current_user->get_display_name() ) . '</div>' .
			'<div id = "signature-editor-wrapper">' .
				$sig_control->form_control() . 
			'</div>'
			. $save_sig_button;
	}

	protected function group_special ( $slug ){
		$special_groups = array ( 'user_sig' );
		return in_array ( $slug, $special_groups );
	}


	// message
	protected function format_message ( &$data_array, $message ) {
		$formatted_message =  'Email processing settings.' . $message;
		return $formatted_message; 
	}
	
	protected function group_screen( $group ){
 		return $group != 'inbox_options';
 	}
	
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ){}
	
	public static $form_groups = array(
		'user_sig'=> array(
		   'group_label' => 'Signature',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('')),
		'non_constituent_responder'=> array(
		   'group_label' => 'Reply',
		   'group_legend' => 'If you enable an autoreply here, when non-constituents (or people who do not provide a parseable address) send repetitive emails for which you have trained responses intended for your constituents, they will get this standard reply instead of your trained reply. This autoreply only replies to trained incoming emails. It is intended to filter out non-constituents from incoming mass email campaigns. It does not take the place of a general purpose auto reply. New constituents will not be created and no activity record will be saved when an autoreply is issued.',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('use_non_constituent_responder','non_constituent_response_subject_line','non_constituent_response_message')),
		'imap_subjects'=> array(
		   'group_label' => 'Forget',
		   'group_legend' => 'Set expiration interval in days for subject line mapping.  Mappings more than set number of days old will be ignored. Default is 60 if none specified.',
		   'initial_open' => '1',
		   'sidebar_location' => '1',
		   'fields' => array('forget_date_interval')),
		'dear_token'=> array(
			'group_label' => 'Dear/Hi',
			'group_legend' => 'What you select here will be used as the greeting in the "Dear Token."',
			'initial_open' => '1',
			'sidebar_location' => '1',
			'fields' => array('dear_token_value')),
	);
}