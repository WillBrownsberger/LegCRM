<?php
/*
* class-wic-list-constituent.php
* 
*
*/ 

class WIC_List_Constituent extends WIC_List_Parent {

	public static $sort_string = ' last_name, first_name ';

	public static $list_fields = array( 
		array('field_slug' =>'case_assigned','field_label'=>'Staff','listing_order'=>'-3','list_formatter'=>'',), 
		array('field_slug' =>'case_status','field_label'=>'Status','listing_order'=>'-2','list_formatter'=>'',), 
		array('field_slug' =>'case_review_date','field_label'=>'Review Date','listing_order'=>'-1','list_formatter'=>'',), 
		array('field_slug' =>'ID','field_label'=>'Internal Id','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'first_name','field_label'=>'First Name','listing_order'=>'10','list_formatter'=>'',), 
		array('field_slug' =>'middle_name','field_label'=>'Middle Name','listing_order'=>'20','list_formatter'=>'',), 
		array('field_slug' =>'last_name','field_label'=>'Last Name','listing_order'=>'30','list_formatter'=>'',), 
		array('field_slug' =>'phone','field_label'=>'Phone','listing_order'=>'40','list_formatter'=>'phone_formatter',), 
		array('field_slug' =>'email','field_label'=>'Email','listing_order'=>'50','list_formatter'=>'email_formatter',), 
		array('field_slug' =>'address','field_label'=>'Address','listing_order'=>'60','list_formatter'=>'address_formatter',)
		);

	public static $group_by_string = 'constituent.case_assigned,constituent.case_review_date,constituent.case_status,constituent.first_name,constituent.last_name,constituent.middle_name,constituent.ID';

	protected function format_rows( &$wic_query, &$fields ) { 

		// check current user so can highlight assigned cases
		$current_user_id = get_current_user_id();

		$output = '';
		$line_count = 1;
		
		// loop through the rows and output a list item for each
		foreach ( $wic_query->result as $row_array ) { 

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			// add special row class to reflect case assigned status
			if ( "0" < $row_array->case_status ) {
				$row_class .= " case-open ";	
				$review_date = new DateTime ( $row_array->case_review_date );
				$today = new DateTime( current_time ( 'Y-m-d') );
				$interval = date_diff ( $review_date, $today );
				if ( '' == $row_array->case_review_date || 0 == $interval->invert ) {
					$row_class .= " overdue ";				
				}
			} 	
			
			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . $this->get_custom_field_class ( $field->field_slug, $wic_query->entity ) . '"> ';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	}


  	protected function get_the_buttons( &$wic_query ) { 
		global $current_user;

		$buttons = '';

		// only show buttons on advanced search constituent result, not in dashboard list
		if ( isset ( $wic_query->search_id ) ) {
			
			// wic-post-export-button
			$download_type_control = WIC_Control_Factory::make_a_control( 'select' );
			$download_type_control->initialize_default_values(  'list', 'wic-post-export-button', '' );
			$buttons = $download_type_control->form_control();			
			// other buttons/controls			
			$buttons .= WIC_List_Parent::make_send_email_to_found_button ( 'email_send', $wic_query->search_id, $wic_query->found_count ); 
			$buttons .= WIC_List_Parent::make_show_map_button ( 'show_map', $wic_query->search_id, $wic_query->found_count ); 
			$buttons .= WIC_List_Parent::back_to_search_form_button ( $wic_query, false );
			$buttons .= WIC_List_Parent::hidden_search_id_control( $wic_query );
			$buttons .= WIC_List_Parent::search_inspection_button( $wic_query );
			// show delete button iff user has required capability
			$required_capability = 'all_crm'; // downloads
			if ($current_user->current_user_authorized_to( $required_capability ) ) {
				$buttons .= $this->constituent_delete_button ( $wic_query );
			}
			$buttons .= WIC_List_Parent::search_name_control ( $wic_query );
		}
		
		return ( $buttons );
	}

	protected function format_message( &$wic_query, $header='' ) {
	
		$found_string = $wic_query->found_count > 1 ? sprintf ( 'Found %1$s constituents.', $wic_query->found_count ) :
			'Found one constituent.';	
		$header_message = $header . $found_string;		
		$header_message = WIC_Entity_Advanced_Search::add_blank_rows_message ( $wic_query, $header_message ); // blank search rows disregarded 
		return $header_message;
	}
   
   
	private function constituent_delete_button ( &$wic_query ) {

		$button_args = array (
			'name'	=> 'delete_constituents_button',
			'id'	=> 'delete_constituents_button',
			'type'	=> 'button',
			'value'	=> $wic_query->search_id,
			'button_class'	=> 'wic-form-button wic-top-menu-button ',
			'button_label'	=>	'<span class="dashicons dashicons-trash"></span>',
			'title'	=>	'Open delete constituent dialog',
		);
		
		$button =  WIC_Form_Parent::create_wic_form_button( $button_args );
		
		$button.= '<div class="list-popup-wrapper">
				<div id="delete_constituent_dialog" title="Permanently delete '. self::constituent_plural_phrase ( $wic_query->found_count ) . '." class="ui-front">' . 
					'<p></p>'  .
					'<p>Type "CONFIRM CONSTITUENT PURGE" (all caps) to confirm permanent delete of found constituents and their emails, addresses, phones and activity records.</p>' .
					'<input id="confirm_constituent_action" name="confirm_constituent_action" placeholder="confirm . . ." value=""/>' .
					'<p><strong>Once in progress, this action cannot be cancelled or undone.</strong></p>
					<div class = "action-ajax-loader">' .
						'<em> . . . working . . .  </em>
						<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' . '">' . 
					'</div>
				</div>
			</div>';
			
		return $button;

	}
   
   	private static function constituent_plural_phrase ( $count ) {
		return $count > 1 ? "all $count found constituents" : "one found constituent";
		
	}
 
 }	

