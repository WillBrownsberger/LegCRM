<?php
/*
*
*	wic-entity-email-process.php
*
*/
class WIC_Entity_Email_Process  {


	/*
	*
	*	functions supporting processing of emails from bulk and online
	*
	*	all processes architected to be interruptible and to not conflict with either other runs or other online actions
	*		-- UID record/reply transactions, followed by hard move
	*		-- if two processes want the same UID, the first acts and the second bypasses it with an error
	*		-- folder actions are set at the start of the job 
	*	
	*	possiblities are:
	*	  (1) training mode on or off 	-- save a new subject line from client
	*	  (2) sweep mode on or off 		-- use trained subject line mappings, saved on record ( cannot be training at same time)
	*	  (3) reply mode 				-- both values of reply compatible with both values of training 
	*	  										-- when sweeping, do not reply if no template
	*/
	
	
	public static function handle_inbox_action_requests( $dummy_id, $data ) {

		/*
		* note that no need or reason to sort UIDs; sorted UID sequence actually results in more collisions because first process
		* catches up with second process and then keeps fighting for the same UID
		*/
		
		// set up outcome counters object
		$fail_counters = (object) array (
			'uid_already_reserved'				=> 0, // not an error, tracked only for debugging ( two processes running, conflict prevented )
			'uid_still_valid_failures' 			=> 0, // not an error, tracked only for debugging ( two processes running, this copy is second )
			'data'								=> $data // send back the original
		);

		// used after loop for error processing			
		$bad_issue = false;
		// begin the processing loop
		foreach ( $data->uids as $current_uid ) {
			
			// reserve uid for action
			if ( ! WIC_Entity_Email_UID_Reservation::reserve_uid ( $current_uid, $data ) ) {
				$fail_counters->uid_already_reserved++;
				continue;
			}

			// gathers preparsed message data
			$message = WIC_DB_Email_Message_Object::build_from_image_uid($current_uid );
			// check outcome and reject if message is not built
			if ( false === $message )  {
				$fail_counters->uid_still_valid_failures++;
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				continue;
			} 	
			if ( $data->sweep ) {
				$working_issue 		= $message->mapped_issue;
				$working_pro_con 	= $message->mapped_pro_con;
				// message was unmapped by another instance
				if ( ! $message->mapped_issue ) {
					WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
					continue;
				}
				$working_template	= WIC_Entity_Email_Message::get_reply_template ( $working_issue, $working_pro_con )['output'];	
			} else {
				$working_issue 		= $data->issue;
				$working_pro_con	= $data->pro_con;
				$working_template 	= $data->template;		
			}
			/* 
			* bullet proof against issue was trashed or deleted between issue assignment and processing, most likely on sweep, but also possible from inbox
			*
			*/
			if ( ! WIC_DB_Access_Issue::fast_id_validation( $working_issue ) ){
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				if ( $data->sweep ) {
					// not setting bad issue since will be overlayed and only controls non-sweep training actions
					continue;					
				} else {
					$bad_issue = true;
					break; // no point in continuing since if not sweeping, all messages have the same issue
				}
			}


			// add activity note -- formatted with message headers as well as content
			$activity_note = 		
				'<div id="recipients-display-line">' . 
					WIC_Entity_Email_Message::setup_recipients_display_line ( $message ) .
				'</div>' .
				'<div id="attachments-display-line">' . 
					WIC_Entity_Email_Message::setup_attachments_display_line( $message->inbox_image_id, 0 ) . 
				'</div>' .
				'<div id="inbox_message_text">' .  
					 WIC_Entity_Email_Attachment::replace_cids_with_display_url(  $message->raw_html_body, $message->inbox_image_id, 0  ) . 
				'</div>';
	
			global $sqlsrv;
			$sqlsrv->query(
				"
				EXECUTE saveConstituentActivity
				?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
				" // 19 parameters
				,
                array(
                    // removed utf8_encode call 
                    get_office(), //@OFFICE smallint,
                    $message->email_address,//@emailAddress varchar(200),
                    ucfirst( strtolower ( $message->first_name) ),//@firstName varchar(50),
                    ucfirst( strtolower ( $message->last_name) ),//@lastName varchar(50),
                    $message->address_line, //@addressLine varchar(100),
                    $message->city, // @city varchar(50),-- not in lookup
                    $message->state, // @state varchar(50), -- not in lookup
                    $message->zip, //@zip varchar(10),
                    $message->phone_number, //@phone
                    $message->assigned_constituent, //@constituentId bigint, (not validated yet; to be validated in saveConstituentActivity)
                    0,  //@in_or_out bit,
                    $working_pro_con, // @pro_con varchar(1)
                    $working_issue, // @issue int
                    $message->email_date_time, //@activity_date datetime2(0), will be converted from utc to eastern
                    $activity_note, // @activity_note varchar(max)
                    $message->is_my_constituent, // @is_my_constituent varchar(1)
                    $message->inbox_image_id, // @related_inbox_image_record  int,
                    0, // @related_outbox_record int,
                    get_current_user_id() // @current_user_id int
                )
			);


			if ( 
				! $sqlsrv->success || // some exceptions are caught, so may show success on failure
				! isset( $sqlsrv->last_result[0] ) || // should always be present unless constituent_id was zero
				0 == $sqlsrv->last_result[0]->activity_id // reset on rollback transaction, belt and suspenders
				) {  
				$save_error_message = "Database error saving new constituent record in WIC_Entity_Email_Process::handle_inbox_action_requests. Proc return was ";
				$save_error_message .= ( $sqlsrv->success ? "successful. " : "unsuccessful. " );
				$save_error_message .= ( isset( $sqlsrv->last_result[0] ) ? "An array was returned with element [0] activity ID = {$sqlsrv->last_result[0]->activity_id} " : "Null was returned. " );
				$save_error_message .= ( "Message ID was {$message->inbox_image_id} and message assigned constituent was {$message->assigned_constituent}. ");
				$save_error_message .= ( "User was " . get_current_user_id() . " in office " . get_office());
				error_log ( $save_error_message );
				WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
				return array ( 'response_code' => false, 'output' => 'Database error saving new constituent record -- likely database outage; processing halted.' );
			} else {
				$result = $sqlsrv->last_result[0]; // object with properties activity_id, constituent_id, first_name,
					 // middle_name, last_name, salutation, gender, name (fn ln)
			}
		
			/*
			*
			* Now save the reply to queue if so ordered
			*
			*/
			if ( $data->reply ) {
				/*
				* $data->reply is set to true on sweeping
				*
				* if sweeping or have more than one uid in the line, then just using address from parsing and id/name from found constituent
				*/
				if ( $data->sweep || count( $data->uids ) > 1 ) {
					$final_subject = 'RE: ' . $message->subject;
					$to_array  = array( 
						array( 
							$result->name, 
							$message->email_address,
							$result->constituent_id 
						) 
					);
					$cc_array = array();
					$bcc_array = array();
				} else {
					$final_subject = $data->subjectUI;
					$to_array = $data->to_array;
					$cc_array = $data->cc_array;
					$bcc_array = $data->bcc_array;
				}
				$addresses = array( 'to_array' => $to_array, 'cc_array' => $cc_array, 'bcc_array' => $bcc_array );	
				
	
				// handle dear token if any
				$working_template =  WIC_Entity_Email_Send::replace_dear_token( $result, $working_template );
			
				// set up object for queue_message
				$outgoing_object = new WIC_DB_Email_Outgoing_Object ( 
					$addresses, 				// address array
					$final_subject, 			// subject
					$working_template . 
						WIC_Entity_Email_Message::get_transition_line ( $message ) . 
						$message->raw_html_body,// html_body
					$working_template .
						WIC_Entity_Email_Send::body_break_string . 
						WIC_Entity_Email_Message::get_transition_line ( $message ) . 
						$message->raw_html_body,// most complete version of message --  will be filtered to text body in queue_message
					0,							// is_draft = false 
					$message->inbox_image_id,	// is_reply_to 	
					isset ( $data->include_attachments ) ? $data->include_attachments : false,	
					$working_issue, 
					$working_pro_con 
				);
				
				// queue_message unless working template is '' -- this could occur in sweep for record-only trained messages
				if ( $working_template > '' ) {
					$reply_result = WIC_Entity_Email_Send::queue_message ( $outgoing_object, false ); 
					// an error here should stop the job -- indicative of a database problem
					if ( false === $reply_result['response_code'] ) {
						WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
						return $reply_result;
					}
				}
			}	
		
			// if got to here OK, mark the message as to be moved
			self::delete_release_uid ( $current_uid ); 

		} // end of loop

		// note that neither of the actions within this possible condition are active during sweeps
		if ( ! $bad_issue ) {
			// if training (never on sweeps), store the new subject line record and parallel content map
			if ( $data->train ) {
				$training_response = WIC_Entity_Email_Subject::save_new_subject_line_mapping( $data );
			} 
		}

		return array ( 'response_code' => true, 'output' => $fail_counters );

	} 

