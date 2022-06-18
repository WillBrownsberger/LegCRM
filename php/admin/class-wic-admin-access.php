<?php
/**
*
* class-wic-admin-access.php
*
*/


class WIC_Admin_Access {
	/*
	* This module checks capability levels of the current user's role, as defined in >Config>Security setting, against an array of required capability levels for particular class/modules.
	*
	* In addition, if the capability level is check_record, then the module tests whether the particular record is assigned to the current_user or $current_user->current_user_authorized_to (all_crm) 
	*
	* Records are assigned in the case management field group for issues and constituents or from the inbox for messages.
	*
	* GET security is mediated by Wordpress and $nav_array in WIC_Admin_Navigation, but this module is also called on GETS to verify record level access rules
	*
	* This module is primarily for authorizing calls to wpIssuesCRM ajax endpoints (both of which route only to classes in /php/entity) and the 3 upload and 2 download functions
	*
	* If ajax_class_method_to_auth_required[class] is not an array, string value is the capability level and applies to all methods within that class
	*    where ajax_class_method_to_auth_required[class] is an array, the capability level is method specific
	*
	* check_record means function is accessible to any with wp_issues_crm_access, but must check specific record
	*
	* function returns true or false -- calling navigation method must legcrm_finish on false
	*
	* check_security is a public function, but is called only from WIC_Admin_Navigation (9 methods) 

	*
	* the list_send capability is not enforced by this module but in email_send/search_link 
	*
	* email batch cron and geocode are secured by cron keys
	*/
	public static function check_security ( $entity, $action, $id, $data, $nonce = true ) { 
		/*
		*
		*
		* preliminary check in case misconfigured hierarchy of role capabilities
		*
		*/
		global $current_user;
		if ( ! $current_user->current_user_authorized_to (  'assigned_only' ) ) {
			return false;
		}
		/*
		*
		* EXCLUSIVE list of allowed GETS and ajax calls and their required capabilities
		*	also applied to get calls to do check_record level of security
		*	any GET or ajax requests not in this list will be rejected (return false from this method and legcrm_finish in calling method) 
		*/
	
		$ajax_class_method_to_auth_required = array (
			'activity' => array (
				'set_up_activity_area' 			=> 'check_record',
				'popup_save_update'				=> 'check_record',
				'popup_delete'					=> 'check_record',
				'reassign_delete_activities'	=> 'all_crm',
				'document'						=> 'check_record', // for document attachments -- coming from do_download 
			),
			// 'address' 						not taking ajax calls,
			// 'address_usps' 					not taking ajax calls,
			'advanced_search' 					=> 'all_crm',
			//'advanced_search_activity' 		not taking ajax calls,
			//'advanced_search_constituent' 	not taking ajax calls,
			//'advanced_search_constituent_having'=> not taking ajax calls,
			//'advanced_search_row'				not taking ajax calls,
			'autocomplete' 						=> '',
			'constituent' => array (
				'new_blank_form'				=> '',
				'id_search'						=> 'check_record',
				'hard_delete'					=> 'check_record',
				'list_delete_constituents'		=> 'all_crm',
				'form_save_update'				=> 'check_record'
			),
			'dashboard' => array(
				'dashboard'						=> '',
				'dashboard_overview'			=> '',
				'dashboard_mycases'				=> '',
				'dashboard_myissues'			=> '',
				'dashboard_recent'				=> '',
				'save_dashboard_preferences'	=> '',
				'dashboard_issues'				=> 'all_crm',
				'dashboard_cases'			 	=> 'all_crm',
				'dashboard_activity'		 	=> 'all_crm',
				'dashboard_activity_type'	 	=> 'all_crm',
				'dashboard_searches'		 	=> 'all_crm',
				'dashboard_uploads'			 	=> 'all_crm',
			),
			'download'							=> 'all_crm', // not really an ajax call -- allows this function to be used by do_download (note the 's')
			// 'all_email'							not taking ajax calls ( email address on constituent record, called within constituent function )
			'email_account' 					=> 'all_email', 
			'email_attachment' 					=> 'all_email',
			'email_block' 						=> 'all_email',
			'email_compose' 					=> 'all_email',
			'email_connect' 					=> 'all_email',
			// 'email_cron' 					secured with cron key
			'email_deliver' 					=> 'all_email',
			//'email_deliver_activesync'		not taking ajax calls,
			'email_inbox' => array (
				'new_blank_form'				=> '',
				'load_inbox'					=> 'check_record',
				'load_sent'						=> 'all_crm',
				'load_outbox'					=> 'all_crm',
				'load_draft'					=> 'all_crm',
				'load_done'						=> 'all_crm',
				'load_saved'					=> 'all_crm',
			),
			'email_inbox_parse' 				=> 'all_email',
			'email_inbox_synch'					=> 'all_email',
			'email_message' => array (			
				'load_message_detail'			=> 'check_record',
				'load_full_message'				=> 'check_record',
				'save_update_reply_template' 	=> 'check_record', // if have unassigned can use template buttons
				'delete_reply_template' 		=> 'all_email',
				'restore_reply_template' 		=> 'all_email',
				'get_reply_template' 			=> 'check_record', // if have unassigned can use template buttons
				'quick_update_inbox_defined_item' => 'check_record',
				'quick_update_constituent_id' 	=> 'check_record',
				'get_post_info'					=> '', // need this to load any email, but limit to title in function if not authorized 
				'new_issue_from_message'		=> 'check_record', // if have unassigned can use template buttons
			),	
			'email_oauth'						=> '', // not adding beyond general access requirement and gmail acc
			//'email_oauth_synch' 				not taking ajax calls,
			//'email_oauth_update'				not taking ajax calls,
			'email_process'						=> 'all_email',
			'email_send' 						=> 'all_email', // note: that list_send capability is checked within the send function
			'email_settings' 					=> 'all_email',	
			'email_subject' => array(
				'show_subject_list'				=> 'all_email',
				'delete_subject_from_list'		=> 'all_email',
				'manual_add_subject'			=> 'all_email',
			),
			'email_uid_reservation' 			=> 'all_email',
			'email_unprocess'					=> 'all_email',
			'geocode'							=> 'all_crm', 
			'issue'						 		=> 'check_record',
			// 'list'						 	not taking ajax calls
			// 'multivalue'						not taking ajax calls
			'office'							=> 'super',
			// 'option_value'					not taking ajax calls
			//'parent'						 	not taking ajax calls
			//'phone'							not taking ajax calls
			'post'								=> 'check_record', // synonym of issue of backward compatibility
			'search_box'						=> '',
			'search_log'						=> 'all_crm',
			'settings'							=> 'all_email',
			'synch'								=> 'all_email',
			'upload'						 	=> 'all_crm',
			'upload_complete'					=> 'all_crm',
			'upload_map'						=> 'all_crm',
			'upload_match'						=> 'all_crm',
			'upload_match_strategies'			=> 'all_crm',
			'upload_regrets'					=> 'all_crm',
			'upload_set_defaults'				=> 'all_crm',
			'upload_upload'						=> 'all_crm',
			'upload_validate'					=> 'all_crm',
			'user'	=> array(
				'set_wic_user_preference_wrap'	=> 'all_email',
			),
		);
	
		// uncomment dump for debugging
		// error_log ( "check_security for >$entity<, >$action<, >$id<, with data count or data string:" . ( ( is_array( $data ) || is_object ( $data ) )? print_r($data, true) : $data  ) );
		// end of debugging dump
		/*
		*
		* does this user have authority to access the requested $entity and $action
		*
		*/
		// entity must be in array or fail security -- force entry by developer ( if calling from ajax )
		if ( !array_key_exists ( $entity, $ajax_class_method_to_auth_required ) ) {
			return false;
		// string value is the level
		} elseif ( !is_array( $ajax_class_method_to_auth_required[$entity] ) ) {
			$security_level = $ajax_class_method_to_auth_required[$entity];
		// array means entity has functions at multi levels -- force entry by developer ( if calling from ajax )
		} elseif ( !array_key_exists( $action, $ajax_class_method_to_auth_required[$entity] ) ) {
			return false;
		} else {
			$security_level = $ajax_class_method_to_auth_required[$entity][$action];
		}

		$required_capability = 'check_record' == $security_level ? 'assigned_only' : $security_level;

		if ( ! $current_user->current_user_authorized_to ( $required_capability ) ) {
			return false;	
		}	
		/*
		*
		* does this user have authority to access THIS RECORD via the requested $entity and $action
		*
		* all_crm gives accesss to all constituents, activities, issues, but not to all email
		*/
		$current_user_can_view_unassigned = $current_user->current_user_authorized_to( 'all_crm' );		
		if ( 'check_record' == $security_level  ) {
			// record level checking requires different functions
			switch ( $entity ) {
				case 'activity':
					if ( ! $current_user_can_view_unassigned && ! self::current_user_can_access_this_activity_record ( $action, $id, $data ) ) {
						return false;
					}
					break;	
				case 'constituent':
					if (  !$current_user_can_view_unassigned && ! self::current_user_can_access_this_constituent_record ( $id ) ) {
						return false;
					}
					break;	
				case 'email_message':
					$current_user_can_view_all_email = $current_user->current_user_authorized_to( 'all_email' );
					//  issue creation and template assignment available if can view assigned or all email 
					if ( in_array ( $action, array ( 'save_update_reply_template', 'get_reply_template', 'new_issue_from_message' ) ) ) {
						if (
							!$current_user_can_view_unassigned && 
							!$current_user_can_view_all_email 
							) {
							return false;
						}
					} elseif (  !$current_user_can_view_all_email && ! self::current_user_can_access_this_email_message ( $action, $id, $data ) ) {
						return false;
					}
					break;	
				case 'issue':
					if (  !$current_user_can_view_unassigned &&! self::current_user_can_access_this_issue_record ( $id ) ) {
						return false;
					}
					break;			
			}
		}
		
		/*
		* at this point have passed all functional and record level screens or have already returned false
		*
		* try to prevent cross site scripting by checking nonce
		*
		* but no nonce on get page
		*/
		// $nonce is passed parameter defaulted to true; only false on menu GET ( WIC_Admin_Navigation::do_page() )
		if ( $nonce ) {
			if ( ! WIC_Admin_Setup::wic_check_nonce ( isset ( $_REQUEST['attachment_id'] ) ? $_REQUEST['attachment_id'] : '' ) )
				{ 
				 legcrm_finish ( 'Expired security code.  Please refresh page.' );		
			}		
		}		
		// passed all function and record level screen and, if doing nonce checking, passed nonce 
		return true; 	
				
	}

