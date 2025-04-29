<?php
/*
*
*	wic-entity-email-send.php
*
*
*   note that class functions that return ajax response to the composeWindow return true with a text error string;
*		compose window interprets lack of an object in the response as identifying an error
*
*   update_draft returns false on an error becuase the composeWindow is already closed on the client and the error will be intercepted by ajax.js 
*
*
*/
class WIC_Entity_Email_Send {

	const body_break_string = "\n\n-------------------\n\n";
	const dear_token = '|||*Dear*|||'; // note that if alter this, must also alter regular expression pattern in replace_dear_token

	// utility to clean up outgoing html for messages
	public static function filter_rough_html_for_output( $html ) {
		/*
		*  notes re filtering of final message content
		*
		*  note that safe_html not an option since escapes html characters -- can't use for html output
		*  utf8_string_no_tags strips tags entirely -- can't use for html output
		*  kses_post is too strict on image content -- does not pass cid.
		*  the_content filter does the following by default -- it does not sanitize
		* 	'wptexturize' -- does smart quotes, elipses, dashes, etc.                   
		* 	'convert_smilies' -- converts ;) to images                  
		* 	'wpautop' -- convert linebreaks to p and br markers;                         
		* 	'shortcode_unautop' -- unwraps shortcodes from autop                
		* 	'prepend_attachment' -- wrap attachement in paragraph code before content               
		* 	'wp_make_content_images_responsive' -- images
		*  SO: repeat strip_html_head (invoked on incoming html )to protect against any bad material introduced in template or signature
		*/
		return WIC_DB_Email_Message_Object::strip_html_head ( $html );	
	}

	/*
	* Similar filtering for text as for html, but remove tags and entities, not the reverse, don't add html
	*
	* have to assume that incoming message body could have arbitrary html and, if contained in message, likely to not parse properly with regex,
	* so keep it simple: http://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags
	* -- only attempt to parse out links; would like to be able to include the same set of links; may affect deliverability if parts do not correspond
	* 
	* link parsing approach derived from http://www.the-art-of-web.com/php/parse-links/
	*/
	public static function filter_rough_html_to_text ( $html ) {
		global $wic_inbox_image_collation_takes_high_plane;
		$link_regex = "/<a\s[^>]*href\s*=\s*([\"\']??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU";
		$first_pass = html_entity_decode (						// decode html entities
			strip_tags( 							// remove other tags
				preg_replace( 						// put in line breaks in lieu of html breaks and paragaraphs
					'#(?i)\s*<\s*/?\s*(?:br|div|p|blockquote|address|center|h\d)\b[^>]*>\s*#', // don't need array since single result, 
					"\n",							// since tinymce (3.3) -- tinymce follows p with \n (so one \n is enough for paragraph boundary), 
					preg_replace ( 					// put links as plain text in parens after their text 
						$link_regex, 
						' \\0 ( \\2 )',
						WIC_DB_Email_Message_Object::strip_html_head (				// redo in case of problems introduced in template
							$html  				
						)
					)
				)
			),
			0,
			'UTF-8' 								// decode to utf-8
		);
		if ( $wic_inbox_image_collation_takes_high_plane ) {
			return $first_pass;
		} else {
			return 	preg_replace(									// discard any high plane UTF-8 characters coming of out of the decode
				'/[\x{10000}-\x{10FFFF}]/u', 
				"\xEF\xBF\xBD",
				$first_pass	
			);
		}		
	}

