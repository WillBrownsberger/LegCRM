<?php
/*
* class-wic-list-constituent-export.php
*
* 
*/ 

class WIC_List_Constituent_Export {

	public static function temporary_id_list_table_name() {
		return  '##temporary_id_list' . get_current_user_id();
	}

	/*
	* router used to clarify combinations -- see notes on default case
	* 
	* possibilities are multiplied from these choices:
	*	-- is advanced search or issue only  A/I
	*	-- is going to map or download or email	M/D/E
	*	-- is coming from map or not FM/FMN
	*
	*  9 possibilities here: the full multiplicative space of 12 combinations is not logical or and not all populated in this class
	*	-- cannot be coming from map and going to map at same time ( minus 2 possibilities: M-FM (can't) x A/I )
	*	-- issue list downloads done as activity lists, not in this class ( minus 1 possibility: I x FMN x D )
	*
	*  'constituent_delete' is an additional download type which is handled as if an advanced search download in create_temporary_id_table
	*/
	public static function download_rule ( $download_type, $rule ) {
		$rules = array(
			// send email button from advanced query -- A/MN/FMN/E
			'email_send'								=> array(
				'gets_sql_only' 		=> true,
				'apply_geo_filter'		=> false,
				'is_issue_only'			=> false,
				'output_sql'			=> 'non_blank_emails',
				'table_only_return'		=> true,
			),
			// send email button from activity issue list -- I/MN/FMN/E
			'issue_activity_list_email_send' 			=> array(
				'gets_sql_only'			=> true,
				'apply_geo_filter' 		=> false,
				'is_issue_only'			=> true,
				'output_sql'			=> 'non_blank_emails',
				'table_only_return'		=> true,
			),
			// show map from advanced query	-- A/M/FMN
			'show_map' 									=> array(
				'gets_sql_only' 		=> true,
				'apply_geo_filter' 		=> false, // applying good point filter in output sql
				'is_issue_only'			=> false,
				'output_sql'			=> 'geo',
				'table_only_return'		=> false,
			),
			// show map from activity issue_list
			'show_issue_map' => array(
				'gets_sql_only'			=> true,
				'apply_geo_filter' 		=> false,
				'is_issue_only'			=> true, // applying good point filter in output sql
				'output_sql'			=> 'geo',
				'table_only_return'		=> false,
			),
			// send email from map from advanced query
			'show_map_email_send' 						=> array(
				'gets_sql_only' 		=> true,
				'apply_geo_filter'		=> true,
				'is_issue_only'			=> false,
				'output_sql'			=> 'non_blank_emails', // applying geo select through filter
				'table_only_return'		=> true,
			),
			// send email from map from issue list
			'show_issue_map_email_send'					=> array(
				'gets_sql_only' 		=> true,
				'apply_geo_filter' 		=> true,
				'is_issue_only'			=> true,
				'output_sql'			=> 'non_blank_emails', // applying geo select through filter
				'table_only_return'		=> true,
			),
			// download from map from advanced query
			'show_map_download' 						=> array(
				'gets_sql_only' 		=> false,
				'apply_geo_filter'		=> true,
				'is_issue_only'			=> false,
				'output_sql'			=> 'dump',
				'table_only_return'		=> false,
			),
			// download from map from issue list
			'show_issue_map_download' 					=> array(
				'gets_sql_only' 		=> false,
				'apply_geo_filter' 		=> true,
				'is_issue_only'			=> true,
				'output_sql'			=> 'dump',
				'table_only_return'		=> false,
			),
			// from advanced search download
			'default' => array(
				'gets_sql_only' 		=> false, // point prep for map show and list prep for email send (from map or not) are true
				'apply_geo_filter'		=> false, // post map downloads and email sends are true
				'is_issue_only'			=> false, // true if coming from activity list as opposed to advanced search
				// not specifying output sql, so return $download_type for default ( original use of $download_type was to specify an output formt)
				'table_only_return'		=> false, // email sends (map or not) are true
			),
		
		);
	
		// use default if no rule array for $download_type
		if ( !isset( $rules[$download_type] ) ) {
			$lookup = 'default';
		} else {
			$lookup = $download_type;
		}

		// use $download_type as return in default case for output_sql -- legacy use of download
		if ( 'default' == $lookup && 'output_sql' == $rule ) {
			return $download_type; 
		} else {
			return $rules[$lookup][$rule];
		}
	
	}
	