	private static function current_user_can_access_this_constituent_record ( $id ) { 

		if ( !$id ) {
			return true; // need this possibility for form_save_update on new record
		}		
		// now check assignment
		global $sqlsrv;
		$user_id = get_current_user_id();
		
		$constituent_vals = $sqlsrv->query ( "SELECT case_assigned, last_updated_by FROM constituent WHERE ID = ? ", array ( $id ) ) ;
		// if user without general access is requesting an id and it is not valid, no need to disclose that, just say unassigned
		if ( ! $constituent_vals ) {
			return false;
		} else {
			return in_array( $user_id, array( $constituent_vals[0]->case_assigned, $constituent_vals[0]->last_updated_by ) ); 
		}

	}

	private static function current_user_can_access_this_issue_record ( $id ) { 
		
		if ( ! $id ) {
			return false; // not supposed to be saving new
		}
		// now check assignment
		global $sqlsrv;
		$user_id = get_current_user_id();
		
		$issue_vals = $sqlsrv->query ( " SELECT issue_staff from issue WHERE id = ? ", array ( $id ) ); 
		if ( ! $issue_vals ) {
			return false;
		} else {
			return $user_id == $issue_vals[0]->issue_staff;
		}
	}

	// supports checking of document downloads and activity deletes
	private static function current_user_can_access_this_activity_record ( $action, $id, $data )  { // activity id

		switch ( $action ) {
			case 'set_up_activity_area':
				if ( 'constituent' == $data->parentForm && !$id ) {
					return true; // new constituent form -- blank area;
				}
				$activity_area_function = "current_user_can_access_this_{$data->parentForm}_record";
				return self::$activity_area_function( $id );
			case 'popup_save_update':
				if ( self::current_user_can_access_this_constituent_record ( $id ) ) {
					return true;
				} else {
					return self::current_user_can_access_this_issue_record( $id ); 
				}
			case 'popup_delete':
				return self::can_current_user_access_this_particular_activity_record( $id );
			case 'document': 
				return self::can_current_user_access_this_particular_activity_record($id);
		}

	}