	// takes WIC_DB_Email_Outgoing_Object, applies filters and saves, returning new outbox id or false
	private static function save_message_to_queue ( $message_object ) {
		global $sqlsrv;

		// do final security filter to outgoing
		$message_object->html_body = self::filter_rough_html_for_output ( $message_object->html_body );
		$message_object->text_body = self::filter_rough_html_to_text ( $message_object->text_body );

		// serialize message object and to_address_concat ; no further use of message object in standard CRM form
		$serialized_outgoing_object = serialize ( $message_object ); 
		$to_address_concat = self::to_address_concat ( $message_object );
		$json_outgoing_object = self::standardize_json_message( $message_object);
		
		$save_result = $sqlsrv->query (
			"INSERT INTO outbox 
				(is_draft,
				is_reply_to,
				subject,
				queued_date_time,
				serialized_email_object,
				json_email_object,
				to_address_concat,
				office,
				sent_time_stamp,
				sent_ok, 
				held,
				sent_date_time
				) VALUES
				(?,?,?,?,?,?,?,?,0,0,0,'')
			",
			array ( 
				$message_object->is_draft,
				$message_object->is_reply_to,
				substr($message_object->subject,0,400),
				current_time( 'YmdHis' ),
				$serialized_outgoing_object,
				$json_outgoing_object,
				$to_address_concat,
				get_office()
			)
		);
		$outgoing_message_id = $sqlsrv->insert_id;
	
		if ( $save_result ) {
			return $outgoing_message_id;
		} else {
			return false;
		}
	}

	private static function address_array_to_object ( $address_array ) {
		$address_object = (object) array();
		$address_object->Name = $address_array[0];
		$address_object->Address = $address_array[1];
		$address_object->ConstituentID = $address_array[2];
		return $address_object;
	}

	private static function standardize_json_message ( $message_object ) {

		// need to preserve the original object for downstream use, so:
		$clone_of_message_object = clone $message_object;

		// transform the address arrays to arrays of objects instead of arrays of arrays 
		// can't mix types in a c# array
		foreach( $clone_of_message_object->to_array as &$address_array ) {
			$address_array = self::address_array_to_object ( $address_array );
		}
		foreach( $clone_of_message_object->cc_array as &$address_array_cc ) {
			$address_array_cc = self::address_array_to_object ( $address_array_cc );
		}
		foreach( $clone_of_message_object->bcc_array as &$address_array_bcc ) {
			$address_array_bcc = self::address_array_to_object ( $address_array_bcc );
		}

		// assure that all ints are actually ints
		$clone_of_message_object->is_draft = intval($clone_of_message_object->is_draft );
		$clone_of_message_object->is_reply_to = intval($clone_of_message_object->is_reply_to);
		$clone_of_message_object->issue = intval($clone_of_message_object->issue );
		$clone_of_message_object->search_id = intval($clone_of_message_object->search_id );
		$clone_of_message_object->draft_id = intval($clone_of_message_object->draft_id );

		// message object in json form with objects that will work with C# (array cannot mix types);
		return json_encode( $clone_of_message_object );
	}

