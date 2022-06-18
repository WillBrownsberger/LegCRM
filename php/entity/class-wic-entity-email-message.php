<?php
/*
*
*	wic-entity-email-message.php
*
*/
Class WIC_Entity_Email_Message {

	public static function load_message_detail ( $UID, $data ) {

		// build message object from serialized object previously built
		$message = WIC_DB_Email_Message_Object::build_from_image_uid( $UID );
		if ( false === $message ) {
			$message_output = 
				'<h3>Message deleted from server since last Inbox refresh.</h3>' .
				'<p>Possibilities include:</p>'.
				'<ol>
				<li>This was the last message on the previous page and your page refreshed before the deletion completed.</li> 
				<li>A background process (like delete or train) finished after the last inbox refresh?</li> 
				<li>You have WP Issues CRM up on another page?</li>
				<li>You deleted it through your standard mail client?</li>
				<li>You initiated an inbox reparse that has not completed?</li>
				</ol>';

			$response = (object) array (
				'assigned_constituent_display' => '',
				'assigned_constituent'		=> '', 
				'attachments_display_line'  => '',
				'from_email'				=> '',
				'incoming_message_details' 	=> '',
				'incoming_message' 			=> $message_output,
				'issue_title'				=> '',
				'issue'						=> '',
				'pro_con'					=> '',
				'recipients_display_line'	=> '',
				'sender_display_line'		=> '',
				'template'					=> '', 
			);

			return array ( 'response_code' => true, 'output' => $response ); 

		} else { 
			/*
			*
			* first set up all the incoming message content
			*
			*
			*/
			$message_output = WIC_Entity_Email_Attachment::replace_cids_with_display_url( $message->raw_html_body, $message->inbox_image_id, 0 ); // raw_html_body is always present -- created in object from text, if absent.
			$sender_display_line		= self::setup_sender_display_line ( $message );
			$recipients_display_line 	= self::setup_recipients_display_line( $message );
			$attachments_display_line 	= self::setup_attachments_display_line( $message->inbox_image_id, 0 ); 
			$reply_transition_line		= self::get_transition_line ( $message );
			/*
			*
			* second assemble parsed results
			*
			*/

			// parse message results 
			$message_details = 
				'<table class = "inbox_message_details wp-issues-crm-stats">
					<tr><td>Date:</td><td>' . $message->email_date_time . '</td></tr>
					<tr><td>Email Address:</td><td>' . $message->email_address . '</td></tr>
					<tr><td>Phone:</td><td>' . ( $message->phone_number > 0 ? $message->phone_number : '' )  . '</td></tr>
					<tr><td>First Name:</td><td>' . $message->first_name . '</td></tr>
					<tr><td>Middle Name:</td><td>' . $message->middle_name . '</td></tr>
					<tr><td>Last Name</td><td>' . $message->last_name . '</td></tr>
					<tr><td>Address:</td><td>' . $message->address_line . '</td></tr>
					<tr><td>City:</td><td>' . $message->city . '</td></tr>
					<tr><td>State:</td><td>' . $message->state . '</td></tr>
					<tr><td>Zip:</td><td>' . $message->zip . '</td></tr>
				</table>';
			/*
			*
			* third assemble findings about issue mapping
			*
			* if switching subject lines, always start fresh, otherwise, just carry through user's defined values
			* for issue/pro_con/template as scroll through messages within group
			*/
			if ( $data->switching ) {
				// reset all to blank
				$data = self::reset_issue ( $data );
				// use available data from message object only under conditions:
				if ( 
					$message->mapped_issue > 0  									// there is a mapped issue
					) {
					$data->issue 		= $message->mapped_issue;
					$data->pro_con 		= $message->mapped_pro_con;	
					$data->template 	= self::get_reply_template( $message->mapped_issue, $message->mapped_pro_con )['output'];			
				} else {
					global $current_user;
					$data->template		= '<br/>' . $current_user->get_signature();
				}
				// always switching when go to a line with inbox defined values because load_inbox will not group if user defined values from inbox
				// override with these values if they exist
				$data->issue 	= 	$message->inbox_defined_issue 		? $message->inbox_defined_issue 		: $data->issue;
				$data->pro_con 	=	( $message->inbox_defined_pro_con  || $message->inbox_defined_issue )	? $message->inbox_defined_pro_con : $data->pro_con;
				$data->template = 	$message->inbox_defined_reply_text 	? $message->inbox_defined_reply_text 	: $data->template;
				
			} 
			// validate finalized issue -- still not trashed? -- parsed email object would not know that
			// note that issue should never be falsey at this stage, but testing anyway for legacy
			// will not process second half of test if first true
			if ( ! $data->issue || ! WIC_DB_Access_Issue::fast_id_validation ( $data->issue ) ) {
				$data = self::reset_issue ( $data );
			}
			// use title look up to validate assigned_constituent
			$assigned_constituent_display = $message->assigned_constituent ? self::get_constituent_title ($message->assigned_constituent) : '';
			if ( false === $assigned_constituent_display ) { // had constituent_id, but it was bad
				$assigned_constituent_display = '';
				$message->assigned_constituent = 0;
			}
			/*
			*
			* return all to client
			*
			*/ 

			$response = (object) array (
				'assigned_constituent_display' 	=> $assigned_constituent_display,
				'assigned_constituent'			=> $message->assigned_constituent, 
				'assigned_staff'				=> $message->inbox_defined_staff,
				'reply_is_final' 				=> $message->inbox_defined_reply_is_final,
				'attachments_display_line' 	 	=> $attachments_display_line,
				'from_email'					=> $message->from_email,
				'incoming_message_details' 		=> $message_details,
				'incoming_message' 				=> $message_output,
				'issue_title'					=> $data->issue > 0 ? WIC_DB_Access_Issue::fast_title_lookup_by_id ( $data->issue ) : '',
				'issue'							=> $data->issue,
				'pro_con'						=> $data->pro_con,
				'recipients_display_line'		=> $recipients_display_line,
				'sender_display_line'			=> $sender_display_line,
				'template'						=> $data->template, 
				'to_array'						=> $message->to_array,
				'cc_array'						=> $message->cc_array,
				'clean_all_array'				=> self::construct_all_address_list( $message->to_array, $message->cc_array ),
				'reply_array'					=> array ( array ( trim( $message->first_name . ' ' . $message->last_name ), $message->email_address, $message->assigned_constituent ) ),
				'reply_transition_line'			=> $reply_transition_line,
			);
			return array ( 'response_code' => true, 'output' => $response ); 
			
		} // else have a valid message object
	}
	
	private static function reset_issue ( $data ) {
		// reset all to blank
		$data->issue 		= WIC_Entity_Activity::get_unclassified_post_array()['value']; // default to unclassified value
		$data->pro_con 		= ''; // pro con select control contains '' as unset value	
		$data->template 	= ''; // template is textarea
		return $data;
	}
	
	public static function get_transition_line ( &$message ) {
			$sent = isset ( $message->display_date ) ? ' sent ' . $message->display_date : '';
			return '<hr/>Replying to message from &lt;' . $message->email_address . '&gt;' . $sent . ':<br/><br/>'; 
	}
	
	public static function load_full_message ( $ID, $selected_page ) {
		if ( ! $message = WIC_DB_Email_Message_Object::build_from_id ( $selected_page, $ID ) ) {
			return array ( 'response_code' => false, 'output' => "Could not find $selected_page message $ID on server." ); 
		}
		// check for issue link
		global $sqlsrv;
		$activity_table = 'activity';
		$link_field = 'done' == $selected_page ? ' related_inbox_image_record ' : ' related_outbox_record ';
		// with renumbering of outbox on a conversion, there may exist duplicative pointers -- in this function, taking most recent will commonly by right
		// worst case is get pointer to wrong issue from constituent from found message, but older messages will not be available, so this will almost always be right
		$results = $sqlsrv->query ( "SELECT TOP 1 constituent_id, issue FROM $activity_table WHERE $link_field = ? and OFFICE = ? ORDER BY activity_date DESC",
			array( $ID, get_office() ) );
		if ( isset ( $results[0] ) ) {
			$constituent	= $results[0]->constituent_id;
			$issue 		 	= $results[0]->issue;
		} else {
			$constituent = 0;
			$issue = 0;
		}
		$response = (object) array (
			'attachments_display_line' 	 	=> self::setup_attachments_display_line( $message->message_id, $message->outbox ), 
			'message' 						=> WIC_Entity_Email_Attachment::replace_cids_with_display_url( 'done' == $selected_page ? $message->raw_html_body : $message->html_body, $message->message_id, $message->outbox ),
			'recipients_display_line'		=> self::setup_recipients_display_line( $message ), // includes from if present
			'constituent_link'				=> ( $constituent ?	'<a target = "_blank" title="Open constituent in new window." 	href="' . WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=' . $constituent . '">View constituent</a>' : 'Constituent not classified.' ),
			'issue_link'					=> ( $issue ? 		'<a target = "_blank" title="Open issue in new window." 		href="' . WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . $issue . '">View Issue</a>' : 'Issue not classified.' ),
		);
		return array ( 'response_code' => true, 'output' => $response ); 
		
	}
	
	// construct unique list excluding own email addresses (incoming from and and reply to) from two arrays
	public static function construct_all_address_list ( $to_array, $cc_array ) {

		$merged_clean_array = array();
		$used_addresses = array();
		$my_mails = array ( 
			WIC_Entity_Office::get_office_email(),
		);	

		foreach ( $to_array as $to_address ) {
			if ( !in_array ( $to_address[1], $used_addresses ) &&  !in_array ( strtolower($to_address[1]), $my_mails) ) {
				$to_address[0] = safe_html( $to_address[0]);
				array_push ( $merged_clean_array, $to_address );
			}
			array_push( $used_addresses, $to_address[1] );
		}
	
		foreach ( $cc_array as $cc_address ) { 
			if ( !in_array ( $cc_address[1], $used_addresses ) &&  !in_array ( strtolower($cc_address[1]), $my_mails) ) {
				$cc_address[0] = safe_html( $cc_address[0]);
				array_push ( $merged_clean_array, $cc_address );
			}
			array_push( $used_addresses, $cc_address[1] );
		}
		return $merged_clean_array; 
	}

	/*
	*
	* save/load reply templates
	*
	*/
	// always call this function for save or update of template -- existence check built in
	public static function save_update_reply_template ( $issue, $data ) {
		/*
		* $data object has three properties
		*	pro_con_value ( OK if empty );
		*	template_title
		*   template_content
		*
		*/
		global $sqlsrv;
		$post_table = 'issue';
		$pro_con_value = $data->pro_con_value ;
		$title =  $data->template_title ;
		$content =$data->template_content ;
		// does the template already exist for the issue pro_con value combination
		$reply_id = WIC_DB_Access_Issue::does_reply_exist ( $issue, $pro_con_value );
			// false return indicates bad issue or pro_con_value
			if ( false === $reply_id ) {
				return array ( 'response_code' => false, 'output' => 'Badly formed attempt to save reply; issue or pro_con_value was invalid.'  );
			}
		if ( $content ) {
			// if template does not yet exist, add the template, get the id and update link
			if ( 0 == $reply_id ) { // 0 means good call, but doesn't exist yet
				$reply_id = WIC_DB_Access_Issue::quick_insert_post(  $title, $content, 'wic_reply_template' );
				if ( $reply_id ) {
					if ( ! WIC_DB_Access_Issue::quick_add_cross_link ( $issue, $reply_id, $pro_con_value ) ) {
						return array ( 'response_code' => false, 'output' => 'Error saving link   for template.'  );
					}
				} else {
					return array ( 'response_code' => false, 'output' => 'Error saving template.'  );
				}
			// if it does exist, just update it
			} else {
				WIC_DB_Access_Issue::quick_update_template ( $reply_id, $title, $content );
				// not doing change checking, so can't test return for false -- continue -- catchable error by user 
			}
			return array ( 'response_code' => true, 'output' => 'Template saved/update OK.'  );
		// sent a blank template, delete post if it exists
		} else {
			$result = true;
			if ( '' !== $reply_id ) {
				$result = WIC_DB_Access_Issue::quick_delete_post_and_unlink (  $issue, $reply_id, $pro_con_value );
			}
			if ( $result ) {
				return array ( 'response_code' => true, 'output' => 'Template deleted.'  );
			} else {
				return array ( 'response_code' => false, 'output' => 'Database error deleting template.'  );
			}
		}	

	}
	/*
	*
	* retrieve set template for issue/pro_con
	* WIC_DB_Access_Issue::create_reply_field_name ( int $issue, $pro_con_value )
	*/
	public static function get_reply_template ( $issue, $pro_con_value ) {
		global $sqlsrv;
		$link_field = WIC_DB_Access_Issue::create_reply_field_name ( $pro_con_value );
		if ( !$link_field ) {
			return array ( 'response_code' => false, 'output' => 'Bad request for template.'  );
		}

		$result = $sqlsrv->query(
			"SELECT p2.post_content as template
			FROM issue p
			INNER JOIN issue p2 on p2.id = p.$link_field
			WHERE p.id = ? and p2.office = ?",
			array(
				$issue,
				get_office()
			)
		);

		if ( ! $result ) {
			$template = '';
		} else {
			$template = $result[0]->template;
		}

		return array ( 'response_code' => true, 'output' => $template  );
	}

	/*
	*
	* delete set template for issue/pro_con
	*
	*/
	public static function delete_reply_template ( $issue, $pro_con_value ) {

		$link_field = WIC_DB_Access_Issue::create_reply_field_name ( $pro_con_value );
		if ( !$link_field ) {
			return array ( 'response_code' => false, 'output' => 'Bad request to delete template.'  );
		}

		global $sqlsrv;

		$result1 = $sqlsrv->query(
			"
			DELETE FROM p2
			FROM issue p 
			INNER JOIN issue p2 on p2.id = p.$link_field
			WHERE p.id = ? AND p2.OFFICE = ?
			",
			array(
				$issue,
				get_office()
			)
		);

		if ( ! ( $result1 ) ) {
			return array ( 'response_code' => true, 'output' => array ( 'success' => false, 'message'=>'Reply likely already deleted or database error.' )  );
		}

		$result2 = $sqlsrv->query(
			"
			UPDATE issue SET $link_field = 0 
			WHERE id = ? AND OFFICE = ?
			",
			array(
				$issue,
				get_office()
			)
		);

		if ( ! ( $result2 ) ) {
			return array ( 'response_code' => true, 'output' => array ( 'success' => false, 'message'=>'Reply likely already deleted or database error.' )  );
		}

		return array ( 'response_code' => true,'output' => array ( 'success' => true, 'message'=>'Reply deleted.' )    );
	}

	/*
	*
	* new issue from message content
	*
	*/
	public static function new_issue_from_message ( $uid, $data ) {
		$notice = '';
		$message = WIC_DB_Email_Message_Object::build_from_image_uid($uid);
		if ( false === $message ) {
			return array ( 'response_code' => false, 'output' => 'Message was deleted from server since last Inbox refresh' ); 
		} else {
			if ( $insert_id = WIC_DB_Access_Issue::fast_id_lookup_by_title ( $message->subject ) ) {
				$notice = '<p><strong>Issue already exists with title:</strong></p><p>"' . $message->subject . '".</p><p>No new issue created.</p>'; 
			} else {
				$insert_id = WIC_DB_Access_Issue::quick_insert_post ( $message->subject,  $message->raw_html_body, 'post',  );
				
				if ( ! $insert_id ) {
					return array ( 'response_code' => false, 'output' => 'Error creating new issue' ); 
				}	
				
				$notice = '<p><strong>New private issue created with title:</strong></p><p>"' . $message->subject . '".</p>'; 
			}
			$notice .= '<p><a target="_blank" href="' .  WIC_DB_Access_Issue::get_issue_edit_link( $insert_id ) .'">Edit issue</a></p>';

			return array ( 'response_code' => true, 'output' => array ( 'value' => $insert_id, 'label' => $message->subject, 'notice' => $notice )  ); 
			
		} // else have a valid message object
	}
	
	// use straight wordpress call to populate issue peek popup
	public static function get_post_info ( $issue, $dummy ) {
		global $current_user;
		$post_object = WIC_DB_Access_Issue::get_post_details ( (int) $issue ); 
		$templated_pro_con_array = WIC_DB_Access_Issue::get_array_of_reply_values ( $issue );
		
		if ( $current_user->current_user_authorized_to ( 'all_crm' ) || $current_user->current_user_authorized_to ('all_email' ) ) {
			$content =  $post_object ? simple_autop( utf8_string_no_tags( $post_object->post_content ) ) : '<h3>Issue trashed or deleted since assignment.</h3>' ;
		} else {
			$content = '<p>Consult your supervisor for access to the full content of this issue.  Your current user role does not have access to issues not assigned to you.</p>';
		}
		return array ( 'response_code' => true, 'output' => (object) array ( 'content' => $content, 'templated_pro_con_array' => $templated_pro_con_array ) ); 
	}


	private static function setup_sender_display_line( $message ) {
		// message sender line for display
		$sender_display_line = 
			isset ( $message->reply_to_personal ) ? 
				(
				$message->reply_to_personal ? 
					$message->reply_to_personal : 
					( isset ( $message->reply_to_email ) ? $message->reply_to_email : '' )
				) :
				''
			; 
		$sender_display_line = $sender_display_line ? $sender_display_line : 
			(
			isset ( $message->from_personal ) ? 
				(
				$message->from_personal ? 
					$message->from_personal : 
					( isset ( $message->from_email ) ? $message->from_email : '' )
				) :
				''
			)
			; 
		return $sender_display_line;
	}
		
	public static function setup_recipients_display_line( &$message ) {
		$recipients_display_line = '<table id="to-from-reply-table" >';
		$recipients_display_line .= '<tr><td>Subject: </td><td>' . $message->subject . '</td></tr>';
		$recipients_display_line .= '<tr><td>Dated: </td><td>' . $message->display_date . '</td></tr>';
		if ( !empty(  $message->from_email  )  ) {
			$recipients_display_line .= self::address_array_to_table_row ( array ( array( $message->from_personal,  $message->from_email ) ), 'From' );
		} 
		if ( !empty( $message->reply_to_email ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( array ( array( $message->reply_to_personal,  $message->reply_to_email ) ), 'Reply' );
		}
		if ( !empty( $message->to_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->to_array, 'To' );
		} else {
			$recipients_display_line .= '<tr><td>To: </td><td></td></tr>'; // always include a to line
		}
		if ( !empty ($message->cc_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->cc_array, 'Cc' );
		}
		if ( !empty( $message->bcc_array ) ) {
			$recipients_display_line .= self::address_array_to_table_row ( $message->bcc_array, 'Bcc' );
		}
		$recipients_display_line .= '</table>';
		return $recipients_display_line;
	}

	public static function setup_attachments_display_line( $message_id, $message_in_outbox ) {
	
		$attachments = WIC_Entity_Email_Attachment::get_message_attachments ( $message_id, $message_in_outbox );
		// this function can be called without knowing if have attachments
		if ( ! $attachments ) {
			return '';
		} 
		// now subselect attachments with disposition == attachment
		$attachments_disposed_as_attachments = array();
		foreach ( $attachments as $attachment ) {
			if ( $attachment->message_attachment_disposition != 'inline' ) {
				$attachments_disposed_as_attachments[] = $attachment;
			}
		}
		if ( ! $attachments_disposed_as_attachments ) {
			return '';
		} 		

		// now construct list of real attahments
		$attachments_display_line = '<p>Attachments:</p><ol id="msg_attachments_list">';
		foreach ( $attachments_disposed_as_attachments as $attachment ) {
			$attachments_display_line .= 
				'<li><a href="' . WIC_Entity_Email_Attachment::construct_attachment_url( $message_id, $attachment->attachment_id, $message_in_outbox ) . '" target = "_blank">' .
					$attachment->message_attachment_filename . ' (' . $attachment->attachment_size . ' bytes)' .
				'</a></li>';
		}	
		$attachments_display_line .= '</ol>';
		return $attachments_display_line; 
	}
	
	private static function address_array_to_table_row ( $address_array, $type ) {
		$string = '';
		$first = true;
		foreach ( $address_array as $address_line ) {
				$string .= '<tr>
					<td>' . ( $first ? $type .': ' : '' ) . '</td>
					<td>' . ( $address_line[0] ? ( $address_line[0] . ' ' ) : '' ) . '&lt;' . $address_line[1] . '&gt;</td>
				</tr>';	
				$first = false;
		}
		return $string;
	}

	private static function get_constituent_title ( $constituent_id ) {
		global $sqlsrv;
		$sqlsrv->query( 'select dbo.getConstituentAll(id) as c_title from constituent where id = ?', array( $constituent_id ) );
		if ( $sqlsrv->success && $sqlsrv->num_rows ) {
			return $sqlsrv->last_result[0]->c_title;
		} else {
			return false;  // not found constituent, $constituent_id was bad
		}
	}

	public static function quick_update_constituent_id ( $folder_uid, $data ) {
		global $sqlsrv;
		$inbox_image_table = 'inbox_image';
		$result = $sqlsrv->query (
			"UPDATE $inbox_image_table SET assigned_constituent = ? WHERE folder_uid = ? AND OFFICE = ?",
			array ( $data->assigned_constituent, $folder_uid, get_office() )
		);
		$response_code = ( $result !== false );
		$output = (object) array ( 
			'output' => $response_code ? 'Assigned constituent update OK.': 'Database error on assigned constituent update.',
			'constituent_name' => ( $response_code && $data->assigned_constituent ) ? WIC_DB_Access_WIC::get_constituent_name( $data->assigned_constituent ) : ''
		); 
		return array ( 'response_code' => $response_code, 'output' => $output ); 
	} 

	public static function quick_update_inbox_defined_item( $folder_uid, $data ) {
	
		global $sqlsrv;
		$inbox_image_table = 'inbox_image';
		// slug sanitize $field_to_update
		$field_to_update = 'inbox_defined_' . slug_sanitizor($data->field_to_update);

		$result = $sqlsrv->query (
			"UPDATE $inbox_image_table SET $field_to_update = ? WHERE folder_uid = ? AND OFFICE = ? ",
			array ( $data->field_value, $folder_uid, get_office() )
		);

		$response_code = ( $result !== false );
		$output = (object) array ( 
			'output' => $response_code ? 'Update OK.': "Database error on $field_to_update update.",
		); 
		return array ( 'response_code' => $response_code, 'output' => $output ); 	
	
	}

	
}