	private static function can_current_user_access_this_particular_activity_record ( $id ) {

		// must have id		
		if ( ! $id ) {
			return false;
		}

		// now check assignemnt
		global $sqlsrv;
		$post_table = 'issue';
		$user_id = get_current_user_id();

		// check if assigned to constituent -- if so OK		
		$constituent_vals = $sqlsrv->query ( "SELECT case_assigned, c.last_updated_by FROM constituent c INNER JOIN activity a on a.constituent_id = c.id WHERE a.ID = ? ", array ( $id ) );
		// if user without general access is requesting an id and it is not valid, no need to disclose that, just say unassigned
		if ( $constituent_vals  &&  in_array( $user_id, array( $constituent_vals[0]->case_assigned, $constituent_vals[0]->last_updated_by ) ) ) {
			return true;
		} 
		
		// not constituent, try issue
		$issue_vals = $sqlsrv->query ( " SELECT issue_staff from issue INNER JOIN activity a on a.issue = id WHERE a.ID = ? ", array ( $id ) ); 
		if ( ! $issue_vals ) {
			return false;
		} else {
			return $user_id == $issue_vals[0]->issue_staff;
		}
	}


	private static function current_user_can_access_this_email_message 	( $action, $id, $data )	{ // for email, function is false only if people cannot access email ( could have email without otherwise accessing unassigned )
		switch ( $action ) {
			case 'load_inbox':
				return self::can_user_access_this_inbox_page ( $data );
			case 'load_message_detail': // from inbox, loading for reply
				return self::can_user_access_this_message ( $id );
			case 'load_full_message': // from activity records
				return self::can_user_access_this_page_message( $id, $data ); // $data is page as 0/1 or 'done'/'sent'
			case 'quick_update_inbox_defined_item': // from inbox reply
				return self::can_user_access_this_message ( $id );
			case 'quick_update_constituent_id' : // from inbox reply
				return self::can_user_access_this_message ( $id );
		}
	}
	