	/*
	*
	* takes WIC_DB_Email_Outgoing_Object, invokes save and further saves activity records
	*
	* invoked by the compose window only for the activity save
	*
	*/
	public static function queue_message ( $message_object, $draft_already_exists ) {

		$activity_table = "activity";
		
		if ( ! $draft_already_exists ) {
			$outgoing_message_id = self::save_message_to_queue ( $message_object );
		} else {
			$outgoing_message_id = $message_object->draft_id;
		}
		// proceed to save attachments in reply case and create activity records if successful.
		if ( $outgoing_message_id ) {
			/*
			* note on why $old_message_in_outbox is 0 in call to link_original_attachments:
			*	current method (queue_message) is invoked 
			*		by main reply dialog (WIC_Entity_Email_Process::handle_inbox_action_requests) with $draft_already_exists == false
			*			in this case, we are replying to a message in the inbox, so $old_message_in_outbox should be 0
			*		by self::update_draft when updating draft to non-draft (i.e., sending) with $draft_already_exists == true
			*			in this case, link_original_attachments is not invoked (because attachments were linked on draft creation)
			*		in self::email_to_list_of_constituents with $draft_already_exists == false (queuing individual messages)
			*			in this case, is_reply_to and include_attachments are both falsy, so link_original_attachments not invoked
			*/
			if ( $message_object->is_reply_to && $message_object->include_attachments && !$draft_already_exists ) {
				WIC_Entity_Email_Attachment::link_original_attachments ( $message_object->is_reply_to, $outgoing_message_id, 0 );
				// last parameter is $old_message_in_outbox -- in this branch, always  0 since replying
			}
			/*
			*
			* do activity_record_inserts including constituent creeation
			*
			*/
			// consolidate outgoing email addresses into single array
			$all_outgoing = array_merge ( $message_object->to_array, $message_object->cc_array, $message_object->bcc_array );

			foreach ( $all_outgoing as $constituent ) {
				$names = preg_split ( "# #", $constituent[0], 2, PREG_SPLIT_NO_EMPTY );
				$save_first_name = isset( $names[0] ) ? ucfirst( strtolower( $names[0] ) ) : '';
				$save_last_name = isset( $names[1] ) ? ucfirst( strtolower( $names[1] ) ) : '';
				// set or unset preset constituent id
				if ( $constituent[2] ) {
					$save_constituent_id = $constituent[2];  
				} else {
					$save_constituent_id = 0;
				}

				// set up object for display 
				$temp_message = (object) array (
					'subject'  => $message_object->subject,
					'display_date' => current_time( 'YmdHis' ),
					'to_array' => $message_object->to_array,
					'cc_array' => $message_object->cc_array,
					'bcc_array' => $message_object->bcc_array,
					'message_id'  => $outgoing_message_id ,
				);
				$save_activity_note = 		
					'<div id="recipients-display-line">' . 
						WIC_Entity_Email_Message::setup_recipients_display_line ( $temp_message ) .
					'</div>' .
					'<div id="attachments-display-line">' . 
						WIC_Entity_Email_Message::setup_attachments_display_line( $outgoing_message_id, 1 ) . 
					'</div>' .
					'<div id="inbox_message_text">' . 
						WIC_Entity_Email_Attachment::replace_cids_with_display_url( $message_object->html_body, $outgoing_message_id, 1  ); 
					'</div>';				
				
				global $sqlsrv;
				$sqlsrv->query(
					"
					EXECUTE saveConstituentActivity
						?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
					" // 19 parameters
					,
                    array(
                        // dropped utf_encode for 8.4
                        get_office(), //@OFFICE smallint,
                        $constituent[1], // @emailAddress varchar(200),
                        $save_first_name, // @firstName varchar(50),
                        $save_last_name, // @lastName varchar(50),
                        '', // @addressLine varchar(100),
                        '', // @city varchar(50),-- not in lookup
                        '', // @state varchar(50), -- not in lookup
                        '', // @zip varchar(10),
                        '', // @phone
                        $save_constituent_id ,  // @constituentId bigint,
                        1,  // @in_or_out bit,
                        $message_object->pro_con, // @pro_con varchar(1)
                        $message_object->issue, // @issue int
                        current_time("Y-m-d"), // @activity_date datetime2(0),
                        $save_activity_note, // @activity_note varchar(max)
                        '', // @is_my_constituent varchar(1)
                        0, // @related_inbox_image_record  int,
                        $outgoing_message_id, // @related_outbox_record int
                        get_current_user_id() // @current_user_id int
                    )
				);
				if ( 
					! $sqlsrv->success || // some exceptions are caught, so may show success on failure
					!isset( $sqlsrv->last_result[0] ) || // should always be present unless constituent_id was zero
					0 == $sqlsrv->last_result[0]->activity_id // reset on rollback transaction, belt and suspenders
					) {  
					error_log ( 'Database error saving outgoing message constituent activity record.');
				}
				// $result only logged -- overriding goal is to get the message out.
			}

			$response_code = true;
		} else {		
			$response_code = false;
		}

		return array ( 
			'response_code' => $response_code, 
			'output' => $response_code ? 
				'Queued reply OK.' : 'Database error queuing_reply -- message recorded but left in outbox.'
			); 
	} 

