<?php
/*
* class-wic-list-constituent-export.php
*
* 
*/ 

class WIC_List_Activity_Export extends WIC_List_Constituent_Export {

	public static function do_activity_download ( $download_type, $search_id ) { 
		
		// naming the outfile
		global $current_user;	
		$file_name = 'wic-activity-export-' . preg_replace( '#[^A-Za-z0-9]#', '', $current_user->get_display_name()) . '-' .  current_time( 'Y-m-d-H-i-s' )  .  '.csv' ;
		// create temp table
		self::create_temp_activity_list ( $download_type, $search_id );

		// do the export off the previously created temp table of activity id's	
		$sql = self::assemble_activity_export_sql( $download_type ); 
		self::do_the_export( $file_name, $sql );

		if ( 'activities' == $download_type  ) { 
			WIC_Entity_Search_Log::mark_search_as_downloaded ( $search_id );
		}

		exit;
	} 
	
	/*
	*
	* create temporary table with activity ids
	*
	*/
	public static function create_temp_activity_list ( $download_type, $search_id ) {
			
		if ( 'activities' == $download_type ) { // coming from logged advanced search
			$search = WIC_Entity_Search_Log::get_search_from_search_log ( array( 'id_requested' => $search_id ) );	
			$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( $search['entity'] );
			$meta_query_array = $search['unserialized_search_array'];
			$search_parameters = array (
				'select_mode' 		=> 'download', // with this setting, object will create the temp table that export sql assembler is looking for
				'sort_order' 		=> true,
				'compute_total' 	=> false,
				'retrieve_limit' 	=> 999999999,
				'redo_search'		=> true,
				'old_search_id'		=> $search_id,				
			);
			$wic_query->search ( $meta_query_array, $search_parameters );
		} else { // from activity list on constituent, issue or email form
			global $sqlsrv;
			$join =	"activity activity ";
			if ( 'issue' == $download_type ) {
				$join .= " left join " . "constituent c on c.id = activity.constituent_id"; 
				$where = " activity.issue = ? "; 
			} elseif ( 'constituent' == $download_type ) {
				$where =  " activity.constituent_id = ? ";
			} else {
				Throw new Exception("Error in download, invalid download request. Download type was $download_type.");
			}				 
			// structure sql with the where clause
			$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();			
			$sql = "
					SELECT activity.ID  
					INTO $temp_table
					from $join 
					WHERE $where
					ORDER BY activity_date desc, activity.last_updated_time 
					";
			$temp_result = $sqlsrv->query  ( $sql, array( $search_id ));
			if ( false === $temp_result ) {
				Throw new Exception('Error in download, likely permission error.  Logged for review.' );
			}			
		} 	
	
	}
	
	
	/*
	*	based on temporary table created early in transaction, creates second temporary table
	*  returns sql to read second table
	*  
	*/
	public static function assemble_activity_export_sql ( $download_type ) { 
		
		// reference global wp query object	
		global $sqlsrv;	
	
		// id list passed through user's temporary file wp_wic_temporary_id_list, lasts only through the server transaction (multiple db transactions)
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();
		
		// pass activity list with full data to export through second temp table
		$temp_activity_table = '##temporary_activity_list_' . time();		

		$registration_fields_string = WIC_List_Constituent_Export::registration_fields_string();

		// initialize download sql -- if remains blank, will bypass download
		$download_sql = '';
		// in_array check just for validation
		if (in_array( $download_type, array( 'constituent', 'issue','activities' ) ) ){
			$download_sql = 
			"
			WITH poss_dups as 
				(
			SELECT ac.id as acid, ROW_NUMBER() OVER (PARTITION BY ac.id ORDER BY email_address DESC, phone_number DESC, address_line DESC ) as non_blank_row,
					activity_date, activity_type, activity_amount, pro_con, file_name, file_size,
					Iif (wp.ID IS NOT NULL, wp.post_title, concat('Hard Deleted Post ( ID was ',ac.issue,' )' ) ) as post_title, 
					first_name as fn, last_name as ln, middle_name as mn, 
					city, 
					email_address, 
					phone_number,
					address_line as address_line_1,
					concat ( city, ', ', state, ' ',  zip ) as address_line_2,
					state, zip, is_deceased as deceased, gender, date_of_birth as dob, year_of_birth as yob, occupation, employer $registration_fields_string
					, ac.issue as issue, left(activity_note, 1000) as ActivityNote1st1000
				FROM $temp_table t inner join activity ac on ac.ID = t.ID 
				LEFT JOIN constituent c on c.ID = ac.constituent_id
				LEFT JOIN issue wp on wp.ID = ac.issue
					left join email e on e.constituent_id = c.ID 
					left join phone p on p.constituent_id = c.ID
					left join address a on a.constituent_id = c.ID	
				) 
			SELECT acid, activity_date, activity_type, activity_amount, pro_con, file_name, file_size, post_title, 
				fn, ln, mn, city, email_address, phone_number, address_line_1, address_line_2,state, zip, deceased, gender, dob, yob, 
				occupation, employer  $registration_fields_string, issue, ActivityNote1st1000	
				INTO $temp_activity_table
				FROM poss_dups where non_blank_row = 1 order by acid, non_blank_row
			";
	}	
	
   	// go direct to database and do customized search and write temp table
	$result = $sqlsrv->query ( $download_sql, array() );
	// pass back sql to retrieve the temp table
	$sql = "SELECT * FROM $temp_activity_table ";
		return ( $sql);
	}

}	

