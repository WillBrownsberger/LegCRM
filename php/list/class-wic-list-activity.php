<?php
/*
* class-wic-list-activity.php
*
*
*/ 

class WIC_List_Activity extends WIC_List_Parent {
	/*
	* No message header
	*
	*/
	public static $sort_string = ' activity_date desc ';
	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal ID for Activity','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'activity_date','field_label'=>'Date','listing_order'=>'10','list_formatter'=>'',), 
		array('field_slug' =>'activity_type','field_label'=>'Type','listing_order'=>'20','list_formatter'=>'activity_type_options',), 
		array('field_slug' =>'activity_amount','field_label'=>'Amount','listing_order'=>'30','list_formatter'=>'',), 
		array('field_slug' =>'constituent_id','field_label'=>'Constituent','listing_order'=>'40','list_formatter'=>'',), 
		array('field_slug' =>'issue','field_label'=>'Issue','listing_order'=>'50','list_formatter'=>'',), 
		array('field_slug' =>'pro_con','field_label'=>'Pro or Con','listing_order'=>'60','list_formatter'=>'pro_con_options',)
	);
	public static $group_by_string = 'activity.activity_amount,activity.activity_date,activity.activity_type,activity.constituent_id,activity.ID,activity.issue,activity.pro_con';

	protected function list_entity_field_filter ( $fields, &$wic_query ) {
		$filtered_fields = array();
		if ( false === $wic_query->financial_activities_in_results ) {
			foreach ( $fields as $field ) {
				if ( 'activity_amount' != $field->field_slug ) {
					$filtered_fields[] = $field;				
				}			
			}
			return ( $filtered_fields );		
		} else {
			return ( $fields );		
		}
			
	}	
	
	public function format_entity_list( &$wic_query, $header ) { 

  		// set up form
		$output = '<div id="wic-post-list"><form id="wic_activity_list_form" method="POST">';
		$output .= $this->get_the_buttons( $wic_query );	
		$output .= $this->set_up_rows ( $wic_query );
		$output .= 	WIC_Admin_Setup::wic_nonce_field() .
		'</form></div>'; 
		$output .= WIC_List_Parent::list_terminator ( $wic_query );
	
		return $output;
   } // close function

	
	
	protected function format_rows( &$wic_query, &$fields ) {

		$output = '';
		$line_count = 1;

		// get financial activity types to avoid showing nonsensical zero amounts
		$financial_types_array = WIC_Entity_Activity::$financial_types;
		
		foreach ( $wic_query->result as $row_array ) {

			$row= '';
			$line_count++;
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";
			
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) { 
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							if ( 'constituent_id' == $field->field_slug ) {
								$row .= safe_html ( $row_array->last_name . ', ' . $row_array->first_name );			
							} elseif ( 'issue' == $field->field_slug ) {
								$row .= safe_html ( $row_array->post_title );
							} elseif ( 'activity_amount' == $field->field_slug ) {								
								$row .= in_array ( $row_array->activity_type, $financial_types_array ) ? safe_html ( $row_array->activity_amount ) : '--'; 							
							} else  {
								$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
							}
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> 'constituent',
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->constituent_id,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
		} // close for each row
		return ( $output );		
	} // close function 

   
   // the top row of buttons over the list 
  	protected function get_the_buttons( &$wic_query ) { 
		global $current_user;

		$buttons = '';
		
		// wic-activity-export-button
		$button_args = array (
				'name' 			=> 'wic-activity-export-button',
				'value' 		=> 'activity,activity,activities,' . $wic_query->search_id,  // see wic-admin-navigation::do_download
				'button_label'	=>	'Download Activities',
				'id'			=> 'wic-activity-export-button',
				'button_class'	=> 'wic-form-button wic-download-button', // selector for download listener
				'title'			=>	'Download all activities meeting search criteria together with related constituent information',
			);
		$buttons .= WIC_Form_Parent::create_wic_form_button( $button_args );

		// show search form with parameters 
		if ( isset ( $wic_query->advanced_search ) ) { 
			$buttons .= WIC_List_Parent::back_to_search_form_button ( $wic_query, false );
			$buttons .= WIC_List_Parent::hidden_search_id_control( $wic_query );
			$buttons .= WIC_List_Parent::search_inspection_button( $wic_query );
			$buttons .= self::reassign_activities_button ( $wic_query, 'activities' );
			$required_capability = 'all_crm'; // downloads
			if ($current_user->current_user_authorized_to( $required_capability ) ) {
				$buttons .= self::delete_activities_button ( $wic_query, 'activities' );
			}
			$buttons .= WIC_List_Parent::search_name_control ( $wic_query );

		}
		
		return ( $buttons );
	}

	public function format_message( &$wic_query, $header='' ) {
	
		$financial_total = $wic_query->financial_activities_in_results ? sprintf ( ' Total amount for found activities is %1$s.', $wic_query->amount_total ) : '';	
		$found_string = intval( $wic_query->found_count ) > 1 ? sprintf ( 'Found %1$s activities.', $wic_query->found_count ) : 
				'Found one activity.';
		$header_message = $header . $found_string. $financial_total;		
		$header_message = WIC_Entity_Advanced_Search::add_blank_rows_message ( $wic_query, $header_message );
		return $header_message;
	}


	public static function delete_activities_button ( &$wic_query, $search_type ) {
		$button_args = array (
			'name'	=> 'delete_activities_button',
			'id'	=> 'delete_activities_button',
			'type'	=> 'button',
			'value'	=> $search_type . ',' . $wic_query->search_id . ',delete',
			'button_class'	=> 'wic-form-button wic-top-menu-button ',
			'button_label'	=>	'<span class="dashicons dashicons-trash"></span>',
			'title'	=>	'Open delete actitivies dialog',
		);
		
		$button =  WIC_Form_Parent::create_wic_form_button( $button_args );
		
		$button.= '<div class="list-popup-wrapper">
				<div id="delete_activities_dialog" title="Permanently delete '. self::activity_plural_phrase ( $wic_query->found_count ) . '." class="ui-front">' . 
					'<p></p><p>Note: The filter button (<span class="dashicons dashicons-filter"></span>) does <strong>NOT</strong> limit deletions.</p>' .
					'<p>Type "CONFIRM" (all caps) to confirm delete.</p>' .
					'<input id="confirm_activities_action" name="confirm_activities_action" placeholder="confirm . . ." value=""/>' .
					'<p><strong>Once in progress, this action cannot be cancelled or undone.</strong></p>
					<div class = "action-ajax-loader">' .
						'<em> . . . working . . .  </em>
						<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' . '">' . 
					'</div>
				</div>
			</div>';
			
		return $button;

	}


	public static function reassign_activities_button ( &$wic_query, $search_type ) {
		
		// create the basic button html
		$button_args = array (
			'name'	=> 'reassign_activities_button',
			'id'	=> 'reassign_activities_button',
			'type'	=> 'button',
			'value'	=> $search_type . ',' . $wic_query->search_id . ',reassign',
			'button_class'	=> 'wic-form-button wic-top-menu-button ',
			'button_label'	=>	'<span class="dashicons dashicons-migrate"></span>',
			'title'	=>	'Open reasssign actitivies dialog',
		);
		$button =  WIC_Form_Parent::create_wic_form_button( $button_args );
		
		// create an issue control -- the issue to which activities will be reassigned
		$issue_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
		$issue_control->initialize_default_values(  'activity', 'issue', 'placeholder' );
		$issue_control->set_value (  WIC_Entity_Activity::get_unclassified_post_array()['value'] );
		// add hidden html for use within popup
		$button.= '<div class="list-popup-wrapper">
				<div id="reassign_activities_dialog" title="Reassign ' . self::activity_plural_phrase ( $wic_query->found_count ) . ' to a new issue." class="ui-front">' . 
					'<p></p><p>Note: The filter button (<span class="dashicons dashicons-filter"></span>) does <strong>NOT</strong> limit reassignment.</p>' . 
					'<p>Select an issue to reassign activities to and then type "CONFIRM" (all caps) to confirm.</p>' .
					$issue_control->form_control() .
					'<p></p>' .
					'<input id="confirm_activities_action" name="confirm_activities_action" placeholder="confirm . . ." value=""/>' .
					'<p><strong>Once in progress, this action cannot be cancelled or undone.</strong></p>
					<div class = "action-ajax-loader">' .
						'<em> . . . working . . .  </em>
						<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' . '">' . 
					'</div>
				</div>
			</div>';
		
		return $button;
	}


	private static function activity_plural_phrase ( $count ) {
		return $count > 1 ? "all $count found activities" : "one found activity";
		
	}


}	