	// sends standard reply to address in $email_object
	public static function send_standard_reply ( $email_object ) {

		// get parameters
		$form_variables_object =  WIC_Entity_Email_Process::get_processing_options()['output'];
		$non_constituent_response_subject_line 	= $form_variables_object->non_constituent_response_subject_line;
		$non_constituent_response_message 		= $form_variables_object->non_constituent_response_message ;

		$html_body = $non_constituent_response_message . WIC_Entity_Email_Message::get_transition_line ( $email_object ) . $email_object->raw_html_body;
		// using html for text body, most complete message body (raw_text is only first part) and willbe stripped to text  in save_message_to_queue;
		$text_body = $non_constituent_response_message .  self::body_break_string . WIC_Entity_Email_Message::get_transition_line ( $email_object ) . $email_object->raw_html_body; 
		// construct send object
		$to_array  = array( array( $email_object->first_name . ' ' . $email_object->last_name, $email_object->email_address, 0 ) );
		$addresses = array( 'to_array' => $to_array );
		$outgoing_message_object =	new WIC_DB_Email_Outgoing_Object (
			$addresses, 
			$non_constituent_response_subject_line,
			$html_body, 
			$text_body,
			0, 								// not is_draft
			$email_object->inbox_image_id, 	// is_reply_to
			false,							// no attachment
			0,								// not assigning issue
			''								// not assigning pro_con
		);
		
		// directly invoke save_message_to_queue because do not want to save constituent or activity records
		return self::save_message_to_queue ( $outgoing_message_object );
	}

	// delete message and related activity record(s if cc)
	public static function delete_message_from_send_queue ( $dummy, $id ) {

		global $sqlsrv;

		// delete the queued id
		$sql = "DELETE FROM o FROM outbox o WHERE o.ID = ?"; 
		$response1 = $sqlsrv->query ( $sql, array( $id ) );
		// delete activity record that relate to queued id	
		$sql = "DELETE a FROM activity a WHERE a.related_outbox_record = ?"; 
		$response2 = $sqlsrv->query ( $sql, array( $id ) );

		$response_code = $response1 && $response2;

		return array ( 	'response_code' => $response_code, 
						'output' =>  
							$response_code 
							? 
							"Message deleted OK"
							: 
							"Database error in deletion of message.",
		);	

	}
	
	// deletes messages and related activity records; $id is dummy in this function 
	public static function purge_mail_queue ( $dummy, $id ) {

		global $sqlsrv;
		$activity_table = "activity";


		// delete the related activity records first
		$sql = "DELETE from a FROM outbox o LEFT JOIN $activity_table a on a.related_outbox_record = o.ID WHERE o.sent_ok = 0 AND o.is_draft = 0 and a.office = ?"; 
		$response1 = $sqlsrv->query ( $sql, array( get_office()) );		
		// delete the related outbox records 
		$sql = "DELETE FROM o FROM outbox o WHERE o.sent_ok = 0 AND o.is_draft = 0 and o.office = ?"; 
		$response2 = $sqlsrv->query ( $sql, array( get_office()) );	
	
		$response_code = (false !== $response1) && (false!== $response2);

		return array ( 	'response_code' => $response_code, 
						'output' =>  
							$response_code 
							? 
							"Outbox purged OK"
							: 
							"Database error in outbox purge.",
		);	

	}