	public static function handle_delete_block_request ( $dummy_id, $data ) {
	
		// first test incoming
		if ( ! isset ( $data->uids ) || ! is_array ( $data->uids ) ) {
			$message = "Bad call to WIC_Entity_Email_Process::handle_delete_block_request.\n No uids.  data was: " 
					. print_r($data, true) . "\n\n"; 
			Throw new Exception( $message );
			return array ( 'response_code' => false, 'output' => $message );

		}
	
		// begin the processing loop
		foreach ( $data->uids as $current_uid ) {
			// test each element
			if ( ! $current_uid || ! is_numeric ( $current_uid ) ) {
				$message = "Bad call to WIC_Entity_Email_Process::handle_delete_block_request.\nIncluded 0 or non-numeric uid.  data passed was \n" 
					. print_r($data, true) . "\n\n";
				Throw new Exception(  $message );
				return array ( 'response_code' => false, 'output' => $message );

			}
			// reserve uid for action
			if ( ! WIC_Entity_Email_UID_Reservation::reserve_uid ( $current_uid, $data ) ) {
				continue;
			}
			// do the delete and release the record
			self::delete_release_uid ( $current_uid );
			// reread the record, construct and apply the filter (block is true only in single record cases);
			if ( true === $data->block ) {
				WIC_Entity_Email_Block::set_address_filter ( $current_uid, $data->wholeDomain );
			}
		} // end of loop

		return array ( 'response_code' => true, 'output' => '' );
	}