	public static function do_constituent_download ( $download_type, $search_id ) { 

		// if user changed download type back to empty (initial button value), do nothing.
		// barred by js, but bullet proofing
		if ( '' == $download_type ) {
			return;		
		}		

		// creating the temp table -- always
		self::create_temporary_id_table(  $download_type, $search_id );

		// conditionally, pass the temporary table through shape filters created in the map
		if ( self::download_rule( $download_type, 'apply_geo_filter') ) {
			WIC_Entity_Geocode::filter_temp_table ( $download_type, $search_id );
		}
		// assemble sql
		$sql = self::assemble_constituent_export_sql( $download_type ); // runs off temp table

		// return $sql or complete the export
		if ( self::download_rule( $download_type, 'gets_sql_only') ) {
			return $sql;
		} else {
			global $current_user;	
			$file_name = 'wic-constituent-export-' . preg_replace( '#[^A-Za-z0-9]#', '', $current_user->get_display_name())  . '-' .  current_time( 'Y-m-d-H-i-s' )  .  '.csv' ;
			self::do_the_export( $file_name, $sql );
			if ( ! self::download_rule( $download_type, 'is_issue_only' ) ) {
				WIC_Entity_Search_Log::mark_search_as_downloaded ( $search_id );
			}
			exit;
		}
		
	}