	/*
	*
	* functions supporting message composition
	*	prepare_compose_dialog is invoked when composition is requested and begins by saving a shell outgoing object marked as draft
	*		sending the message is just updating the draft status
	*	error messages on prepare or returning to the compose window always have 'true' response_code; a plain text response is interpreted as error
	*	error messages on updates (window is closing) return false on error for interception by ajax.js
	*
	*/
	public static function prepare_compose_dialog ( $dummy, $compose_request ) {
		// incoming variable defaults
		$context 	= 'new'; 	// context of message composition request
		$id  		= 0;  		// email_id, message_id or search_id	
		$parm		= 0;  		// unused, list_page or constituent count
		// object with same three terms
		extract ( ( array ) $compose_request, EXTR_OVERWRITE );
		/*
		* to handle possibilities
		*	new/0/0 				-- compose button
		*	mailto/email_id/0 		-- email to constituent
		*   reply, reply_all or forward /message_id/page -- from message view popup
		*	email_send ( or show_map_email_send)/search_id/constituent_count -- from advanced search constituent list
		*	issue_activity_list_email_send (or show_issue_map_email_send)/search_id/constituent_count -- from activity list for issue
		*/
		global $current_user;
		$current_user_sig =$current_user->get_signature();
		$outgoing_object = new WIC_DB_Email_Outgoing_Object ( 
			array(), 	// $addresses
			'', 	 	// $subject
			'<br/>' . $current_user_sig,	// $html_body
			'',			// $text_body will be derived from output of editing process
			1, 			// $is_draft
			0,			// $is_reply_to
			false,		// $include_attachments 
			0,			// $issue 
			'',			// $pro_con
			$context,	// $search_type  
			$id,		// $search_id 
			$parm		// $search_parm 
		);
		// for forwarding, need to pass parameter identifying location of message to pick attachments from; 
		// default is inbox
		$old_message_in_outbox = 0;

		/*
		*  add in body material for reply/forward messages
		*  note that text_body must be entirely derived from html body when sending since
		*     reply html can be edited
		*  need to differentiate 'done' page which uses the incoming object from 'outbox' and 'sent' pages which use the outgoing object
		*/
		if ( in_array ( $context, array ( 'reply', 'reply_all', 'forward') ) ) {
			// set is_reply_to for all of these categories
			$outgoing_object->is_reply_to = $id;
			// get original message and handle error
			$original_message = WIC_DB_Email_Message_Object::build_from_id( $parm, $id ); // this function is page sensitive, so can be invoked for all three history lists
			if ( ! $original_message ) {
				return array ( 'response_code' => true, 'output' => 'The message you seek to reply/forward appears to already have been deleted.' );
			}
			// re processing message already viewed
			if ( 'done' == $parm ) {
				$reply_to = array ( trim( $original_message->first_name . ' ' . $original_message->last_name ), $original_message->email_address, $original_message->assigned_constituent );
				$outgoing_object->html_body .=  WIC_Entity_Email_Message::get_transition_line ( $original_message ) . $original_message->raw_html_body;
				// $old_message_in_outbox defaulted already to zero
			// further reacting to messages self sent
			} else {
				$outgoing_object->html_body .=  '<br/><hr/><br/>' . $original_message->html_body;
				$old_message_in_outbox = 1;
			}
			
			$reply_subject = ( "re:" == strtolower( substr( $original_message->subject, 0, 3) ) ) ? $original_message->subject : ( "Re: " . $original_message->subject );
		}
		$search_link = '';
		// set addresses and include attachments switch
		switch ( $context ) {
			case 'new':
				break; 
			case 'mailto':
				$outgoing_object->to_array = WIC_Entity_Email::get_email_address_array_from_id ( $id );
				// check for bad calls
				if ( ! $outgoing_object->to_array ) {
					return array ( 'response_code' => true, 'output' => 'Bad or missing email_address.  To compose a new message, use the main email function.' );
				}
				break;
			case 'reply':
				$outgoing_object->subject = $reply_subject;
				if ( 'done' == $parm) {
					// properties updated from assigned constituent
					$outgoing_object->to_array = array ( $reply_to );
				} else {
					$outgoing_object->to_array = $original_message->to_array;
				}
				break;
			case 'reply_all':
				$outgoing_object->subject = $reply_subject;
				if ( 'done' == $parm) {
					// properties updated from assigned constituent
					$outgoing_object->to_array = array ( $reply_to );
					$outgoing_object->cc_array = WIC_Entity_Email_Message::construct_all_address_list ( 
						$original_message->to_array, 
						$original_message->cc_array 
					); // merge unique minus own emails
				} else {
					$outgoing_object->to_array = $original_message->to_array;
					$outgoing_object->cc_array = $original_message->cc_array;
				}			
				break;
			case 'forward':
				$outgoing_object->subject = "Fwd: " . $original_message->subject;
				$outgoing_object->include_attachments = true;
				break;
			case 'show_map_email_send':
			case 'show_issue_map_email_send':
			case 'email_send':
			case 'issue_activity_list_email_send':
				$search_link_result = self::search_link ( $outgoing_object );
				if ( false === $search_link_result['response_code'] ) {
					// false return at this stage should yield a true ajax return -- compose window detects bad return based on object presence (string is an error message)
					$search_link_result['response_code'] = true;
					return $search_link_result;
				} else {
					$search_link = $search_link_result['output'];
				}
				break;
		}

		// save draft -- need to carry the id in the object for future reference
		$outgoing_object->draft_id = self::save_message_to_queue ( $outgoing_object );
		if ( ! $outgoing_object->draft_id ) {
			// note that must repopulate this at draft retrieval if draft is not saved initially -- see load_draft
			return array ( 'response_code' => true, 'output' => 'Database error initiating message composition.  Likely a transient server problem.' );
		}

		/*
		* link original attachments in case of message forward ( in case of new outgoing, linked to draft as saved )
		*/
		if ( $outgoing_object->is_reply_to && $outgoing_object->include_attachments ) {
			WIC_Entity_Email_Attachment::link_original_attachments ( $outgoing_object->is_reply_to, $outgoing_object->draft_id, $old_message_in_outbox );
		}

		// also append send form
		ob_start();
		$compose_entity = new WIC_Entity_Email_Compose ( 'new_blank_form', array (
			 'search_link' => $search_link, 
			 'draft_id' => $outgoing_object->draft_id 
		) );
		$form = ob_get_clean();
		
		return array ( 'response_code' => true, 'output' => ( object ) array( 'object' => $outgoing_object, 'form' => $form ) );
	
	}