	public static function delete_release_uid ($current_uid ) {
		global $sqlsrv;
		$inbox_table = 'inbox_image';
		$sql = "UPDATE $inbox_table SET to_be_moved_on_server = 1 WHERE  folder_uid = ? and OFFICE = ?";
		$sqlsrv->query ( $sql, array( $current_uid, get_office() )  );
		WIC_Entity_Email_UID_Reservation::release_uid ( $current_uid );
	}

	public static function setup_settings_form() { 

		$settings_form_entity = new WIC_Entity_Email_Settings ( 'no_action', '' );
		return array ( 'response_code' => true, 'output' => $settings_form_entity->settings_form() );
	}
	
	// sanitize and save options 
	public static function save_processing_options( $dummy, $options ) {
		/*
		* sanitize options
		*/
		foreach ( $options as $key => $value ) {
			// will also hard sanitize this option before using it -- comes in character limited and is verified character limited
			if ( 'team_list' == $key) {
				$options->$key == preg_replace ( '/[^%@.+_A-Za-z0-9-]/', '', $value );
			} elseif (  'non_constituent_response_message' == $key ) {
				$options->$key = WIC_DB_Email_Message_Object::strip_html_head( $value );
			} elseif (  'forget_date_interval' == $key ) {
				$value = intval ( $value );
				if ( $value < 1 || $value > 10000 ) {
					$options->$key = 60;
				}
			} else {
				$options->$key = utf8_string_no_tags ( $value );
			}
		}
		/*
		* return on save is always true -- generates fatal error if actual problem saving
		*/
		$result = WIC_Admin_Settings::update_settings_group ( 'wp-issues-crm-email-processing-options', $options ) ;
		
		return array ( 'response_code' => true, 'output' => 'Options saved OK.' );
	}
	
	/*
	* called to support settings form population and in some instances where multiple email settings are used in the same routine
	*/
	public static function get_processing_options( ) {
		// load options -- comes back unserialized (no longer serializing on save since update_option serializes )
		// always returns at least an empty array;
		$options_object = (object) WIC_Admin_Settings::get_settings_group( 'wp-issues-crm-email-processing-options' );

		// restore basic option defaults from class ( on error or first use )
		// force nonblank for forget date phrase
		foreach ( WIC_Entity_Email_Settings::get_entity_dictionary() as $field => $details ) { 
			if ( !isset ( $options_object->$field ) || '' == $options_object->$field  ) {
				if ( isset($details['field_default'] ) ) {
					@$options_object->$field = $details['field_default'];
				}
			}
		}

		// not handling error from retrieval of option -- on start up, want to load overloaded properties anyway
		return array ( 'response_code' => true, 'output' =>  $options_object );
	}

	public static function first_n1_chars_or_first_n2_words ( $utf8, $n1, $n2 ) {
		if ( function_exists ( 'iconv_substr' ) ) { // note that this branch will generally be used since iconv is installed by default and wp issues crm requires it for charactiver conversion
			return iconv_substr( $utf8, 0, $n1, 'UTF-8' );
		} else {
			$shortened_utf8 = '';
			$utf8_word_array = explode ( ' ', $utf8 );
			$max = count ( $utf8_word_array );
			for ( $i=0; $i<$n2; $i++ ) {
				if ( $i == $max ) {
					break;
				}
				$shortened_utf8 .= ( $utf8_word_array[$i] . ' ' );
			}		
			return trim ( $shortened_utf8 );
		} 
	}
	
	

	
	
}