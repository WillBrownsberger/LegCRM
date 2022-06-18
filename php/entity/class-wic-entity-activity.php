<?php
/*
*
*	wic-entity-activity.php
*
*/



class WIC_Entity_Activity extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) {
		$this->entity = 'activity';
	} 

	// supports creation of this entity without taking an action in construct
	protected function nothing(){}

	// remove any non numeric characters from amount -- commas, currency most likely in csv upload (done by js online)
	public static function activity_amount_sanitizor ( $raw_amount ) { 
		return preg_replace("/[^0-9.]/", '', $raw_amount );
	}

	public static function set_up_activity_area( $parent_entity_id, $parameters_object ) {

		// these parameters determine assembly, but are not part of sql assembled
		$init = $parameters_object->initialLoad;
		$parent = $parameters_object->parentForm;

		global $sqlsrv;
		
		/* prepare list before buttons because want to see found count */
		$activity_list = '<p id="wic_no_activities_message"></p>
						  <ul id="wic_activity_list">';
		
		$found_count = 0;
		$count_activities = 0;	
		if ( $parent_entity_id > 0 ) {

			$where_predicate = 'constituent' == $parent ? 'a.constituent_id' : 'a.issue';
			$limit_phrase = $init ? "TOP 10" : 'TOP 200'; // limit only on init -- init false means user requested show all
			$select_phrase = "a.ID, a.constituent_id, a.activity_type, a.activity_date, a.activity_amount, a.issue, a.pro_con, a.activity_note, a.last_updated_by, a.last_updated_time, a.related_inbox_image_record, a.related_outbox_record,
				IIF( 
					p.ID IS NULL, 
					CONCAT('Hard deleted issue ( ID was ', a.issue,' )' ), 
					post_title 		
				) as post_title, 
				c.first_name, c.last_name
				";
			$assembly_phrase = "FROM activity a
				LEFT JOIN constituent c on c.ID = a.constituent_id
				LEFT JOIN issue p on p.ID = a.issue
				WHERE $where_predicate = ? 
				";
			$order_phrase = " ORDER BY activity_date DESC ";
			// query normally works for either parent (issue or post) 
			// LEFT JOIN to post to cover case where issue deleted	
			$activities = $sqlsrv->query(
					"SELECT $limit_phrase $select_phrase $assembly_phrase $order_phrase",
					array ( $parent_entity_id )
				);		
			$count_activities = $sqlsrv->num_rows; 
					
			if ( $count_activities > 0 ) {
				// prepare activity list
				foreach ( $activities as $activity ) {
					$activity_list .= self::format_activity_list_item ( $activity );
				}
			}

			// get count over 9
			if ( $count_activities > 9 ) {
				$found_array = $sqlsrv->query ( "SELECT COUNT(*) as found_count " . $assembly_phrase, array( $parent_entity_id ) );
				$found_count = $found_array[0]->found_count;
			} else {
				$found_count = $count_activities;
			}
		}
		
		$activity_list .= '</ul>';

		if ( $found_count > $count_activities ) { 
			$more_word = $found_count > 200 ? "190 additional" : "all";
			$more_phrase = $init ? 
				( '<h4 id="show_all_activities"> . . . load ' . $more_word . ' activities ( total count is ' . $found_count . ' ) &raquo;</h4>' ) :
				( '<h4>200 activities loaded.  Use advanced search to browse more.  The total count is ' . $found_count . '.</h4>' );
			$activity_list .= $more_phrase;
		}		
		
		
		/* prepare buttons */
		$buttons = '';
		
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-plus-alt"></span>',
			'type'						=> 'button',
			'id'						=> 'add-new-activity-button',			
			'name'						=> 'add-new-activity-button',
			'title'						=> 'Add new activity'			
		);	
		
		$buttons .= WIC_Form_Parent::create_wic_form_button ( $button_args_main );

		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-paperclip"></span>',
			'type'						=> 'button',
			'id'						=> 'upload-document-button',			
			'name'						=> 'upload-document-button',
			'title'						=> 'Upload document'			
		);	
		
		$buttons .= WIC_Form_Parent::create_wic_form_button ( $button_args_main );



		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-download"></span>',
			'type'						=> 'button',
			'title'						=> 'Download activities (all for this ' . $parent . ').',
			'name'						=> 'download-activities-form-button',			
			'id'						=> 'download-activities-form-button',			
			'button_class'				=> 'wic-form-button wic-download-button',
			'value'						=> 'activity,activity,' . $parent . ',' . $parent_entity_id,
		);	
		
		$buttons .= WIC_Form_Parent::create_wic_form_button ( $button_args_main );
		if ( 'issue' == $parent  ) {
		
			$buttons .= WIC_List_Parent::make_send_email_to_found_button ( 'issue_activity_list_email_send', $parent_entity_id, $found_count );
			$buttons .= WIC_List_Parent::make_show_map_button ( 'show_issue_map', $parent_entity_id, $found_count );
			$search_parms = (object) array(
				'found_count'		=> $found_count,
				'search_id'			=> $parent_entity_id,
			);	
			$buttons .= WIC_List_Activity::reassign_activities_button ( $search_parms, 'issue' );

			$required_capability = 'all_crm'; // downloads
			global $current_user;
			if ($current_user->current_user_authorized_to( $required_capability ) ) {
				$buttons .= WIC_List_Activity::delete_activities_button ( $search_parms, 'issue');
			}
		}

		$buttons .= self::make_activity_type_filter_button();	
		
		ob_start();
		new WIC_Entity_Activity ('new_blank_form', array() ) ;
		$hidden_form = ob_get_clean();
		
		return array ( 'response_code' => true, 'output' => (object) array ( 'activityList' => $buttons . $activity_list, 'activityForm' => $hidden_form ) );	
	
	}

	// takes object with properties having field names; prepares sanitized output for list display and possible use in update form
	public static function format_activity_list_item ( $activity ) {
	
		

		// carry lable through from form to list item if coming that way
		if ( isset ( $activity->constituent_id_label ) ) { 
			$name_show = $activity->constituent_id_label; 
		} else {
			$name_show =  $activity->first_name . ' ' . $activity->last_name ;
		}
		$name_show = ( '' == trim ( $name_show ) ? 'Name unknown' : trim ( $name_show ) );
		$sanitized_note = utf8_string_no_tags ( $activity->activity_note ); // strip tags, etc.
		$shortened_note = '';
		
		$user = get_user_by ( 'id', $activity->last_updated_by ); 
		$display_name = is_object ( $user ) ? ( isset( $user->user_name ) ? $user->user_name : $user->user_email ) : 'Deleted User';

		if ( 'wic_reserved_77777777' == $activity->activity_type ) { 
			return '<li>' .
					'<span class="dashicons dashicons-edit"></span>' . 
					'<span class="activity_list_ID">' . $activity->ID . '</span>' .
					'<span class="activity_list_activity_date">' . safe_html ( $activity->activity_date ) . '</span>, ' .
					'<span class="activity_list_activity_type">' . safe_html ( $activity->activity_type ). '</span>' .
					'<span class="activity_list_activity_type_show not-really-show">' . safe_html( value_label_lookup ( $activity->activity_type, self::get_option_group('activity_type_options') ) ) . '</span>' . 
					' <button type="button"
						class="document-link-button", ' . 
						// see WIC_Admin_Navigation::do_download and ajax.js wpIssuesCRM.doMainDownload
						'value="document,document,document,' . $activity->ID . '"' .
						'>' 
						.  $sanitized_note .
						'</button>' .
					'<span class="activity_list_pro_con">' . safe_html( $activity->pro_con ) . '</span>' .
					'<span class="activity_list_activity_note">' . safe_html ( $activity->activity_note ) . '</span> ' .
					'<span class="activity_list_issue">' . safe_html ( $activity->issue ) . '</span>' .
					'<span class="activity_list_issue_show"><a class = "activity_list_issue_show_link" target = "_blank" href = "' . WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . safe_html( $activity->issue ). '">' . safe_html( $activity->post_title ) .'</a></span>' .
					'<span class="activity_list_constituent_id">' . $activity->constituent_id . '</span>' .
					'<span class="activity_list_last_updated_by">' . $activity->last_updated_by . '</span>' .
					'<span class="activity_list_last_updated_by_show">' . $display_name . '</span>' .
					'<span class="activity_list_last_updated_time">' . $activity->last_updated_time . '</span>' .

					(  $activity->constituent_id  ? ('<span class="activity_list_constituent_show"><a class="activity_list_constituent_show_link" target = "_blank" href = "' . WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=' . $activity->constituent_id . '">' . safe_html(  $name_show ) .'</a></span>' ) : '' ) .
				'</li>';
		
		} else {
			if ( ( 'wic_reserved_99999999' == $activity->activity_type ) || ( 'wic_reserved_00000000' == $activity->activity_type ) ) {
				$matches = array();
				preg_match( '#<tr><td>Subject: </td><td>([^<]*)</td></tr>#', $activity->activity_note, $matches );
				if ( $matches ) {
					$shortened_note =  ' -- subject: ' . safe_html ( utf8_string_no_tags ( $matches[1] ) );
				}
				/*
				* attachment links are created at the time a message is displayed with time limited nonces
				* if message is saved as activity, it will save with the time sensitive nonces
				* here substituting a current nonce for display with the activity note so that the message attachments can be viewed.
				*
				* all message notes have an attachments-display-line; if an exploit adds one within message text, we will see > 1 here and do nothing here
				*/
				// this $dom will be a single email's content
				$dom = new DOMDocument;
				// only attempt to fix on successful parse;
				if ( @$dom->loadHTML( $activity->activity_note   )  ) {
					// all emails saved as activity have an attachment display line, although may be empty div, so can rely on their being one
					// should note be more than one, but if there are, stop
					if ( $element = $dom->getElementById ( 'attachments-display-line' ) ) { 
						if ( is_array ( $element ) ) {
							error_log ( 'WIC_Entity_Activity::format_activity_list_item detected activity note containing multiple attachments-displine-line divs.');
						} else {
							if ( $links = $element->getElementsByTagName( 'a') )  {
								if ( $links->length ) {
									$made_substitutions = false;
									for ($i = 0; $i < $links->length; $i++) {
										if ( $href = $links[$i]->getAttribute( 'href' ) ) {
											$href_base = preg_split ( '#&(attachment_nonce|wic_nonce)#', $href )[0]; // bring over legacy links; the current usage is "wic_nonce"
											$query_vars = array();
											parse_str ( $href_base, $query_vars );
											$links[$i]->setAttribute( 'href',  preg_replace( '#&amp;#', '&',  $href_base ) .  '&wic_nonce=' . WIC_Admin_Setup::wic_create_nonce($query_vars['attachment_id']) );
											$made_substitutions = true;
										}
									}
									// having made substitutions, spit the html back whence it came
									if ( $made_substitutions ) {
										$activity->activity_note = $dom->saveHTML(); 
									} // did some substitutions
								} // links array length > 0;
							} // found array of links
						}  // not an array of elements with id attachments-display-line -- should alwys be only one except in expoits; 
					}	// have an attachments display line -- should always be true
				} // parseable html in activity note
				// append link to original if available
				$related_inbox_id  =  $activity->related_inbox_image_record ?? 0;
				$related_outbox_id =  $activity->related_outbox_record ?? 0; 
				if ( max( $related_inbox_id,  $related_outbox_id ) ) {
					$activity->activity_note .= self::get_link_to_original ( $related_inbox_id, $related_outbox_id  );
				}
				
			}// 999 or 000
			
			// especially for comments and incoming email, $note may be utf-8 -- can't just use substr
			if ( ! $shortened_note ) {
				$shortened_note =  ' -- note:' . safe_html ( WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words ( $sanitized_note, 50, 8 ) );
			}
			$show_note = 
				'<span class="activity_list_activity_note_show">  ' . 
					 $shortened_note .
				'</span> ';
			return 	'<li>' .
				( 'wic_reserved_88888888' == $activity->activity_type ? '<span class="dashicons dashicons-admin-comments" title = "' . safe_html( $sanitized_note ) . '"></span>' : '<span class="dashicons dashicons-edit"></span>' ) .
				'<span class="activity_list_ID">' . $activity->ID . '</span>' .
				'<span class="activity_list_activity_date">' . safe_html ( substr($activity->activity_date,0,10) ) . '</span>, ' .
				'<span class="activity_list_activity_type">' . safe_html ( $activity->activity_type ). '</span>' .
				'<span class="activity_list_activity_type_show">' . ( $activity->activity_type > '' ? safe_html( value_label_lookup ( $activity->activity_type, static::get_option_group('activity_type_options' ) ) ) : '' )  . '</span>' . ( $activity->activity_type > '' ? ', '  : '' ) .
				'<span class="activity_list_activity_amount">' . safe_html( $activity->activity_amount ) .'</span>' .
				'<span class="activity_list_activity_amount_show">' . ( in_array( $activity->activity_type, self::$financial_types ) ? safe_html( $activity->activity_amount ) . ', ' : '' ) . '</span> ' .
				'<span class="activity_list_issue">' . safe_html ( $activity->issue ) . '</span>' .
				'<span class="activity_list_issue_show"><a class = "activity_list_issue_show_link" target = "_blank" href = "' .WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . safe_html( $activity->issue ). '">' . safe_html( $activity->post_title ) .'</a></span>' .
				'<span class="activity_list_constituent_id">' . $activity->constituent_id . '</span>' .
				'<span class="activity_list_constituent_show"><a class="activity_list_constituent_show_link" target = "_blank" href = "' . WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=' . $activity->constituent_id . '">' . safe_html(  $name_show ) .'</a></span>' .
				'<span class="activity_list_pro_con">' . safe_html( $activity->pro_con ) . '</span>' .
				'<span class="activity_list_pro_con_show">' . ( $activity->pro_con > '' ?  ( ' (' . safe_html ( value_label_lookup ( $activity->pro_con, static::get_option_group('pro_con_options')  ) ) . ') ' ) : '' ) . '</span> ' .
				'<span class="activity_list_activity_note">' . safe_html ( $activity->activity_note ) . '</span> ' .
				'<span class="activity_list_last_updated_by">' . $activity->last_updated_by . '</span>' .
				'<span class="activity_list_last_updated_by_show">' . $display_name . '</span>' .
				'<span class="activity_list_last_updated_time">' . substr( $activity->last_updated_time,0,10) . '</span>' .
				( $shortened_note > '' ? $show_note : ' ' ) .
			'</li>';
		}
	}

	private static function get_link_to_original ( $related_inbox_image_record, $related_outbox_record  ) {
		
		if ( $related_inbox_image_record ) {
			$link = $related_inbox_image_record;
			$page = 'done';
		} elseif ( $related_outbox_record ) {
			$link = $related_outbox_record;
			$page = 'sent';
		} else {
			return '';
		}
		
		$button_args = array (
			'name'	=> 'show_original_message',
			'id'	=> 'show_original_message',
			'type'	=> 'button',
			'value'	=> $page,
			'button_class'	=> 'wic-form-button show-original-message ',
			'button_label'	=>	'Show original message</span>',
			'title'	=> 'Show original message if still available',
		);
		
		$button =  WIC_Form_Parent::create_wic_form_button( $button_args );
		
		return '<div class="show-original-message-wrapper">' . $button . '<div class = "message-ID">' . $link . '</div></div>';	
	
	}

	public static function popup_save_update ( $dummy_id, $form_data ) { 
		$activity_entity = new WIC_Entity_Activity ( 'nothing', array() );
		return $activity_entity->form_save_update_message_only ( $form_data );	
	}	
	
	// differs from parent save/update in that does not send back form, only message -- nothing to refresh except ID and notes could change in sanitization
	protected function form_save_update_message_only ( $form_data ) {

		// populate the array from the submitted form
		$this->initialize_data_object_array();

		foreach ( $this->fields as $field ) {  	
			if ( isset ( $form_data->{$field->field_slug} ) ) {		
				$this->data_object_array[$field->field_slug]->set_value( $form_data->{$field->field_slug} );
			}	
		} 

		$save = ( 0 == $this->data_object_array['ID']->get_value() );

		// return with no changes message if nothing to do
		if ( '0' === $this->data_object_array['is_changed']->get_value() ) {
			return array ( 
				'response_code' => true, 
				'output' => (object) array ( 
					'message' => 'No changes to save -- no action taken.', 
					'message_level' => 'error'  
				) 
			);	
		}

		// sanitize, validate and check for completeness the array
		$this->sanitize_values();
		$this->validate_values();
		// don't clutter with additional messages if validation fails.
		if ( '' === $this->outcome ) {
			$this->required_check();
		}
		if ( false === $this->outcome ) {
			return array ( 	
				'response_code' => true, 
				'output' => (object) array ( 
					'message' => 'Activity save not successful:' . $this->explanation, 
					'message_level' => 'error'  
				) 
			);	
		}
		
		// form was good so do save/update 
		$wic_access_object = WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		$wic_access_object->save_update( $this->data_object_array );
		// handle failed save/update 
		if ( false === $wic_access_object->outcome ) {
			return array ( 	
				'response_code' => true, 
				'output' => (object) array ( 
					'message' => 'Activity save not successful: ' . $wic_access_object->explanation, 
					'message_level' => 'error'  
				) 
			);
		// proceed on successful save/update
		} else {
			// retrieve the new activity ID from the save process
			if ( $save ) { 
				$this->data_object_array['ID']->set_value( $wic_access_object->insert_id );
				$form_data->ID = $wic_access_object->insert_id;
			}
			// populate form data with sanitized values
			foreach ( $this->data_object_array as $field_slug => $control ) {
				if ( isset ( $form_data->{$field_slug} ) ) {
					$form_data->{$field_slug} = $control->get_value();				
				}
			}

			// populate the last_updated elements correctly (note that, although present in the array from form, the form values are ignored in the update clase generation because readonly)
			$form_data->last_updated_by = get_current_user_id();
			$form_data->last_updated_time = current_time( 'YmdHis' );
	
			// use label for form list title
			$form_data->post_title = $form_data->issue_label;
			return array ( 	
				'response_code' => true, 
				'output' => (object) array ( 
					'message' 		=> 'Activity save/update successful. ',  
					'message_level' => 'OK', 
					'activity_id'	=>	$form_data->ID,
					'list_item'		=>	self::format_activity_list_item( $form_data ),
				) 
			);					
		}
	}


	public static function popup_delete ( $id, $dummy ) {
		$wic_access_object = WIC_DB_Access_Factory::make_a_db_access_object( 'activity' );
		$wic_access_object->delete_by_id ( $id );
		// note that a database error will generate a non-JSON error message which will intern trigger a "server error" message.
		return array ( 'response_code' => true, 'output' => '' );	
	}


/**************************************************
*
*  Activity utility functions and object properties
*
***************************************************/

	// basic option retrieval function for activity issue drop down
	public static function get_issue_options( $value ) {

		// top element in options array is always the unclassified issue -- allow one touch to unclassified
		$issues_array = array( 
			self::get_unclassified_post_array(),
		);	

		// variable tested to see if need to add an already saved activity's issue to the array
		$value_in_option_list = false;	

		// 3.4.1+ limits retrieval to open issues
		if ( ! $allowed_issues = WIC_DB_Access_Issue::get_wic_live_issues() ) {
			return $issues_array;
		}
		// assemble array		
		foreach ( $allowed_issues as $allowed_issue ) {
			if (  $allowed_issue->ID != $issues_array[0]['value'] ) { // don't dup not classified value
				$issues_array[] = array(
					'value'	=> $allowed_issue->ID,
					'label'	=>	safe_html ( $allowed_issue->post_title ),
				);
				if ( $value == $allowed_issue->ID ) {
					$value_in_option_list = true;
				}
			}
		}

		// add current value if missing
		if ( ! $value_in_option_list && $value > '' ) {
			$issues_array[] = array (
				'value'	=> $value,			
				'label'	=> WIC_DB_Access_Issue::format_title_by_id( $value ),
			);
		}		

		return ( $issues_array );
	}


	// generate upload id options -- for advanced searches on activities
	public static function upload_id( $value ) {

		// base value is blank -- cannot set 0 as search value since sqlsrv since now field is nullable
		$uploads_array = array( 
			array ( 'value' => '' , 'label' => 'No upload selection'),
		);			
		// get upload list

		global $sqlsrv;
		$sql = "SELECT ID, upload_file, upload_time from upload ORDER BY upload_time DESC";
		$uploads = $sqlsrv->query( $sql, array() );

		// assume value not found
		$value_found = false;
		// if got a list of uploads, populate the option list
		if ( is_array ( $uploads ) ) {
			foreach ( $uploads as $upload ) {
				$uploads_array[] = array ( 'value' => $upload->ID , 'label' => $upload->upload_file . ' (' . $upload->upload_time . ') ' );		
				if ( $upload->ID == $value ) {
					$value_found = true;
				}
			}
		}
		if ( false === $value_found && $value > 0 ) {
			$uploads_array[] = array ( 'value' => $value , 'label' => 'Upload history purged.' );	
		
		}
	    return ( $uploads_array );
	}

	public static function make_activity_type_filter_button() {
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-filter"></span>',
			'type'						=> 'button',
			'title'						=> 'Show/hide activity type filter.',
			'id'						=> 'filter-activities-button',			
			'name'						=> 'filter-activities-button'			
		);

		$output = WIC_Form_Parent::create_wic_form_button ( $button_args_main );

		$activity_type_control = WIC_Control_Factory::make_a_control( 'multiselect' );
		$activity_type_control->initialize_default_values(  'activity', 'activity_type', '' );
		$output .= '<div id="filter-activities-menu">' .
			$activity_type_control->form_control() 	
		.'</div>';
		return $output;
	}

	// get the id/title array for the unclassified post, create it if necessary
	public static function get_unclassified_post_array () {
		$unclassified_post_title = 'Unclassified';
		$unclassified_post_id = WIC_DB_Access_Issue::fast_id_lookup_by_title ( $unclassified_post_title ); 
		$unclassified_post_content = // formatted for viewing in text editor
'It is safe to edit the content or title of this issue, but be aware that the system will always recreate an issue with the title "' . $unclassified_post_title . '".'. 
'

The system uses the "Unclassified" issue as the default issue.

You can change the title of this issue to group unclassified activies.  For example you could change the title of this issue to "Unclassified, pre-2021".

Your old unclassified activities would show under that title, and the system would create a new "Unclassified" issue that you could use for unclassified activities going forward.

If this issue is deleted, activities assigned to it will be linked to an invalid issue and will only be retrievable through their assigned constituent.

This issue was created ' . current_time( 'YmdHis' ) . '.
		';
		if ( !$unclassified_post_id ) {
			// set up save post object
			$unclassified_post_id = WIC_DB_Access_Issue::quick_insert_post ( $unclassified_post_title, $unclassified_post_content, 'post', 'uncategorized' );
		}
		return array ( 'value' => $unclassified_post_id, 'label' => $unclassified_post_title );
	}
		
	public static function reassign_delete_activities ( $dummy, $search_object ) {
	
		/*
		* $search_object 
		*    issue => destination reassignment 
		*    searchType => type of search (advanced or issue)
		*    searchID = advanced search or issue id
		*    action = intended result -- supported action possibilities are 
		*		reassign
		*	    delete
		*/

		// set variables
		global $sqlsrv;
		$current_user = get_current_user_id();
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();		
		$issue_title = $search_object->issue ? WIC_DB_Access_Issue::get_the_title ( $search_object->issue ) : '';
		
		// create temp table (will have name $temp_table)
		WIC_List_Activity_Export::create_temp_activity_list ( $search_object->searchType, $search_object->searchID );


		// do the reassignment
		if  ( 'reassign' == $search_object->action ) {
			$result = $sqlsrv->query (
				"
				UPDATE activity 
				SET issue = ?
				FROM $temp_table t inner join activity a on t.id = a.id 
				",
				array( $search_object->issue )
			);
		} else {
			$result = $sqlsrv->query(
				"
				DELETE a FROM $temp_table t INNER JOIN activity a on t.id = a.id 
				",
				array()
			);
		}

		
		if ( !$result ) { // if no result, will not test second conditional
			return array ( 
				'response_code' => true, 
				'output' => (object) array ( 
						'reassigned' => false, 
						'message' => 
						"Possibilities include:
							<ol>
							<li>there was a database error or</li> 
							<li>activities were changed or deleted by another user.</li>
						</ol>"
					
				) 
			);
		} else {
			return array ( 
				'response_code' => true, 
				'output' => (object) array ( 
					'reassigned' => true, 
					'message' => ( 
						( 1 == $result ? "One" : $result ) .
						" activit" . 
						( $result > 1 ? "ies " : "y " ) .  
						( ( 'reassign' == $search_object->action ) ? " reassigned to issue:  $issue_title." : " permanently deleted." )
					)
				) 
			);
		}		

	}

	protected static $entity_dictionary = array(

		'activity_amount'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'float',
			'field_label' =>  'Amount',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Amount',
			'option_group' =>  '',),
		'activity_date'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'today_date',
			'field_label' =>  'Date',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'activity_note'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'activity_note',
			'field_label' =>  'Note',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  ' . . . notes . . .',
			'option_group' =>  '',),
		'activity_type'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Type',
			'option_group' =>  'activity_type_options',
			'length' =>  30,),
		'constituent_id'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '1',
			'field_type' =>  'selectmenu_constituent',
			'field_label' =>  'Constituent',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Search . . .',
			'option_group' =>  '',),
		'ID'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Internal ID for Activity',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_changed'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'issue'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu_issue',
			'field_label' =>  'Issue',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Issue',
			'option_group' =>  'get_issue_options',),
		'last_updated_by'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Updated',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'pro_con'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Pro or Con',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Pro/Con',
			'option_group' =>  'pro_con_options',),
		'upload_id'=> array(
			'entity_slug' =>  'activity',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Upload File',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'upload_id',),
	);
	
	public static $option_groups = array(
		'activity_type_options'=> array(
			array('value'=>'','label'=>'Type?',),
			array('value'=>'0','label'=>'eMail',),
			array('value'=>'1','label'=>'Call',),
			array('value'=>'2','label'=>'Petition',),
			array('value'=>'3','label'=>'Meeting',),
			array('value'=>'4','label'=>'Letter In',),
			array('value'=>'LO','label'=>'Letter Out',),
			array('value'=>'5','label'=>'Social Media Contact',),
			array('value'=>'6','label'=>'Conversion',),
			array('value'=>'7','label'=>'Case Closure',),
			array('value'=>'MO','label'=>'Member Of',),
			array('value'=>'wic_reserved_77777777','label'=>'Document',),
			array('value'=>'wic_reserved_00000000','label'=>'Email In',),
			array('value'=>'wic_reserved_99999998','label'=>'Queued email',),
			array('value'=>'wic_reserved_99999999','label'=>'Email Out',)),
		/* NOTE THAT THIS VALUE SET IS ASSUMED IN MULTIPLE PLACES IN CODE */
		  'pro_con_options'=> array(
				array('value'=>'0','label'=>'Pro',),
				array('value'=>'1','label'=>'Con',),
				array('value'=>'','label'=>'Pro/Con?',)),
		);

	public static $financial_types = array( 'MI', 'MO');

} // class