	/*
	* 
	* this private function returns false response code if returning an error to class functions in same format as going to an ajax return
	*
	* HOWEVER, directly using class functions will flip the code to true before returning it to compose window
	*
	*/
	private static function search_link ( $outgoing_object ) { 
		global $current_user;
		// only applicable to list sends and map list sends 
		if ( ! ( 'email_send' === substr( $outgoing_object->search_type, - 10 ) ) ) { // excludes 'mailto' on load_draft . . .
			return array ( 'response_code' => true, 'output' => '' );
		}
		// return error if user does not have authority to send -- possible only if security rules changed after was shown list
		$required_capability = 'all_email'; 
		if ( ! $current_user->current_user_authorized_to( $required_capability ) ) {
			return array ( 'response_code' => false, 'output' => "Contact your site administrator to upgrade your role to allow list sending.");
		}

		// check count from search 
		$temp_table = WIC_List_Constituent_Export::do_constituent_download ( $outgoing_object->search_type, $outgoing_object->search_id );
		// read one from the temp file and get the count of the temp file (constituent selection narrowed to those with emails)
		$count_temp= WIC_List_Constituent_Export::get_temp_table_count( $temp_table );	

		// if none, error
		if ( ! $count_temp ) {
			return array ( 'response_code' => false, 'output' => "None of the selected constituents have email addresses recorded." );
		}

		// if exceed max count error
		$applicable_max_count = 1000;
		if ( defined ( 'WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND' ) ) {
			$applicable_max_count = WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND;
		} 
		$max_count_OK = ( $count_temp <= $applicable_max_count );
		if ( !$max_count_OK ) {
			return array ( 
				'response_code' => false, 
				'output' => 
					"This selection of constituents contains $count_temp email addresses. " .  
					"This count exceeds $applicable_max_count, the bulk send limit for this installation. " .   
					"An administrator can raise this limit." 
				);
		}

		// if OK, will use a link in place of an address area in the compose window.
		$query_entity = WIC_List_Constituent_Export::download_rule( $outgoing_object->search_type, 'is_issue_only' ) ? '&entity=issue' : '&entity=search_log';
		$search_link = '<p><em>Messages will be sent individually to each of <b>' . $count_temp . '</b> constituents having addresses among selected.</em></p>
		<p><a target = "_blank" href="'. WIC_Admin_Setup::root_url() . '?page=wp-issues-crm-main' . $query_entity . '&action=id_search&id_requested=' . $outgoing_object->search_id . '">Click here to open a new window showing the underlying search.</a></p>';		
		return array ( 'response_code' => true, 'output' => $search_link );

	}

