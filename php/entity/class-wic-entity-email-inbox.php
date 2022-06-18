<?php
/*
*
*	wic-entity-email-inbox.php
*
*/
Class WIC_Entity_Email_Inbox extends WIC_Entity_Parent {


	/*
	*
	* basic entity functions
	*
	*
	*/

	public function __construct ( $action_requested, $args ) { 
		$this->set_entity_parms( $args );
		$this->$action_requested( $args );
	}

	protected function set_entity_parms( $args ) {
		$this->entity = 'email_inbox';
		$this->entity_instance = '';
	} 

	// special version of this function to allow checking of settings before form display 
	protected function new_blank_form( $args = '', $guidance = '' ) { 
		$this->initialize_data_object_array();
		$new_form = new WIC_Form_Email_Inbox;
		// passing $args to layout_inbox -- entity_parent standard is to pass $guidance with third param styling info
		$new_form->layout_inbox( $this->data_object_array, $args, '' );
	}	

	// previously was written to support hooks
	public static function get_tabs () {
		$tabs_array = array( 
			'Individual',
			'Advocacy',
			'Gov',
			'Bulk',
			'Assigned',
			'Ready',
		);
		return $tabs_array;
	}



	private function display_email_process_not_configured_splash() {
		echo '
			<div id="unconfigured-message-for-process-email">
				<p>WP Issues CRM Email: Not fully configured -- check Controls.</p>
			</div>
		';
	}

   /*
   * functions supporting field definitions
   */
   // pass through from original entity
	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}
	public static function get_inbox_options ( $value ) {
		return array (
			array ( 'value' => 'inbox'    		,  'label' => 'Inbox'   ),
			array ( 'value' => 'draft'			,  'label' => 'Drafts'  ),
			array ( 'value' => 'done'			,  'label' => 'Archive'  ),
			array ( 'value' => 'outbox'			,  'label' => 'Outbox'   ),
			array ( 'value' => 'sent'			,  'label' => 'Sent'  ),
			array ( 'value' => 'saved'			,  'label' => 'Saved'  ),
			array ( 'value' => 'manage-subjects',  'label' => 'Subjects'  ),
			array ( 'value' => 'manage-blocks'	,  'label' => 'Blocked'   ),
			array ( 'value' => 'settings'  		,  'label' => 'Controls'   ),
		);
	}

	public static function load_inbox ( $dummy_id, $data ) {

		global $current_user;
		// set up variables
		$current_user_id = get_current_user_id();
		// note that if !$user_can_see_all, then WIC_Admin_Access will have bounced a tab request other than for CATEGORY_ASSIGNED, CATEGORY_READY
		$user_can_see_all = $current_user->current_user_authorized_to ( 'all_email');
		$tab_display = safe_html(ucfirst ( strtolower( substr ( $data->tab, 9 ) ) ) );
		/*
		* Compose sql statements to assemble inbox lines from constant and mode-dependent elements
		*
		* Note: the if statements in this query (and in load_message_detail ) implement the following conservative logic tree for choosing when to group issues:
		*
		* Group messages if and only if:
		*   (1) There is a subject map record that they share (so, same subject) (not expired);
		*   (2) HAVE NOT HAD ANY INDIVIDUAL MESSAGE INBOX DEFINITION ACTIVITY
		*   NOTE: There may or may not be a reply already assigned to the issue -- could be just recording
		*
		*   most of the inbox (as opposed to message detail view) is security and cosmetics: the only processing consequences of the inbox line content flow from 
		*		* "have_trained_issue" (which determines, by adding a class below, whether a line is grouped and so eligible for sweeps
		*	 	*  and the folder_uid list (which is the basis of all line processing)
		*
		*   note that there is an overriding ungroup rule which is parse quality vs. strictness (lower is better/stricter, so ungroup if quality > strictness)
		*/
		global $sqlsrv;
		/*
		*
		* ALL VARIABLES ARE ACCCUMULATED IN SEGMENTS SO CAN WILL BE MERGED IN CORRECT ORDER INTO FINAL VALUES ARRAY FOR PARAMETRIZED QUERIES 
		*
		* Array names correspond to phrase names plus _values
		*/
		$sweep_definition_values = array();
		$assigned_subject_join_values = array();
		$filter_where_values = array();
		$category_where_values = array();
		$other_where_terms_values = array();
		$group_lines_values = array();
		$ungroup_lines_values = array(); // never populated
		/*
		* sweep_definition -- is the item sweepable
		*  exclude unclassified post as sweepable
		*  4 inbox_defined terms exclude items that have had individual attention from the inbox in any grouping
		*/
		$unclassified_post = WIC_Entity_Activity::get_unclassified_post_array()['value'];
		$sweep_definition = " 
			mapped_issue > 0 AND 
			mapped_issue != $unclassified_post AND
			inbox_defined_staff = 0 AND
			inbox_defined_issue = 0 AND
			inbox_defined_pro_con = '' AND
			inbox_defined_reply_text = ''
			";

		// user supplied filter string from in box email -- $data->filter is not blank, from email, from personal (name), subject and snippet will be scanned for it
		// only emails with a positive scan will be returned
		$filter = utf8_string_no_tags ( $data->filter );
		$filter_where_result = self::filter_where ( $filter );
		$filter_where = $filter_where_result['string'];
		$filter_where_values = $filter_where_result['array'];

		/*
		* add tab selection terms -- CATEGORY_TEAM, CATEGORY_ADVOCACY, CATEGORY_ASSIGNED, CATEGORY_READY special, dynamically applied
		*
		* assigned_subject is the subject from $assigned_subject_join subselection query below 
		*	if assigned_subject is null, 
		*     	no email with that subject is currently in the inbox and assigned
		*		the email should be displayed in its parsed catogery or if mapped in CATEGORY_ADVOCACY
		*
		*   if assigned_subject is not null,
		*		the email is assigned or has a subject line that is assigned and should be displayed as
		*			CATEGORY_ASSIGNED or if a response has been drafted in CATEGORY_READY	
		*
		*    if ! $user_can_see_all, just choosing between two allowed tabs --
		*		CATEGORY_ASSIGNED or if a response has been drafted in CATEGORY_READY
		* 
		*/ 

		// assemble the category_where
		if ( $user_can_see_all ) {
			$category_where = 
				"
				IIF( 
					assigned_subject is NULL, 
					IIF( mapped_issue != $unclassified_post AND mapped_issue > 0, 'CATEGORY_ADVOCACY', category), 
					IIF( subject_is_final > 0 , 'CATEGORY_READY', 'CATEGORY_ASSIGNED' )
				) = ?
				AND
				";
		} else {
			$category_where = " IIF( inbox_defined_reply_is_final > 0 , 'CATEGORY_READY', 'CATEGORY_ASSIGNED' ) = ? AND "; 
		}		
		$category_where_values[] = $data->tab;

		/*
		* limit selection to inbox content ( not deleted or intended to be deleted and already parsed)
		*
		* if not can see all absolutely limit to only assigned emails
		*/
		$other_where_terms = 
			"
			no_longer_in_server_folder = 0 AND
			to_be_moved_on_server = 0 AND
			folder_uid > 0 AND
			OFFICE = ?
			";
		$other_where_terms_values[] = get_office();

		if( ! $user_can_see_all ) {
			$other_where_terms .=  " AND inbox_defined_staff = ?"; 
			$other_where_terms_values[] = $current_user_id;
		}

		$sort_assigned_to_top = ( $data->tab == 'CATEGORY_ASSIGNED' || $data->tab == 'CATEGORY_READY' ) ? 
			( 
				'grouped' != $data->mode ? 
					" IIF( inbox_defined_staff > 0, 1, 0) DESC, " :
					" IIF( max(inbox_defined_staff) > 0, 1, 0) DESC, " 
			) :
			'';
		// key implementing language for group options
		$sort_safe = 'ASC' == $data->sort ? 'ASC' : 'DESC'; // avoid inserting unsafe as an a sql term
		$group_lines =
			"GROUP BY IIF( $sweep_definition, subject, cast(folder_uid as varchar(30))) COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8";
		$ungroup_lines = '';

		$order_by_lines = 'grouped' != $data->mode ?
			" ORDER BY $sort_assigned_to_top  email_date_time $sort_safe " :
			" ORDER BY  $sort_assigned_to_top min(email_date_time)  $sort_safe ";
		/*
		*
		* join to support Assigned and Ready tabs
		*
		* $user_subject_where_limit does not limit the larger search, only the look up for the following subsidiary tables
		*
		* this join is only  necessary when showing assigned and ready tabs for user with Email capability -- serves to move 
		*	subjects that are identical to subjects of assigned emails into the ready and advocacy tabs
		*/
		$data_staff_array = array();
		if ( $data->staff ) {
			$user_subject_where_limit =  " WHERE inbox_defined_staff = ? ";
			$data_staff_array[] = $data->staff;
		} else {
			$user_subject_where_limit =  " WHERE inbox_defined_staff > '' ";
		}
		$assigned_subject_join = '';
		if( $user_can_see_all ) {
			$assigned_subject_join = 
			"
			LEFT JOIN 
				(  
				  SELECT max( inbox_defined_reply_is_final ) as subject_is_final, subject as assigned_subject 
				  FROM inbox_image 
				  $user_subject_where_limit AND $other_where_terms
				  GROUP BY subject
				) assigned_subjects 
			ON subject = assigned_subject 
			";
			$assigned_subject_join_values = array_merge( $data_staff_array, $other_where_terms_values  );
		}
		/*
		*
		* first check counts for all tabs with only basic where terms -- show straight message count, not grouped -- if all counts = 0
		*
		* if ! $user_can_see all, only seeing ready and assigned tabs and only for current user is inbox_defined_staff, only first two cases apply
		*/
		$tabs_array = self::get_tabs(); 
		$tabs_summary_sql = '';
		$tabs_summary_sql_values = array();
		foreach ( $tabs_array  as $tab ) { 
			$category = 'CATEGORY_' . strtoupper( $tab );
			// categories are programmatically supplied, not user input, but for extra safety sanitize to safe slug
			$category = slug_sanitizor ( $category );

			if ( ! $user_can_see_all && ! in_array( $category, array( 'CATEGORY_READY', 'CATEGORY_ASSIGNED' ) ) ) {
				continue;				
			}
			// this logic covers all five possible combinations for counts, the default covering all tabs other than the synthetic team, ready, advocacy and assigned 
			$tabs_summary_sql .= ", SUM(IIF(" ; 
			switch ( $category) {
				// in the first two cases, subject_is_final cannot be null because assigned_subject_is not null and inbox_defined_reply_is_final is not null field
				case 'CATEGORY_READY':
					$tabs_summary_sql .= (
						$user_can_see_all ?
						" assigned_subject IS NOT NULL AND subject_is_final > 0, ":
						" inbox_defined_reply_is_final > 0, "
					);
					break;
				case 'CATEGORY_ASSIGNED':
					$tabs_summary_sql .= (
						$user_can_see_all ?
						" assigned_subject IS NOT NULL AND subject_is_final = 0, ":
						" inbox_defined_reply_is_final = 0,  "
					);
					break;
				// in the latter two cases, mapped_issue cannot be null because it is a not null field
				case 'CATEGORY_ADVOCACY':
					$tabs_summary_sql .= 
						" assigned_subject IS NULL AND ( mapped_issue > 0 OR category = '$category' ), ";
					break;
		    	default:
		    		$tabs_summary_sql .= 
						" assigned_subject IS NULL AND  ( mapped_issue = 0 AND category = '$category' ), ";	
			
			} 
			$tabs_summary_sql .= "1, 0)) as $category"; // 
		}
				
		$tabs_count_sql =		
			"
			SELECT count(ID) as all_inbox_messages_count $tabs_summary_sql
			FROM inbox_image $assigned_subject_join 
			WHERE $other_where_terms			
			"
		; 		

		$tab_counts = $sqlsrv->query ( 
			$tabs_count_sql, 
			array_merge( 
				$tabs_summary_sql_values, 
				$assigned_subject_join_values, 
				$other_where_terms_values
			) 
		); 

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		// $detail_select_terms = 
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		if ( 'grouped' != $data->mode ) {
			// sql for the inbox rows
			$subjects_array_sql = 
			"
			SELECT 
			inbox_defined_staff,
			subject,
			snippet,
			account_thread_id,
			from_email,
			from_domain,
			is_my_constituent_guess as mine,
			IIF ( email_date_time > '', email_date_time, activity_date ) as oldest,
			1 as [count],
			folder_uid as UIDs,
			IIF( from_personal > '', from_personal, from_email ) as [from],
			IIF( $sweep_definition, 1, 0 ) as have_trained_issue,
			0 as conversation,
			0 as many
			FROM inbox_image $assigned_subject_join
			WHERE 
				$filter_where
				$category_where
				$other_where_terms 
				$ungroup_lines 
				$order_by_lines
			OFFSET $page_base ROWS FETCH NEXT $max_count ROWS ONLY					
			";
			// sql for the inbox counts 
			$summary_sql =  
			" 
			SELECT count(*) as found_count
			FROM inbox_image $assigned_subject_join
			WHERE 
				$filter_where
				$category_where
				$other_where_terms 
				$ungroup_lines 
			";
			$values = array_merge (
				$sweep_definition_values,
				$assigned_subject_join_values,
				$filter_where_values,
				$category_where_values,
				$other_where_terms_values,
				$ungroup_lines_values
			);
			$values_summary = array_merge (
				$assigned_subject_join_values,
				$filter_where_values,
				$category_where_values,
				$other_where_terms_values,
				$ungroup_lines_values
			);			
		} else {
			$subjects_array_sql = 
			"
			SELECT 
			max(inbox_defined_staff) as inbox_defined_staff,
			max(subject) as subject,
			max(snippet) as snippet,
			max(account_thread_id) as account_thread_id,
			max(from_email) as from_email,
			max(from_domain) as from_domain,
			max(is_my_constituent_guess) as mine,
			min(IIF( email_date_time > '', email_date_time, activity_date ) ) as oldest, 
			count(folder_uid) as [count], 
			string_agg( cast(folder_uid as varchar(max)), ',' ) as UIDs," . // this uid list becomes the array that drives processing when the user sweeps or shifts to inbox view
			"max(IIF(from_personal > '', from_personal, from_email )) as [from], " . // used only in conjunction with many for display purposes
			"max(IIF( $sweep_definition, 1, 0 )) as have_trained_issue," . // have_trained_issue is variable that determines eligibility for sweep by setting class 'trained-class' -- group by logic assures that all in group have same sweep definition
			"max(IIF(LEFT(subject,3)='re:', 1, 0 )) as conversation,
			IIF(COUNT(DISTINCT from_personal) > 1, 1, 0 ) as many
			FROM inbox_image $assigned_subject_join
			WHERE 
				$filter_where
				$category_where
				$other_where_terms 
				$group_lines
				$order_by_lines
			OFFSET $page_base ROWS FETCH NEXT $max_count ROWS ONLY					
			";
			// rollup totals
			$summary_sql =  
			"
			SELECT count(id) as found_count
			FROM 
				(
				SELECT 1 as id FROM inbox_image $assigned_subject_join
				WHERE 
					$filter_where
					$category_where
					$other_where_terms 
					$group_lines
				) summ
			";
			$values = array_merge (
				$sweep_definition_values,
				$assigned_subject_join_values,
				$filter_where_values,
				$category_where_values,
				$other_where_terms_values,
				$group_lines_values
			);
			$values_summary = array_merge(
				$assigned_subject_join_values,
				$filter_where_values,
				$category_where_values,
				$other_where_terms_values,
				$group_lines_values				
			);
		}
		
		// get subjects array
		$subjects_array = $sqlsrv->query( $subjects_array_sql, $values );

		// get count total messages ( as filtered and/or grouped ) . . . tracking the sql above, but not recreating values
		$found_result = $sqlsrv->query( $summary_sql, $values_summary);	  
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-arrived ' : ' last-arrived ';
		$loaded_object = 'grouped' == $data->mode ? 'subject lines' :   'messages';
		$filter_statement = safe_html($filter ? ' (filtered by "' . $filter . '")' : ''); // sanitized above but not for html
		// define user limit statement
		if ( ( ! $user_can_see_all || $data->staff  ) && ( $data->tab == 'CATEGORY_ASSIGNED' || $data->tab == 'CATEGORY_READY' ) ) {
			$user_data = $current_user->get_user_by_id(  $user_can_see_all ? $data->staff : $current_user_id );
			$user_display_name = safe_html( $user_data->user_name ? $user_data->user_name : $user_data->user_email );		
			$user_limit_statement = ' Limited to messages assigned to ' . $user_display_name . '. ';
		} else {
			$user_limit_statement = '';
		}
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page. $user_limit_statement" ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			/*
			* create inbox output -- consider this an interface from prior message analysis, but only some elements of each subject line have consequences down stream:
			* -- trained-subject class, driven by query analysis of message above (does the message in this subject line meet strict sweep criteria?);
			*	 . . . used in wpIssuesCRM.processEmail (email-process.js) to aggregate a list sweepable uids
			*	 . . . if NOT in sweep mode, processing runs off the issue/pro_con and template coming from the form
			* -- array (comma separated in each line) of UIDs defines which messages are acted on
			* -- count of messages is used in chrome for both inbox and inbox detail
			*/
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$from_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   ) . $subject->from . ( $subject->many ? ' +' : '' );
				// regardless of count, if already mapped mark with class and show the already mapped legend
				if ( $subject->have_trained_issue > 0  ) {
					$trained_class = ' trained-subject ';
					$trained_legend = 'Mapped: '; // changed vocabulary
				} else {
					$trained_class = '';
					$trained_legend = '';
				}
				// mark as assigned
				$assigned_staff_class =  $subject->inbox_defined_staff ? ' inbox-assigned-staff ' : '';
				$uid_count = $subject->count;
				$uid_list = $subject->UIDs;

				// format an li for the inbox
				$output .= 
				'<li class="inbox-subject-line '. safe_html( $trained_class) .'">' .
					'<div class="inbox-subject-line-checkbox-wrapper">' .
						'<input class="inbox-subject-line-checkbox"  type="checkbox" value="1"/>' .
					'</div>' .
					'<ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
						'<li class = "subject-line-item from-email">' . safe_html($subject->from_email) . '</li>' . // hidden, used to filter out blocked
						'<li class = "subject-line-item from-domain">' . safe_html($subject->from_domain) . '</li>' . // hidden, used to filter out blocked
						'<li class = "subject-line-item from-summary' . ( 'Y' == $subject->mine ? ' includes-constituents ' : '' ) . '">' . safe_html($from_summary ). '</li>' . // just display
						'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . $uid_count . '</span></li>' . // *supports multiple UI elements*
						'<li class = "subject-line-item subject' . $trained_class . $assigned_staff_class . '">' . $trained_legend . '<span class="actual-email-subject">' . $subject->subject . '</span><span class="wic-email-snippet">' . ( $subject->snippet ? ' -- ' : '' ).$subject->snippet .'</span></li>' . // just display
						'<li class = "subject-line-item oldest" title="Date of oldest">' . $subject->oldest . '</li>' . // just display
						'<li class = "subject-line-item UIDs">' . $uid_list . '</li>' . // *pass through for all processing*
						'<li class = "subject-line-item account_thread_id">' . $subject->account_thread_id . '</li>' . // *pass through to inbox for presentation
					'</ul>
				</li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . $view_statement . '</div>' . 
				'</div>';
		// no messages found
		} else {
			if ( !$tab_counts[0]->all_inbox_messages_count ) {
				$output = '<h3 class="all-clear-inbox-message">All clear -- done for now! ' . $user_limit_statement  . '</h3>';
			} else {
				if ( ! in_array( $tab_display, self::get_tabs() ) ) {  
					$output = '<div id = "filtered-all-warning">Tab configuration changed? Refresh page to reload tabs.</div>';
				} else {
					$output = $filter  ? 
						( '<div id = "filtered-all-warning">' . 
							safe_html('No from email address or subject line containing "' . $filter . '" ' . ' in ' . $tab_display ) . '.</div>' ) : 
						('<div id="inbox-congrats">' .
							safe_html('All clear  in ' . $tab_display . '. ' . $user_limit_statement ) .'</div>' );
				}
			}
		}

		// construct inbox title
		$inbox_header = 'INBOX' .
			(
				$found_count ?				
				( ': ' .  ( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object . ' in ' . $tab_display) :
				''
			);
		if ( $filter ) {
			$inbox_header .= " filtered by '$filter'";
		}
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		


		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => WIC_Entity_Email_UID_Reservation::check_old_uid_reservations(),
			'last_load_parms' => $load_parms,
			'tab_counts' => $tab_counts[0]
		);
		
		return array ( 'response_code' => true, 'output' => $return_array ); 
	}

	public static function load_sent ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 1, 0, $data );
	}

	public static function load_outbox ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 0, 0, $data );
	}

	public static function load_draft ( $dummy_id, $data ) {
		return self::load_sent_outbox ( 0, 1, $data );
	}


	private static function load_sent_outbox ( $sent_ok, $is_draft, $data ) { 
	
		/*
		* Return looks like inbox to js
		*
		*/
		global $sqlsrv;
		$outbox = 'outbox';
		$filter = utf8_string_no_tags ( $data->filter );
		if ( $filter > '' ) {
			$filter_where =
				"
				( 
					PATINDEX( ?, subject ) > 0 OR 
					PATINDEX( ?, to_address_concat ) > 0 
				) AND 
				"
				;
			$filter_where_values = array( '%' . $filter . '%', '%' . $filter . '%' );
		} else {
			$filter_where = '';
			$filter_where_values = array();
		}
		// limit selection to sent/draft content
		$other_where_terms = 
			" sent_ok = ? AND is_draft = ? AND OFFICE = ?";	
		$other_where_terms_values = array( $sent_ok, $is_draft, get_office()  );
		$oldest = $sent_ok ? "sent_date_time" : "queued_date_time"; // this correct in draft mode -- sent_ok = 0

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$safe_sort = ($data->sort == 'ASC' ? 'ASC' : 'DESC');
		$subjects_array_sql = 
			(
			( 'grouped' != $data->mode || $is_draft ) ?
			" 
			SELECT 
				ID,
				subject,
				serialized_email_object,
				json_email_object,
				$oldest as oldest,
				1 as [count]
			"
			:
			"
			SELECT 
				min(ID),
				min(subject),
				min(serialized_email_object),
				min(json_email_object),
				min( $oldest ) as oldest, 
				count(ID) as [count]
			"
			) . 
			"
			FROM $outbox
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				( 'grouped' == $data->mode && !$is_draft ) ? 
					( " GROUP BY SUBJECT ORDER BY MIN( $oldest ) " . $safe_sort . ' ' ) : 
					( " ORDER BY $oldest " . $safe_sort . ' ')
			) .
			"
			OFFSET $page_base ROWS FETCH NEXT $max_count ROWS ONLY					
			"
			;
		$values = array_merge ( $filter_where_values, $other_where_terms_values );
		// get subjects array
		$subjects_array = $sqlsrv->query( $subjects_array_sql, $values );
		
		// now do sql for counts
		$summary_sql = 
			"
			SELECT COUNT(*) AS found_count
			FROM $outbox
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				( 'grouped' == $data->mode && !$is_draft ) ? 
					" GROUP BY SUBJECT " : 
					"" 
			) 
			;
		// get count total messages ( as filtered and/or grouped )
		$found_result = $sqlsrv->query( $summary_sql, $values );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$past_tense = $is_draft ? 'drafted' : ( $sent_ok ? 'sent' : 'queued' );
		$sort_order = ( 'ASC' == $data->sort ) ? " first-$past_tense " : " last-$past_tense ";
		$loaded_object = ( 'grouped' == $data->mode && !$is_draft ) ? 'subject lines' :   'messages';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$email_object = unserialize ( $subject->serialized_email_object );
				$to_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   );
				if ( $email_object ) {
					$to_summary .= isset ( $email_object->to_array[0] ) ?
					( ( trim($email_object->to_array[0][0]) ? trim( $email_object->to_array[0][0] ) : $email_object->to_array[0][1] ) . ( $subject->count > 1 ? '+' : '' ) ) :
					'Addressee not specified yet';
				} else {
					// c# sent autoreply
					$json_object = json_decode ( $subject->json_email_object );
					$to_summary .= $json_object->To_array[0]->Address;
				}
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' .
					'<li class = "subject-line-item message-ID">' . safe_html($subject->ID) . '</li>' .
					'<li class = "subject-line-item from-summary">' . safe_html($to_summary) . '</li>' .
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . safe_html($subject->count) . '</span></li>' .
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . safe_html($subject->subject) . '</span></li>' . 
					'<li class = "subject-line-item oldest" title="Date of oldest">' . safe_html($subject->oldest) . '</li>' .
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . safe_html( $view_statement ). '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">' . safe_html('No to/cc email address or subject line containing "' . $filter ) . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No ' . ( $is_draft ? 'draft' : ( $sent_ok ? 'sent' : 'unsent' ) ). ' messages.</h1>' );
		}

		// construct inbox title
		$inbox_header = 
			( $is_draft ? 'Draft: ' : ( $sent_ok ? 'Sent: ' : 'Outbox: ' ) ). 
			'<span id="outbox-lower-range">' . ( $found_count ? $page_base + 1 : 0 ) . '</span>-<span id="outbox-upper-range">' . ( $page_base + $count_subjects ) . '</span> of <span id="outbox-total-count">' . $found_count . '</span> ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}

	private static function filter_where ( $filter ) {

		if ( $filter > '' ) {
			$filter_where_string = " 
				( 
					PATINDEX( ?, subject ) > 0 OR 
					PATINDEX( ?, from_email ) > 0 OR 
					PATINDEX( ?, from_personal ) > 0 OR
					PATINDEX( ?, snippet ) > 0
				) AND 
				"; 
				$filter_string = '%' . $filter . '%';
				$filter_where_array = array( 
					$filter_string, 
					$filter_string, 
					$filter_string, 
					$filter_string 
				);
		} else {
			$filter_where_string = '';
			$filter_where_array = array();
		}
		return array(
			'string' => $filter_where_string,
			'array'  => $filter_where_array,
		);
	}

	public static function load_done ( $dummy_id, $data ) { 

		/*
		* looks like inbox to js and css
		* 
		*/
		// done messages remain in the inbox		
		global $sqlsrv;

		$filter = utf8_string_no_tags ( $data->filter );
		$filter_where_result = self::filter_where ( $filter );
		$filter_where = $filter_where_result['string'];
		$filter_where_values = $filter_where_result['array'];

		// limit selection to inbox content
		$other_where_terms = 
			"to_be_moved_on_server = 1 AND OFFICE = ?
			";	
		$other_where_terms_values = array ( get_office() );
		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$safe_sort = ($data->sort == 'ASC' ? 'ASC' : 'DESC');
		$subjects_array_sql = 
			(
			'grouped' != $data->mode ?
			"SELECT
				ID,
				subject,
				from_personal as [from],
				IIF ( email_date_time > '', email_date_time, activity_date ) as oldest,
				1 as [count],
				0 as many
			"
			:
			"SELECT
				max(ID),
				max(subject),
				max(from_personal as [from]),
				min( IIF ( email_date_time > '', email_date_time, activity_date ) ) as oldest,
				count(ID) as [count], 
				IIF(COUNT(DISTINCT from_personal) > 1, 1, 0 ) as many
			"
			) . 
			"
			FROM inbox_image
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				'grouped' == $data->mode ? 
					( " GROUP BY SUBJECT ORDER BY MIN( IIF ( email_date_time > '', email_date_time, activity_date ) ) " .$safe_sort . ' ' ) : 
					( " ORDER BY IIF( email_date_time > '', email_date_time, activity_date )  " . $safe_sort . ' ' )
			) .
			"
			OFFSET $page_base ROWS FETCH NEXT $max_count ROWS ONLY					
			"
			;

		// get subjects array
		$values = array_merge ( $filter_where_values, $other_where_terms_values );
		$subjects_array = $sqlsrv->query( $subjects_array_sql, $values );

		// summary sql 
		$summary_sql = "
		SELECT COUNT(*) as found_count
		FROM inbox_image
		WHERE 
		$filter_where
		$other_where_terms
		" . 
		(
			'grouped' == $data->mode ? 
				( " GROUP BY SUBJECT " . $safe_sort . ' ' ) : 
				" "
		) 
		;
		// get count total messages ( as filtered and/or grouped )
		$found_result = $sqlsrv->query( $summary_sql, $values );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-arrived ' : ' last-arrived ';
		$loaded_object = 'grouped' == $data->mode ? 'subject lines' :   'messages';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$from_summary = (  $subject->count > 1 ? ('(' . $subject->count . ') ') : ''   ) . $subject->from . ( $subject->many ? ' +' : '' );
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
					'<li class = "subject-line-item message-ID">' . safe_html($subject->ID) . '</li>' .
					'<li class = "subject-line-item from-summary">' . safe_html( $from_summary). '</li>' . // just display
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . safe_html($subject->count) . '</span></li>' . // *supports multiple UI elements*
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . safe_html($subject->subject) . '</span></li>' . // just display
					'<li class = "subject-line-item oldest" title="Date of oldest">' . safe_html($subject->oldest) . '</li>' . // just display
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . safe_html( $view_statement ). '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">No archived message with from email address or subject line containing "' .
					safe_html( $filter ) . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No archived messages.</h1>');
		}


		// construct inbox title
		$inbox_header = 
			'Archived: ' . 
			( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}
	
	public static function load_saved ( $dummy_id, $data ) { 

		/*
		* looks like inbox to js and css
		* 
		*/
		global $sqlsrv;

		$filter = utf8_string_no_tags ( $data->filter );
		$filter_where_values = array();
		if ( $filter > '' ) {
			$filter_where = " PATINDEX( ?, p.post_title ) > 0 AND ";
			$filter_where_values[] = '%' . $filter . '%';
		} else {
			$filter_where = '';
		}
		
		// selection limited by inner join on xref
		$other_where_terms = ' p.OFFICE = ?';
		$other_where_terms_values = array( get_office());

		// set max count (fixed )
		$max_count = 50;
 		// set page variable
 		$page_base = $data->page * $max_count;
		
		// assemble sql statements -- two version of SELECT, one for ungrouped, one for grouped
		$safe_sort = ($data->sort == 'ASC' ? 'ASC' : 'DESC');
		// note that for pro_con_value IIF conditional, all values must be string or '' is coerced to 0
		$subjects_array_sql = 
			(
			'grouped' != $data->mode ?
			" 
			SELECT 
				p.ID,
				p.post_title as subject,
				IIF( p.reply = p2.id, 
					'',
					IIF(  p.reply0 = p2.id, '0', '1' ) 
				) as pro_con_value,
				p2.id as reply_id,
				p2.last_updated_time as oldest,
				1 as count

			"
			:
			"
			SELECT 
				max(p.ID),
				max(p.post_title) as subject,
				string_agg( 
					IIF( p.reply = p2.id, 
						'',
						IIF(  p.reply0 = p2.id, '0', '1' ) 
					)	 
					, ',' ) as pro_con_value,
				max(p2.id) as reply_id,
				min( p2.last_updated_time )  as oldest,
				count( p.ID ) as count
			"
			) . 
			"
			FROM issue p inner join issue p2 on p.reply = p2.id or p.reply0 = p2.id or p.reply1 = p2.id
			WHERE 
				$filter_where
				$other_where_terms
			" . 
			(
				'grouped' == $data->mode ? 
					( " GROUP BY p.post_title ORDER BY MIN( p2.last_update_time )  " . $safe_sort . ' ' ) : 
					( " ORDER BY p2.last_updated_time  " . $safe_sort . ' ' )
			) .
			"
			OFFSET $page_base ROWS FETCH NEXT $max_count ROWS ONLY					
			"
			;

		$values = array_merge ( $filter_where_values, $other_where_terms_values );
		// get subjects array
		$subjects_array = $sqlsrv->query( $subjects_array_sql, $values );
		// get count total messages ( as filtered and/or grouped )
		$summary_sql = 
				
			" 
			SELECT count(*) as found_count
			FROM 
				(
				SELECT " . ( 'grouped' == $data->mode ? " MAX(p.ID) AS ID " : " p.ID " ) .
				"
				FROM issue p inner join issue p2 on p.reply = p2.id or p.reply0 = p2.id or p.reply1 = p2.id
				WHERE 
					$filter_where
					$other_where_terms
				" . 
				(
					'grouped' == $data->mode ? 
						( " GROUP BY p.post_title " ) : 
						(  ' ' )
				) .
				") inner_group"
			;
		$found_result = $sqlsrv->query( $summary_sql, $values );	
		$found_count = $found_result[0]->found_count;
		
		// choose terms based on parms for use in both branches of the conditional
		$sort_order = ( 'ASC' == $data->sort ) ? ' first-modified ' : ' last-modified ';
		$loaded_object = 'grouped' == $data->mode ? 'issues' :   'issue/pro-con';
		$filter_statement = $filter ? ' (filtered by "' . $filter . '")' : '';
		$view_statement = "Viewing $loaded_object, $sort_order first" . "$filter_statement. $max_count per page." ;

		// get pro_con options
		
		$option_array = WIC_Entity_Activity::get_option_group( 'pro_con_options' );
		

		// process subject array
		$count_subjects = 0;
		if ( $subjects_array ) {
			$output = '<ul id="inbox-subject-list">';
			foreach ( $subjects_array as $subject  ) {
				$pro_con_values = explode ( ',' , $subject->pro_con_value );
				$pro_con_label = ''; 
				$later = false;
				foreach ( $pro_con_values as $pro_con_value ) {
					if ( false === $later  ) {
						$later = true;
					} else {
						$pro_con_label .= '|';
					}
					$pro_con_label .=  safe_html(value_label_lookup ( $pro_con_value, $option_array ) ) ;
				}
				// format an li for the inbox
				$output .= '<li class="inbox-subject-line "><ul class="inbox-subject-line-inner-list">' . // *class determines sweepability*
					'<li class = "subject-line-item message-ID">' . safe_html($subject->ID ) . '</li>' .
					'<li class = "subject-line-item reply-ID">' . safe_html($subject->reply_id) . '</li>' .
					'<li class = "subject-line-item subject"><span class="actual-email-subject">' . safe_html($subject->subject) .  '</span>' . ( $pro_con_label ? ' (' : '') .  // passed through to display if click
					'<span class = "from-summary">' . $pro_con_label . '</span>' . ( $pro_con_label ? ')' : '') . '</li>' . // passed through to display if clicked
					'<li class = "subject-line-item pro-con-value">' . safe_html($pro_con_value) . '</li>' . // used in reply retrieval
					'<li class = "subject-line-item count" title = "Message Count"><span class="inner-count">' . safe_html($subject->count) . '</span></li>' . // *supports multiple UI elements*
					'<li class = "subject-line-item oldest" title="Date of oldest">' . safe_html($subject->oldest) . '</li>' . // just display
				'</ul></li>';
				$count_subjects++;
			}
			$output .= '</ul>';
			/*
			* assemble page links and explanatory legend at end of inbox display
			*
			*/
			$output .= 
				'<div id = "wic-inbox-list-footer">' .
					'<div class = "wic-inbox-footer-legend">' . safe_html($view_statement) . '</div>' . 
				'</div>';
		// no messages found
		} else {
			$output = $filter  ? 
				( '<div id = "filtered-all-warning">No saved reply standards with titles containing "' . safe_html($filter) . '".</div>' ) : 
				('<div id="inbox-congrats"><h1>No saved reply standards.</h1>');
		}


		// construct inbox title
		$inbox_header = 
			'Saved Reply Standards: ' . 
			( $page_base + 1 ) . '-' . ( $page_base + $count_subjects ) . ' of ' . $found_count . ' ' . $loaded_object; 
		
		$load_parms = (object) array ( 
			'filter'				=> 	 $filter,
			'page_ok'				=>	 ( $found_count > $page_base || 0 == $found_count ), // flag in case pages have shifted through record consolidation
		);		

		$return_array = (object) array (
			'inbox' => $output,
			'inbox_header' => $inbox_header,
			'nav_buttons' => array ( 'disable_prev' => $data->page == 0, 'disable_next' => ( $page_base + $max_count > $found_count ) ),
			'stuck' => false,
			'connection' => false,
			'last_load_parms' => $load_parms,
		);
		
		return array ( 'response_code' => true, 'output' => $return_array  ); 
	}
	
	
	protected static $entity_dictionary = array(
				
		'add_cc'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'case_assigned'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Assigned Staff:',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_user_array',),
		'constituent_id'=> array(
			'entity_slug' =>  'email_inbox',
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
		'include_attachments'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'issue'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '1',
			'field_type' =>  'selectmenu_issue',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Issue',
			'option_group' =>  'get_issue_options',),
		'message_bcc'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'message_cc'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'message_subject'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'message_to'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'multi_email',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'pro_con'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Pro/Con value to be recorded for incoming emails (optional):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Pro/Con?',
			'option_group' =>  'pro_con_options',),
		'search_subjects_phrase'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Search subject lines and mapped issues.',
			'option_group' =>  '',),
		'subject'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  ' Filter email subjects',
			'option_group' =>  '',),
		'working_template'=> array(
			'entity_slug' =>  'email_inbox',
			'hidden' =>  '0',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),

   );

   public static $option_groups = array(
	'pro_con_options'=> array(
	  array('value'=>'0','label'=>'Pro',),
		  array('value'=>'1','label'=>'Con',),
		  array('value'=>'','label'=>'Pro/Con?',)),
   
  );


}