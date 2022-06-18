<?php
/**
*
* class-wic-entity-dashboard.php
*/


class WIC_Entity_Dashboard extends WIC_Entity_Parent {
	
	/**************************************************************************************************
	*
	* Dashboard display and dashboard action functions	
	*
	***************************************************************************************************/	
	
	protected function set_entity_parms ( $args ) {}
	
	
	protected function dashboard () { 
		global $current_user;
		// get sort order and what is open 
		$config = $current_user->get_preference ( 'wic_dashboard_config' );
		if ( $config ) {
			$sort_list = array_flip ( $config->sort ); // e.g., $sort_list['dashboard_activity'] = 0 . . .
		} else {
			$sort_list = array();
		}	
		if ( $current_user->current_user_authorized_to ('all_crm' ) )	{
			// inventory of dashboard widgets and titles
			$dashboard_divs = array (
				'dashboard_overview'		=> 'Staff Work Status',
				'dashboard_issues'			=> 'Assigned Issues', 
				'dashboard_cases' 			=> 'Assigned Cases', 
				'dashboard_activity'		=> 'Constituents with Activity by Issue',
				'dashboard_activity_type'	=> 'Activities by Issue and Type',
				'dashboard_recent' 			=> 'Recently Updated',
				'dashboard_searches' 		=> 'Search Log',
				'dashboard_uploads' 		=> 'Uploads', 
			);
		} else {
			// inventory of dashboard widgets and titles
			$dashboard_divs = array (
				'dashboard_overview'		=> 'Staff Work Status',
				'dashboard_myissues'		=> 'Assigned Issues', 
				'dashboard_mycases' 		=> 'Assigned Cases', 
				'dashboard_recent' 			=> 'Recently Updated', 
			);
		}
		// sort the inventory
		$sorted_dashboard_divs = array();
		$unconfigged_div_counter = 100;
		foreach ( $dashboard_divs as $id => $title ) {
			if ( isset ( $sort_list[$id] ) ) {
				$new_index =  $sort_list[$id] ;
			} else {
				$new_index = $unconfigged_div_counter;
				$unconfigged_div_counter++;
			}
			$sorted_dashboard_divs[$new_index] = array( $id, $title );	
		} 
		ksort ( $sorted_dashboard_divs );
		// identify which entity to start as open
		$open_div = isset ( $config->tall ) ? ( isset ( $config->tall[0] ) ?  $config->tall[0] : 'dashboard_overview' ) : 'dashboard_overview';
		echo '<ul id="dashboard-sortables" >';
		foreach ( $sorted_dashboard_divs as $key => $dashboard_div ) {
			$tall_class = $open_div == $dashboard_div[0] ? ' wic-dashboard-tall ' : ''; 
			echo 
				'<li id = "' . $dashboard_div[0] . '" class = "ui-state-default wic-dashboard  wic-dashboard-full  ' . $tall_class . '">' .
					'<div class="wic-dashboard-title wic-dashboard-drag-handle" title="Drag to reorder dashboard widgets"><span class="dashicons dashicons-move"></span>' . $dashboard_div[1] . '</div>' .
					$this->special_buttons ( $dashboard_div[0], $config ) .
					'<button class="wic-dashboard-title wic-dashboard-refresh-button" type="button" title="Refresh"><span class="dashicons dashicons-update"></span></button>' .
					'<div class = "wic-inner-dashboard" id="wic-inner-dashboard-' . $dashboard_div[0] . '">' . 
						'<img src="' .  WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif">' .
					'</div>' . 
				'</li>' 
			;			
		}
		echo '</ul>';
	}


	public static function save_dashboard_preferences ( $dummy_id, $data ) { 
		global $current_user;
		$current_user->set_wic_user_preference ( 'wic_dashboard_config', $data );
	}