	// true return with text output is error for receiving compose window
	public static function load_draft ( $dummy , $id ) {

		global $sqlsrv;
		$result = $sqlsrv->query ( "SELECT * FROM outbox WHERE ID = ?", array ( $id ) );
		if ( !$result || !is_array($result) || 1 > count( $result ) ) {
			return array ( 'response_code' => true, 'output' => 'Draft  deleted or database error in retrieving draft; refresh page to check.' );
		} else {
			if ( 0 == $result[0]->is_draft ) {
				return array ( 'response_code' => false, 'output' => 'Draft was already sent; refresh page to check.' );
			}
			$outgoing_object = unserialize ( $result[0]->serialized_email_object ); 
			// repopulate draft_id in case draft was created but never saved with id
			$outgoing_object->draft_id = $result[0]->ID;
			// create search link 
			$search_link = '';
			$search_link_result = self::search_link ( $outgoing_object );
			if ( false === $search_link_result['response_code']  ) {
				// false return at this stage is a true ajax return -- compose window detects bad return based on object presence (string is an error message)
				$search_link_result['response_code'] = true;			
				return $search_link_result;
			} else {
				$search_link = $search_link_result['output'];
			}
			// create form
			ob_start();
			$compose_entity = new WIC_Entity_Email_Compose ( 'new_blank_form', array (
				 'search_link' => $search_link, 
				 'draft_id' => $outgoing_object->draft_id 
			) );
			$form = ob_get_clean();
		
			return array ( 'response_code' => true, 'output' => ( object ) array( 'object' => $outgoing_object, 'form' => $form ) );			
		}
	
	}
 

	/*
	*
	* called by js ajax 
	* returns false on any failure -- this is intercepted and user is alerted by WP Issues CRM ajax.js -- compose window has already closed and reset
	*	-- either directly or from delete_draft or email_to_list_of_constituents
	*/
	public static function update_draft ( $dummy, $message_object ) {
		/*
		* takes standard outgoing object -- WIC_DB_Email_Outgoing_Object
		*
		* updates if is_draft = 0 or 1; deletes if is_draft = -1
		*
		* updating is_draft to 0 is putting it in the send queue -- see email-deliver
		*
		*/
		global $sqlsrv;
		$id = $message_object->draft_id;

		if ( -1 == $message_object->is_draft ) {
			$result = self::delete_draft ( $id );
			// client is only handling errors, not giving positive feedback
			return array ( 'response_code' => 1 == $result, 'output' => 'Draft was already deleted or there was a database error.' );
		} 

		// do final security filter to outgoing
		$message_object->html_body = self::filter_rough_html_for_output ( $message_object->html_body );
		$message_object->text_body = self::filter_rough_html_to_text ( $message_object->html_body ); // here deriving text from html entirely
		
		if (
			// not doing a list send
			( 'email_send' != substr( $message_object->search_type, - 10 ) ) ||
			// or not sending yet
			$message_object->is_draft
			) {
				$serialized_outgoing_object = serialize ( $message_object ); 
				$to_address_concat = self::to_address_concat ( $message_object );
				$json_outgoing_object = self::standardize_json_message( $message_object);

				$update_result = $sqlsrv->query (
					"UPDATE outbox SET
						is_draft = ?,
						subject = ?,
						queued_date_time = ?,
						serialized_email_object = ?,
						json_email_object = ?,
						to_address_concat = ?
					WHERE ID = ?;
					",
					array ( 
						$message_object->is_draft,
						$message_object->subject,
						current_time( 'YmdHis' ),
						$serialized_outgoing_object,
						$json_outgoing_object,
						$to_address_concat,
						$id
					)
				);

				// in case sending record, should also save activity if message has is no longer draft
				if ( ! $message_object->is_draft ) {
					self::queue_message ( $message_object, true );				
				}
				
				return array ( 'response_code' => 1 == $update_result, 'output' => 'Draft was already deleted or there was a database error.' );
		} 
		// last possibility::sending to list
		$result = self::email_to_list_of_constituents ( '', $message_object );
		// leave draft in draft status unless all messages have been sent, in which case, they are the outbox records to keep
		if ( $result['response_code'] ) {
			self::delete_draft( $id );
		}
		return $result;
	}