	public static function create_temporary_id_table (  $download_type, $search_id  ) { 
		
		// create query object directly if downloading for a single issue (no search log entry, $search_id is just the issue (i.e., post ID ))
		if( self::download_rule( $download_type, 'is_issue_only') )  {		

			$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );

			$search_parameters= array(
				'retrieve_limit' 	=> 99999999,
				'select_mode'		=> 'download',
			);

			// note that the lat/lon found and/or email only filters are applied in the final sql assembly from the temp file 
			$search_array = array (
				array (
					 'table'	=> 'activity',
					 'key'	=> 'issue',
					 'value'	=>  $search_id, 
					 'compare'	=> '=', 
				),
			);

			$wic_query->search ( $search_array, $search_parameters ); // create a temp table of id's 

		} else {
			// retrieves only the meta array, not the search parameters, since will supply own
			$search = WIC_Entity_Search_Log::get_search_from_search_log ( array( 'id_requested' => $search_id ) );	
			// can be issue or constituent 
			$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( $search['entity'] );
			$search_parameters = array (		
				'select_mode' 		=> 'download', // with this setting, object will create the temp table that export sql assembler is looking for
				'sort_order' 		=> true,
				'compute_total' 	=> false,
				'retrieve_limit' 	=> 999999999,
				'redo_search'		=> true,
				'old_search_id'		=> $search_id,	
			);
		
			// do the search, saving retrieved id list in temp table if constituent, setting up next step if issue	
			$wic_query->search ( $search['unserialized_search_array'], $search_parameters );
		} 	
	}

	public static function do_staging_table_download ( $file_requested,  $staging_table_name  ) {

		// naming the download file
		$file_name = $staging_table_name  . '_' . $file_requested . '.csv' ;
		$sql = "SELECT * FROM $staging_table_name ";
		// set up the sql			
		if ( 'validate' == $file_requested ) {
			// validation errors; can export after validation
			$sql .= " WHERE VALIDATION_STATUS = 'n' "; 										
		} else if ( 'new_constituents' == $file_requested ) {
			// not matched, but have matching fields; can export after define matching 
			$sql .= " WHERE MATCH_PASS = '' AND FIRST_NOT_FOUND_MATCH_PASS > '' "; 	
		} else if ( 'match' == $file_requested ) {
			// actually matched; can export after define matching
			$sql .= " WHERE MATCH_PASS > '' AND MATCHED_CONSTITUENT_ID > 0 "; 		
		} else if ( 'bad_match' == $file_requested ) {				
			// but no match attempted -- most likely missing data although passing validation
			$sql .= " WHERE ( VALIDATION_STATUS = 'y' AND MATCH_PASS = '' AND FIRST_NOT_FOUND_MATCH_PASS = '' ) "; 
		} else if ( 'not_loaded' == $file_requested ) {
			// not linked -- meaningful only after completion										
			$sql .= " WHERE MATCHED_CONSTITUENT_ID = 0 ";
		}	
		// 'dump' has no where clause
		self::do_the_export( $file_name, $sql );	
		
		exit;

	}


	/*
	*	sql points to temporary table created early in transaction
	*/
	public static function assemble_constituent_export_sql ( $download_type ) { 
		
		/* NOTE THAT THIS FUNCTION CREATES TEMP TABLE AND ASSEMBLES THE SQL TO POINT TO IT */

		// reference global wp query object	
		global $sqlsrv;	
		
		// id list passed through user's temporary file wp_wic_temporary_id_list, lasts only through the server transaction (multiple db transactions)
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();
		
		// will use to pass fuller constituent list to actual export via db as temp table
		$temp_constituent_table = '##temporary_constituent_list' . get_current_user_id();  	

		$registration_fields_string = self::registration_fields_string();
		
		// initialize download sql -- if remains blank, will bypass download
		$download_sql = '';
		$output_type = self::download_rule( $download_type, 'output_sql');
		switch ( $output_type ) {
			case 'emails':		
				$download_sql =" 
					SELECT DISTINCT last_name, first_name,  salutation, gender, email_address, consented_to_email_list
					INTO $temp_constituent_table
					FROM $temp_table i INNER JOIN constituent c on c.ID = i.ID
					inner join email e on e.constituent_id = c.ID
					WHERE email_address > ''
					ORDER BY last_name, first_name
					"; 
				break;
			case 'phones':		
				$download_sql =" 
					SELECT DISTINCT  last_name, first_name, salutation, gender, phone_number, extension
					INTO $temp_constituent_table 
					FROM $temp_table i INNER JOIN constituent c on c.ID = i.ID
					inner join phone p on p.constituent_id = c.ID
					where phone_number > ''
					"; 		
				break;
			case 'addresses':		
				$download_sql =" 
					SELECT DISTINCT last_name, first_name, salutation, gender,
						address_line as wic_address_line_1,
						concat ( city, ', ', state, ' ',  zip ) as wic_address_line_2,
						city, state, zip, i.* $registration_fields_string
					INTO $temp_constituent_table
					FROM $temp_table i INNER JOIN constituent c on c.ID = i.ID
					inner join address a on a.constituent_id = c.ID
					WHERE (city > '' or state > '' or zip > '')
					ORDER BY last_name, first_name
					"; 		
				break;		
			case 'dump':
				$download_sql = 
					// note: the effect of order by email_address desc in the partition is just to allow use of row_number
					// query selects one row for every unique combo of phone/email/address, but at least eliminates dups
					"
					WITH poss_dups as (
						SELECT	
							ROW_NUMBER() OVER (
								PARTITION BY c.id, email_address, phone_number, address_line, city, state, zip
								ORDER BY email_address DESC
								) as not_dup,
							last_name,first_name, middle_name, salutation,c.id AS ID,
							email_type, email_address, 
							phone_type, phone_number,
							address_type, address_line,
							city, state, zip,
							consented_to_email_list, case_assigned, case_review_date, case_status, 
							is_deceased as deceased, gender, date_of_birth as dob, year_of_birth as yob, occupation, employer, c.last_updated_time, c.last_updated_by
							$registration_fields_string
						FROM $temp_table i INNER JOIN  constituent c on c.ID = i.ID
							left join email e on e.constituent_id = c.ID AND email_address > ''
							left join phone p on p.constituent_id = c.ID AND phone_number > ''
							left join address a on a.constituent_id = c.ID AND (address_line > '' OR city > '' OR state > '' OR zip > '' )
						)
						SELECT
							last_name, first_name, middle_name, salutation,
							email_type, email_address, 
							phone_type, phone_number,
							address_type, address_line,
							city, state, zip,
							consented_to_email_list, case_assigned, case_review_date, case_status, 
							deceased, gender, dob, yob, occupation, employer, last_updated_time, last_updated_by, ID  $registration_fields_string
							INTO $temp_constituent_table
							FROM poss_dups WHERE not_dup = 1
					";
					break;
			// send to only non_blank_emails and only one per constituent				
			case 'non_blank_emails':
				$download_sql = " 		
					SELECT last_name, first_name, salutation, gender, max(email_address) AS email_address, c.ID
					INTO $temp_constituent_table
					FROM $temp_table i INNER JOIN constituent c on c.ID = i.ID
					inner join email e on e.constituent_id = c.ID
					WHERE e.email_address > ''
					group by c.ID, first_name, last_name, salutation, gender
					";	
				break;	
			// for geocoding
			case 'geo':
				/*
				* note: this sql groups first by address id -- there is one address id for each address that a constituent has 
				* 	. . . multiple constituents at the same address have their own address record and address id
				*   . . . choosing to include constituents in geographic analysis whereever they appear, possibly multiply (not likely)
				*	. . . BUT do want to group by address id -- don't want dup records creeping in b/c multiple phone or emails
				* So, a constituent could appear n times on a map, in n places that they have addresses.
				*
				* THIS IS FOR ASSEMBLY OF MAP POINTS FOR DISPLAY
				*/

				$download_sql = "
				SELECT 
					MAX(a.constituent_id) as cid,
					COUNT(a.id) as address_count,
					'View constituent ' as name,
					iif(count(a.id) > 1, concat( ', last added among ', COUNT(a.id), ' at '),'') AS others,
					concat ( address_line, iif( address_line > '',', ', '' ), city, ', ', state, ' ',  zip ) as address,
					'' as phone_number,
					'' as email_address,
					'' as party,
					lat,
					lon
				INTO $temp_constituent_table
				FROM address a INNER JOIN $temp_table i on i.ID = a.constituent_id 
				WHERE lat > 0 and lat < 99
				GROUP BY lat, lon, address_line, city, state, zip
				";
				break;
	
		} 	
	
   		// go direct to database and do customized search and write temp table
		$result = $sqlsrv->query ( $download_sql, array() );

		if ( self::download_rule ( $download_type, 'table_only_return') ) {
			return $temp_constituent_table;
		} else {
			return "SELECT * FROM $temp_constituent_table ";
		}
	}




	// do_the_export runs the $sql in chunks and exports to filename
	protected static function do_the_export ( $file_name, $sql ) {

		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Description: File Transfer');
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename={$file_name}");
		header("Expires: 0");
		header("Pragma: public");

		$fh = fopen( 'php://output', 'wt' ); // writing plain text files; good to support txt 
		
		global $sqlsrv;
		
		// chunking the retrievals to keep $results array size down on large downloads
		// 10000 peaks memory usage at just under 100M for constituent download
		// believe packet size not an issue -- outgoing sql is short and the packet is just a row coming back (?).
		// 1000 chunk size cuts memory usage to 13M ( WP is roughly 4M) and but is only about 30% slower on large files
		$i = 0;
		$header_displayed = false;
		while ( $results = $sqlsrv->query ( $sql . " ORDER BY 1, 2 OFFSET " . $i * 1000 . " ROWS FETCH NEXT 1000 ROWS ONLY ", array() ) ) {
			$i++;	
			foreach ( $results as $result ) {
				if ( !$header_displayed ) {
		      	fputcsv($fh, array_keys((array)$result));  
		        	$header_displayed = true;
		   	}
		    fputcsv($fh, (array) $result); // defaults are delimiter ',', enclosure '"', escape '/'
			}
		} 

		if ( 0 == $i ) { // write a not found header if no records were found
			fputcsv( $fh, array ( "No records found for $file_name" ) ); 		
		}
		fclose ( $fh );		

	}

	public static function get_temp_table_count ( $temp_table ) {
		global $sqlsrv;
		$result = $sqlsrv->query ( "SELECT count(*) as found_count from $temp_table", array() );
		return $result[0]->found_count;
	}
	
	public static function registration_fields_string() {
	
		// string includes initial comma;
		$registration_fields_string = ',' . implode(',', WIC_FORM_Constituent::$form_groups['registration']['fields']);
		return $registration_fields_string;
	
	}
	
	
}	