	public static function dashboard_overview ( $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		global $sqlsrv;

		$constituent_sql = "
			 SELECT case_assigned as user_id, count(id) as cases_open, sum(iif( case_review_date < [dbo].[easternDate](), 1, 0)) as cases_overdue 
			 FROM constituent 
			 WHERE case_status = 1 AND OFFICE = ? GROUP BY case_assigned
		";
	
		$issue_sql = "
			SELECT issue_staff as user_id, count(id) as issues_open, sum(iif(review_date < [dbo].[easternDate](), 1, 0)) as issues_overdue 
			FROM issue p
			WHERE follow_up_status = 'open' AND OFFICE = ?
			GROUP BY issue_staff
		";

		$message_sql = "
			SELECT 
				inbox_defined_staff as user_id, 
				count(id) as count_assigned_messages, 
				sum(iif( 0 = inbox_defined_reply_is_final, 1, 0 )) as unfinalized_messages,  
				left( min(iif( 0 = inbox_defined_reply_is_final, email_date_time, '2200-01-01' )), 10) as oldest_unanswered
			FROM inbox_image WHERE inbox_defined_staff > 0 AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0 AND
				folder_uid > 0
				AND OFFICE = ?
				GROUP BY inbox_defined_staff
			";	
		// get values
		$sqlsrv->query ( $constituent_sql, array( get_office() ) );
		$case_assigned_array 		= $sqlsrv->last_result;
		$sqlsrv->query ( $issue_sql, array(get_office() ) );
		$issue_assigned_array 		= $sqlsrv->last_result;
		$sqlsrv->query( $message_sql, array( get_office()  ) );
		$message_assigned_array 	= $sqlsrv->last_result;
		// construct array of user ids to report on
		$user_ids = array();
		if ( $case_assigned_array ) {
			foreach ( $case_assigned_array as $line ) {
				if ( !array_key_exists ( $line->user_id, $user_ids ) ) {
					$user_ids[$line->user_id] = array();
				}
				$user_ids[$line->user_id]['cases_open'] = $line->cases_open;
				$user_ids[$line->user_id]['cases_overdue'] = $line->cases_overdue;
			}
		}
		if ( $issue_assigned_array ) {
			foreach ( $issue_assigned_array as $line ) {
				if ( !array_key_exists (  $line->user_id, $user_ids ) ) {
					$user_ids[$line->user_id] = array();
				}
				$user_ids[$line->user_id]['issues_open'] = $line->issues_open;
				$user_ids[$line->user_id]['issues_overdue'] = $line->issues_overdue;
			}
		}
		if ( $message_assigned_array ) {
			foreach ( $message_assigned_array as $line ) {
				if ( !array_key_exists ( $line->user_id, $user_ids ) ) {
					$user_ids[$line->user_id] = array();
				}
				$user_ids[$line->user_id]['count_assigned_messages'] = $line->count_assigned_messages;
				$user_ids[$line->user_id]['unfinalized_messages'] = $line->unfinalized_messages;
				$user_ids[$line->user_id]['oldest_unanswered'] = $line->oldest_unanswered;

			}				
		}
		if ( !count ( $user_ids ) ) {
			return array ( 'response_code' => true, 'output' => '<div class="dashboard-not-found">No work assignments found.</div>' );	
		}
		
		global $current_user;
		$users = $current_user->office_user_list(); // user-id keyed array of objects

		$user_login_array = array();
		$deleted_user_counter = 0;
		foreach ($user_ids as $user_id => $values ) {
			$user_display_name = false;
			if ( $user_id > 0 ) {
				$user_data = isset( $users[$user_id] ) ?  $users[$user_id] : false;
				if ( $user_data ) {
					$user_display_name = isset( $user_data->user_name ) &&  $user_data->user_name  ? $user_data->user_name : $user_data->user_email;
				} else {
					$deleted_user_counter++;
					$user_display_name = 'Deleted_User_' . $deleted_user_counter;
				}
			} else {
				$user_display_name = 'Unassigned';
			}
			$user_login_array[$user_display_name] = $values;
		}
		
		ksort ( $user_login_array );
		
		$output = '<table id="wic-work-flow-status">
			<tr>
				<th>User Id</th>
				<th>Open Cases</th>
				<th>Overdue Cases</th>
				<th>Open Issues</th>
				<th>Overdue Issues</th>
				<th>Assigned Messages</th>
				<th>Unanswered Messages</th>
				<th>Oldest Unanswered</th>
			</tr>';
		foreach ( $user_login_array as $user_login => $values ) {
			$output .= '<tr>' .
				'<td>' . $user_login . '</td>' .
				'<td>' . ( isset( $values['cases_open'] ) ? $values['cases_open']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['cases_overdue'] ) && $values['cases_overdue'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">' . ( isset( $values['cases_overdue'] ) ? $values['cases_overdue']: 0 ) . '</td>' .
				'<td>' . ( isset( $values['issues_open'] ) ? $values['issues_open']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['issues_overdue'] ) && $values['issues_overdue'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">' . ( isset( $values['issues_overdue'] ) ? $values['issues_overdue']: 0 ) . '</td>' .
				'<td>' . ( isset( $values['count_assigned_messages'] ) ? $values['count_assigned_messages']: 0 ) . '</td>' .
				'<td class="' . ( isset( $values['unfinalized_messages'] ) && $values['unfinalized_messages'] > 0 ? 'wic-staff-overdue-assignment' : '' ) . '">'  . ( isset( $values['unfinalized_messages'] ) ? $values['unfinalized_messages']: 0 ) . '</td>' .
				'<td class="wic-email-dashboard-date">'  . ( isset( $values['oldest_unanswered'] ) &&  $values['oldest_unanswered'] != '2200-01-01' ? $values['oldest_unanswered']: '--' ) . '</td>' .
			'</tr>';
		}
		$output .= "</table>";

		return array ( 'response_code' => true, 'output' => $output );	
	}
	
	// display a list of CASES assigned to user --  (SEE ONLY OWN, NO OPTIONS)	
	public static function dashboard_mycases( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );

		$user_ID = get_current_user_id();	
		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 200,
			'select_mode'		=> 'id',
		);

		$search_array = array (
			array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  $user_ID, 
				 'compare'	=> '=', 
				 
			),
			array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_status',
				 'value'	=> '0', 
				 'compare'	=> '!=', 
				 
			), 
		);

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$wic_query->list();
		$wic_query->result = $wic_query->list_result;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No cases assigned.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, 'My Cases: ' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	}
		
	// display a list of issues assigned to user --  (SEE ONLY OWN, NO OPTIONS)	
	public static function dashboard_myissues( $dummy_id, $data ) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
		
		$user_ID = get_current_user_id();	
		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'issue' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 200,
			'select_mode'		=> '*',
		);

		$search_array = array (
			array (
				 'table'	=> 'issue',
				 'key'	=> 'issue_staff',
				 'value'	=> $user_ID,
				 'compare'	=> '=', 
				 
			),
			array (
				 'table'	=> 'issue',
				 'key'	=> 'follow_up_status',
				 'value'	=> 'closed', 
				 'compare'	=> '!=', 
				 
			), 
		);

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No issues assigned.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Issue' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,  'My Issues: ' );
			return array ( 'response_code' => true, 'output' => $list);					
		} 
	}

	// display a list of assigned cases -- default is to current user
	public static function dashboard_cases( $dummy_id, $data) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		extract ( (array) $data->dashboard_cases ); // case_assigned/case_review_date/case_status

		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 200,
			'select_mode'		=> 'id',
		);
		
		
		$search_array = array();
		if ( 'any' == $case_assigned ) {
			$assigned_term = array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  '0', 
				 'compare'	=>  '>', 
				 
			);
			array_push ( $search_array, $assigned_term );
		} else { 
			$assigned_term = array (
				 'table'	=> 'constituent',
				 'key'	=> 'case_assigned',
				 'value'	=>  $case_assigned, 
				 'compare'	=>  '=', 
				 
			);
			array_push ( $search_array, $assigned_term );		
		} // no search term if all
		
		// $case_status = '' means all, so no status selection
		if ( $case_status !== '' ) {
			$status_term = array (
				'table'	=> 'constituent',
				'key'	=> 'case_status',
				'value'	=> substr($case_status,0,1), // value 1OD is open over due 
				'compare'	=> '=', 
				
			); 		
			array_push ( $search_array, $status_term );
		}
		// selecting over due cases
		if ( '1OD' == $case_status  ) {
			$today = new DateTime('now');
			$today_ymd = date_format( $today, 'Y-m-d' );
			$review_term = array (
				 'table'	=> 'constituent',
				 'key'		=> 'case_review_date',
				 'value'	=> $today_ymd, 
				 'compare'	=> '<=', 
				 
			); 		
			array_push ( $search_array, $review_term );		
		}
		
		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		$wic_query->list();
		$wic_query->result = $wic_query->list_result;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No cases found -- check search criteria.' . '</div>' );		
		} elseif ( 200 < $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'Over 200 cases found -- use the advanced search function.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, 'My Cases: ' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	} 
		
	// display a list of assigned issues -- default is to current user	
	public static function dashboard_issues( $dummy_id, $data ) { 
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		extract ( (array) $data->dashboard_issues ); // issue_staff/review_date/follow_up_status:		
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'issue' );

		$search_parameters= array(
			'sort_order' => true,
			'compute_total' => false,
			'retrieve_limit' 	=> 200,
			'select_mode'		=> '*',
		);

		$search_array = array();
		if ( 'any' == $issue_staff ) {
			$assigned_term = array (
				'table'	=> 'issue',
				'key'	=> 'issue_staff',
				'value'	=> 0,
				'compare'	=> '>',
					 
			);
			array_push ( $search_array, $assigned_term );
		} else { // blank or non-blank 
			$assigned_term = array (
				'table'		=> 'issue',
				'key'		=> 'issue_staff',
				'value'		=> $issue_staff,
				'compare'	=> '=',
					 
			);
			array_push ( $search_array, $assigned_term );		
		}

		// $follow_up_status = '' means all, so no status selection
		if ( $follow_up_status !== '' ) {		
			$status_term = array (
				'table'	=> 'issue',
				'key'	=> 'follow_up_status',
				'value'	=> ('openOD' == $follow_up_status ) ? 'open' : $follow_up_status, 
				'compare'	=> '=', 
			); 		
			array_push ( $search_array, $status_term ); 
		}

		if ( 'openOD' == $follow_up_status  ) {
			$today = new DateTime('now');
			$today_ymd = date_format( $today, 'Y-m-d' );
			$review_term = array(
				'table'	  => 'issue',
				'key'     => 'review_date',
				'value'   => $today_ymd,
				'compare' => '<=',
			);
			array_push ( $search_array, $review_term );		
		}

		$wic_query->search ( $search_array, $search_parameters ); // get a list of id's meeting search criteria
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No issues found -- check search criteria.' . '</div>' );		
		} elseif ( 200 < $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'Over 200 issues found -- use the advanced search function.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Issue' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,  'My Issues: ' );
			return array ( 'response_code' => true, 'output' => $list);					
		} 
	}


	public static function dashboard_recent( $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		$user_ID = get_current_user_id();	
		
		// create a shell query object, but do the query directly and set up the query object with results to do listing
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );
		global $sqlsrv;
		$sql = "
		SELECT c.ID as ID, first_name, middle_name, last_name,case_status, case_review_date,
			max(phone_number) as phone,
			max(email_address) as email,
			max(concat(address_line,'|',city,'|',zip)) as address
		FROM  constituent c
			LEFT JOIN address a on a.constituent_id = c.id
			LEFT JOIN phone p on p.constituent_id = c.id
			LEFT JOIN email e on e.constituent_id = c.id
		WHERE c.OFFICE = ? AND c.last_updated_by = ?
		group by c.id, first_name, middle_name, last_name,case_status, case_review_date, c.last_updated_time
		ORDER BY c.last_updated_time DESC OFFSET 0 ROWS FETCH NEXT 20 ROWS ONLY
		";
		$sqlsrv->query( $sql, array( get_office(), $user_ID ) );
		$wic_query->result = $sqlsrv->last_result;

		$wic_query->found_count = $sqlsrv->num_rows;
		if ( !$sqlsrv->num_rows ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No constituents updated.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Constituent' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
	
	}

	// display user's search log ( which includes form searches, items selected from lists and also items saved )
	public static function dashboard_searches(  $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'search_log' );
		$wic_query->retrieve_search_log_latest();
		$sql = $wic_query->sql;
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' =>  '<div class="dashboard-not-found">' . 'Search log new or purged.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Search_Log' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, '' );
			return array ( 'response_code' => true, 'output' => $list);				
		}
	}

	public static function dashboard_uploads (  $dummy_id, $data ) {
	
		self::save_dashboard_preferences ( $dummy_id, $data );
	
		// table entry in the access factory will make this a standard WIC DB object
		$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( 'upload' );
		// select uploads that are beyond copied stage
		$wic_query->search (  
				array (
					array (
						'table'	=> 'upload',
						'key'	=> 'upload_status',
						'value'	=> 'copied',
						'compare'	=> '!=', 
						
					),
				),	
				array( 'retrieve_limit' => 200, 'sort_order' => true ) 
			);
		if ( 0 == $wic_query->found_count ) {
			return array ( 'response_code' => 'true', 'output' =>  '<div class="dashboard-not-found">' . 'Upload log new or purged.' . '</div>' );		
		} else {
			$lister_class = 'WIC_List_Upload' ;
			$lister = new $lister_class;
			$list = $lister->format_entity_list( $wic_query, '' ); 
			return array ( 'response_code' => true, 'output' => $list);	
		}
	}
	
	
	
	private function special_buttons ( $dashboard_div, $config ) { 
		
		if ( 'dashboard_activity'  == $dashboard_div ) {
			$dashboard_activity_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$dashboard_activity_control->initialize_default_values( 'dashboard', 'date_range', 'date_range' );
			$dashboard_activity_control->set_value('last30'); // initial config
			if ( isset ( $config->dashboard_activity) && isset($config->dashboard_activity->date_range) ) {
				$dashboard_activity_control->set_value( $config->dashboard_activity->date_range );
			}
			return 	$dashboard_activity_control->form_control() . WIC_Entity_Activity::make_activity_type_filter_button();
		} elseif ( 'dashboard_activity_type'  == $dashboard_div ) {
			$dashboard_activity_control_t = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$dashboard_activity_control_t->initialize_default_values( 'dashboard', 'date_range', 'date_range_t' );
			$dashboard_activity_control_t->set_value('last30'); // initial config
			if ( isset ( $config->dashboard_activity_type ) && isset($config->dashboard_activity_type->date_range ) )  {
				$dashboard_activity_control_t->set_value( $config->dashboard_activity_type->date_range );
			}
			return $dashboard_activity_control_t->form_control();
		} elseif ( 'dashboard_cases'  == $dashboard_div ) {
			$assigned_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$assigned_control->initialize_default_values( 'dashboard', 'case_assigned', 'case_assigned' );
			$assigned_control->set_value ( get_current_user_id() );
			$status_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$status_control->initialize_default_values( 'dashboard', 'case_status', 'case_status' );
			$status_control->set_value ( '1' ); // initial config
			if ( isset ( $config->dashboard_cases->case_assigned ) ) {
				$assigned_control->set_value( $config->dashboard_cases->case_assigned );
			}
			if ( isset ( $config->dashboard_cases->case_status ) ) {
				$status_control->set_value( $config->dashboard_cases->case_status );
			}		
			return  $assigned_control->form_control() . $status_control->form_control();
		} elseif ( 'dashboard_issues'  == $dashboard_div ) {
			$assigned_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$assigned_control->initialize_default_values( 'dashboard', 'issue_staff', 'issue_staff' );
			$assigned_control->set_value ( get_current_user_id() );
			$status_control = WIC_Control_Factory::make_a_control ( 'selectmenu' );
			$status_control->initialize_default_values( 'dashboard', 'follow_up_status', 'follow_up_status' );
			$status_control->set_value ( 'open' ); // initial config
			if ( isset ( $config->dashboard_issues->issue_staff ) ) {
				$assigned_control->set_value( $config->dashboard_issues->issue_staff );
			}
			if ( isset ( $config->dashboard_issues->follow_up_status ) ) {
				$status_control->set_value( $config->dashboard_issues->follow_up_status );
			}
			return  $assigned_control->form_control() . $status_control->form_control();
		} else {
			return '';
		}
	}



	public static function dashboard_activity ( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );
		extract ( (array) $data->dashboard_activity );
		extract(self::parse_date_range_option ( $date_range ), EXTR_OVERWRITE ); // set $start and $end (overriding any converted old values in preference)

		$type_string = '';
		$first = ''; 
		$values = array( $end, $start );
		// get the right number of parameter opening ?'s and the right additional values array elements
		foreach ( $included as $type ) {
			$type_string .= ( $first . '?'); 
			$first = ',';
			$values[] = $type;
		}
		$values[] = get_office();
		$in_clause = count ( $included ) > 0 ? " activity_type IN ( $type_string ) " : "  activity_type IS NULL "; // no null values

		// set global access object 
		global $sqlsrv;

		$join = 'activity inner join constituent c on c.id = activity.constituent_id inner join issue on issue.ID = activity.issue';
		$sql = "
			WITH activity_summary AS (
				SELECT constituent_id, issue, max(pro_con) as pro_con,  post_title, review_date,follow_up_status,post_category, issue_staff
				FROM $join
				WHERE activity_date <= ? and activity_date >= ? and $in_clause AND c.OFFICE = ?
				GROUP BY activity.constituent_ID, activity.issue,  post_title, review_date,follow_up_status,post_category, issue_staff
				)
			SELECT TOP 50 
					issue as id, 
					post_title,
					review_date,
					follow_up_status,
					post_category,
					issue_staff,
					count(constituent_id) as total, 
					sum( iif (pro_con = '0', 1, 0) ) as pro,  
					sum( iif (pro_con = '1', 1, 0) ) as con  
				FROM activity_summary 
				GROUP BY issue, post_title, review_date,follow_up_status,post_category, issue_staff
				ORDER BY count(constituent_id) DESC
				";

		$sqlsrv->query( $sql, $values );

		$wic_query = (object) array ( 
			'result' => $sqlsrv->last_result,
			'entity' => 'trend',
			'showing_count' => $sqlsrv->num_rows,
		);

		if ( 0 == $sqlsrv->num_rows ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No activities found.' . '</div>' );		
		} else { 
			$lister = new WIC_List_Trend;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' =>  $list);			
		}
		
	}


	public static function dashboard_activity_type ( $dummy_id, $data ) {

		self::save_dashboard_preferences ( $dummy_id, $data );
		extract ( (array) $data->dashboard_activity_type );
		extract(self::parse_date_range_option ( $date_range ), EXTR_OVERWRITE ); // set $start and $end (overriding any converted old values in preference)		


		// set global access object 
		global $sqlsrv;

		// get activity types (THESE ARE USER DEFINED VALUES AND LABELS AND ARE PARAMETRIZED IN THE MAIN QUERY BELOW)
		$option_list = WIC_Entity_Activity::get_option_group('activity_type_options');
		// pass type list to prepare select terms for each type, array of fields mapped to query values, and string of types
		$term_string = 'issue as id, count(activity_type) as total ';
		$fields = array ( array ( 'Issue', 'id' ), array ( 'Total', 'total') ); // headers for the ultimate display list
		$type_string = '';
		$first = '';
		$values1 = 
		$values2 = array();
		foreach ( $option_list as $type ) {
			$value = $type['value'];
			if ( ! $value ) continue; // skip any blanks
			$label = 'type' . $value;
			$term_string .= ", sum( iif( activity_type = '$value', 1, 0 ) ) as $label";
			$fields[] = array ( $type['label'], $label ); 
			$type_string .= $first . "'$value'"; 
			$first = ',';
		}
		$values = array();
		$values[] = $end;
		$values[] = $start;
		$values[] = get_office();
		// prepare extra term for not found type ( i.e., type code option was eliminated after start of search period );
		$term_string .= ", sum( iif ( activity_type NOT IN( $type_string ), 1, 0 ) ) as nf";
		$fields[] = array ( 'NF type', 'nf' );
		
		$activity_sql = "
			SELECT $term_string 
			FROM  activity a 
			WHERE activity_date <= ? and activity_date >= ? AND OFFICE = ?
			GROUP BY issue
			ORDER BY count(a.ID) DESC
			";

		$sqlsrv->query( $activity_sql, $values ); 

		$wic_query = (object) array ( 
			'result' => $sqlsrv->last_result,
			'entity' => 'trend',
			'showing_count' => $sqlsrv->num_rows,
			'fields' => $fields,
		);

		if ( !$sqlsrv->num_rows ) {
			return array ( 'response_code' => 'true', 'output' => '<div class="dashboard-not-found">' . 'No activities found.' . '</div>' );		
		} else { 
			$lister = new WIC_List_Activity_Type;
			$list = $lister->format_entity_list( $wic_query,'' );
			return array ( 'response_code' => true, 'output' => $list);			
		}
		
	}

	private static function  parse_date_range_option ( $option ) {

		if ( !$option ) {
			$option = 'last7';
		}

		$today = new DateTime('now');
		$today_ymd = date_format( $today, 'Y-m-d' );
		
		if ( 'last' == substr($option,0,4) ) {
			$end = '2100-01-01';
			$days = substr( $option,4 );
			$interval = new DateInterval( 'P' . $days . 'D');
			$today->sub( $interval );
			$start = date_format( $today, 'Y-m-d' );
		} elseif ( 'week' == $option ) {
			$end = '2100-01-01';
			$start = date ( 'Y-m-d', strtotime('last monday'));
		} elseif ( 'priorweek' == $option ) {
			// is today monday
			$today_is_monday = ('Mon' == date_format( $today, 'D'));
			if ( $today_is_monday ) {
				$end = date ( 'Y-m-d', strtotime('yesterday'));
				$end = date ( 'Y-m-d', strtotime('last monday'));
			} else {
				$last_monday = new DateTime ('last monday');
				$interval_one = new DateInterval( 'P1D');
				$interval_six = new DateInterval( 'P6D');
				$last_monday->sub($interval_one);
				$end = date_format( $last_monday, 'Y-m-d' );
				$last_monday->sub($interval_six);
				$start = date_format( $last_monday, 'Y-m-d' );
			}
		} elseif ( 'month' == $option ) {
			$end = '2100-01-01';
			$start = date ( 'Y-m-d', strtotime('first day of this month'));
		} elseif ( 'priormonth' == $option ) {
			$end = date ( 'Y-m-d', strtotime('last day of last month'));
			$start = date ( 'Y-m-d', strtotime('first day of last month'));
		} elseif ( 'year' == $option ) {
			$end = '2100-01-01';
			$year = date_format( $today, 'Y' );
			$start = $year . '-01-01';
		} elseif ( 'prioryear' == $option ) {
			$year = date_format( $today, 'Y' );
			$last_year = $year - 1;
			$end = $last_year . '-12-31';
			$start = $last_year . '-01-01';
		} else { // bad option sent (hacking?) gets week today
			$end = '2100-01-01';
			$start = date ( 'Y-m-d', strtotime('last monday'));
		}

		return array ( 'start' => $start, 'end' => $end );
	}


	public static $entity_dictionary = array (


		'case_assigned'=> array(
			'entity_slug' =>  'dashboard',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_user_array_dashboard',),
		'case_status'=> array(
			'entity_slug' =>  'dashboard',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'case_status_options',),
		'date_range'=> array(
			'entity_slug' =>  'dashboard',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'date_range_options',),
		'follow_up_status'=> array(
			'entity_slug' =>  'dashboard',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'follow_up_status_options',),
		'issue_staff'=> array(
			'entity_slug' =>  'dashboard',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_user_array_dashboard',),
	);

	public static $option_groups = array(
		'case_status_options'=> array(
			array('value'=>'1','label'=>'Open',),
			array('value'=>'1OD','label'=>'Open Due',),
			array('value'=>'0','label'=>'Closed',),
			array('value'=>'','label'=>'All',),
		),
		'follow_up_status_options'=> array(
			array('value'=>'open','label'=>'Open',),
			array('value'=>'openOD','label'=>'Open Due',),
			array('value'=>'closed','label'=>'Closed',),
			array('value'=>'','label'=>'All',),
		),
		'date_range_options' => array(
			array('value'=>'last7',		'label'	=>'Last 7 days',),
			array('value'=>'last30',	'label'	=>'Last 30 days',),
			array('value'=>'last90',	'label'	=>'Last 90 days',),
			array('value'=>'last365',	'label'=>'Last 365 days',),
			array('value'=>'week',		'label'	=>'Week to date',),
			array('value'=>'priorweek', 'label'	=>'Prior week',),
			array('value'=>'month',		'label'	=>'Month to date',),
			array('value'=>'priormonth','label'	=>'Prior month',),
			array('value'=>'year',		'label'	=>'Year to date',),
			array('value'=>'prioryear',	'label'	=>'Prior year',),
		),
	);
}