	// return false (false or 0 update on failure)
	private static function delete_draft ( $id ) {
		global $sqlsrv;
		$result = $sqlsrv->query ( "DELETE FROM outbox WHERE ID = ?", array ( $id ) );
		return $result;	
	}

	private static function to_address_concat( &$email_object ) {
		$to_address_concat = '';
		foreach ( $email_object->to_array as $to_address ) {
			$to_address_concat .= ( $to_address[0] . $to_address[1] ) ;
		}
		foreach ( $email_object->cc_array as $cc_address ) {
			$to_address_concat .= ( $cc_address[0] . $cc_address[1] ) ;
		}
		return $to_address_concat;
	}
	
	/* does send for set of constituents */
	public static function email_to_list_of_constituents ( $dummy, $outgoing_object ) {

		$temp_table = WIC_List_Constituent_Export::do_constituent_download ( $outgoing_object->search_type, $outgoing_object->search_id );
		
		global $sqlsrv;
		$sql = "SELECT * FROM $temp_table";
		$constituent_list = $sqlsrv->query ( $sql, array() );

		$bad_sends = 0;
		$good_sends = 0;
		
		// set up templates that will remain fixed in loop below, possibly preserving dear token
		$html_template = $outgoing_object->html_body;
		$text_template = $outgoing_object->text_body;

		// $constituent_object is object containing c.ID, first_name, last_name, email_address (see assemble_constituent_export_sql for case non_blank_emails, type email_send)
		foreach ( $constituent_list as $constituent_object ) {
			// construct send object --
			$outgoing_object->to_array  = array( array( $constituent_object->first_name . ' ' . $constituent_object->last_name, $constituent_object->email_address, $constituent_object->ID ) );
			$outgoing_object->html_body = WIC_Entity_Email_Send::replace_dear_token( $constituent_object, $html_template );
			$outgoing_object->text_body = WIC_Entity_Email_Send::replace_dear_token( $constituent_object, $text_template );

			// queue message -- false: draft does not already exist for the particular message being queued
			$success = self::queue_message ( $outgoing_object, false );
			if ( !$success['response_code'] ) {
				$bad_sends++;
			} else {
				$good_sends++;
			}
		}
		
		return array ( 'response_code' => 0 == $bad_sends, 'output' => "$bad_sends messages could not be queued for delivery.  $good_sends messages were successfully queued." );

	}
	
	public static function replace_dear_token ( $constituent_object, $message_string ) {
		
		if ( false === strpos( $message_string, self::dear_token ) ) {
			return $message_string;
		}
	
		// determine token replacement value
		$dear_token_value = WIC_Admin_Settings::get_setting( 'dear_token_value' );
		$dear_token_value = $dear_token_value ? $dear_token_value : 'Dear';
		if ( $constituent_object->salutation > '' ) {
			$token_replacement_value = $dear_token_value . ' ' . trim( $constituent_object->salutation ) . ',';
		} elseif ( $constituent_object->first_name > '' ) {
			$token_replacement_value = $dear_token_value . ' '. ucfirst(strtolower(trim( $constituent_object->first_name ))) . ',';
		} else {
			$token_replacement_value = '';
		}
	
		// insert token replacement value once
		return preg_replace ( '#\|\|\|\*Dear\*\|\|\|#', $token_replacement_value, $message_string, 1 );
	
	}
	
}