	private static function can_user_access_this_inbox_page ( $data) {
		if ( in_array( $data->tab, array( 'CATEGORY_ASSIGNED', 'CATEGORY_READY') ) ) {
			return true;
		} else {
			return $current_user->current_user_authorized_to ('all_email' );
		}
	}
	

	private static function can_user_access_this_message( $uid ) {
		global $sqlsrv;
		$user_id = get_current_user_id();

		$message_vals = $sqlsrv->query ("SELECT inbox_defined_staff FROM inbox_image WHERE folder_uid = ?" , array( $uid ) );
		if ( $message_vals && $user_id == $message_vals[0]->inbox_defined_staff ) {
			return true;
		} else {
			return false;	
		}	
	}

	private static function can_user_access_this_page_message( $message_id, $message_in_outbox ) {
	
		// translate 'page' to binary message in outbox if necessary
		if ( 0 != $message_in_outbox && 1 != $message_in_outbox ) {
			$message_in_outbox = 'done' == $message_in_outbox ? 0 : 1;
		}
	
	
		global $sqlsrv;
		$user_id = get_current_user_id();

		$activity_table = 'activity';
		$constituent_table = 'constituent';
		
		// if is an inbox attachment check for assigned message or assigned constituent
		if ( ! $message_in_outbox ) {
			$message_vals = $sqlsrv->query (  "SELECT inbox_defined_staff FROM inbox_image WHERE ID = ?" , array( $message_id )  ); // supports opening of attachments from inbox (treated as if from load_full_message)
			if ( $message_vals && $user_id == $message_vals[0]->inbox_defined_staff ) {
				return true;
			} else {
				$message_vals = $sqlsrv->query (  "SELECT case_assigned, c.last_updated_by FROM constituent c INNER JOIN activity a on a.constituent_id = c.id WHERE related_inbox_record = ?" , array( $message_id ) );
			}
		// if outbox, check only assigned constituent
		} else {
			$message_vals = $sqlsrv->query (  "SELECT case_assigned, c.last_updated_by FROM constituent c INNER JOIN activity a on a.constituent_id = c.id WHERE related_outbox_record = ?", array( $message_id )  );				
		}

		// constituent based return if did not return true on message
		if ( ! $message_vals ) {
			return false;
		} else {				
			return in_array( $user_id, array( $message_vals[0]->case_assigned, $message_vals[0]->last_updated_by ) );
		}
	}

	


}