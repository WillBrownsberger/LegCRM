<?php
/*
*
* class-wic-entity-upload-upload.php
*
* Uploader is designed with three goals in mind:
*	1. 	Chunk uploads using plupload, so no max file size issues
*	2.	Don't save any data outside temp directory
*		-	avoid exposure of constituent data in breakdown situations
* 		-	avoid breakdowns due to permissions issus
*	3.	Seperate upload step from verification and formatting steps for execution time chunking
*
* NOTE COULD REWRITE DECODING FUNCTIONS TO SUPPORT HIGH PLANE CHARACTERS IN MB4 INSTALLATION
*/

class WIC_Entity_Upload_Upload {
 
 	// called by plupload only -- return format for upload-details.js
	public static function handle_upload() {  
				
		// did the file chunk come at all?
		if ( empty($_FILES) ) {
			 legcrm_finish( '{"OK": 0, "info": "Failed to actually upload file -- $_FILES empty."}');
		}

		// did the file chunk come in OK?
		if ( $_FILES['file']['error'] ) {
			 legcrm_finish( '{"OK": 0, "info": "' . self::codeToMessage( $_FILES['file']['error'] ) . '"}');
		}

		// check that the file apparently uploaded in fact an uploaded file
		if ( ! is_uploaded_file (  $_FILES['file']['tmp_name'] ) ) {
			legcrm_finish( '{"OK": 0, "info": "File identity violation -- working file not an uploaded file." }' );
		}

		// do we have an upload id ?
		$upload_id = isset ( $_REQUEST['upload_id'] ) ? $_REQUEST['upload_id'] : '';
	
		// if we don't have an id, so that this is first chunk, and it has zero length, legcrm_finish
		if ( '' == $upload_id && 0 == $_FILES['file']["size"]  ) {
			legcrm_finish( '{"OK": 0, "info": "File uploaded shows as having size 0." }' );
		} 

		// extract and sanitize file name
		$file_name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
		$file_name = sanitize_file_name ( $file_name );			

		// prepare to do database updates for initial file transfer 
		global $sqlsrv;
		global $current_user;
		$current_user_id	= $current_user->get_id();
		
		// create stub in upload table if getting started -- if not chunked, set as if single chunk
		$chunks = isset( $_REQUEST["chunks"] ) ? intval( $_REQUEST["chunks"] ) : 1;
		if ( '' == $upload_id ) {
			$save_result = $sqlsrv->query( 
				"INSERT INTO upload 
				( upload_time, upload_by, upload_file, upload_status, last_updated_time, last_updated_by, upload_chunks, office )  VALUES 
				( ?, $current_user_id, ?, 'initialized', ?, $current_user_id, ?, ? )",
				array ( current_time( 'YmdHis' ), $file_name, current_time( 'YmdHis' ), $chunks, get_office() )
			);
			if ( $save_result ) {
				$upload_id = $sqlsrv->insert_id;
			} else {		
				legcrm_finish( '{"OK": 0, "info": "Unknown database error logging upload."}' );
			}
		}

		// read the chunk into memory (small max size: 512kb)
		$chunk = file_get_contents( $_FILES['file']['tmp_name'] ); 
		@unlink($_FILES['file']['tmp_name']);
		if ( '' == $chunk ) {
			legcrm_finish('{"OK": 0, "info": "Failed to open input stream."}');
		} 

		// get the chunk ID -- doesn't have to be set ( i.e., might not be chunked ) for this handler to work
		$chunk_id = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;

		// save the chunk contents in the temp table
		$save_result   = $sqlsrv->query( "INSERT INTO upload_temp
			( upload_id, chunk_id, chunk ) VALUES
			( $upload_id, $chunk_id, ? )",
			// specifying local 8 bit encoding, replacing not found chars with ? in incoming, saving as varchar (collated as utf-8)
			array ( array($chunk,SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR),SQLSRV_SQLTYPE_VARCHAR('max')), )
		);

		if ( $save_result ) {
			if ( $chunk_id == $chunks - 1 || $chunks == 0 ) {
				WIC_Entity_Upload::update_upload_status ( $upload_id, 'copied' );
			}
			legcrm_finish( '{"OK": 1, "info": "Chunk upload successful.", "upload_id": "' . $upload_id . '"}' );
		} else {		
			legcrm_finish( '{"OK": 0, "info": "Unknown database error storing upload chunk."}' );
		}
 
	}
 
 
 	/*
 	*
 	* this function supports loading of single chunk files through constituent and issue forms
 	*
 	* stores them as activities
 	*
 	*/
 	public static function handle_document_upload ( $constituent_id, $issue ) {

		// do standard checks
		self::die_standard_deaths();

		// if we don't have an id, so that this is first chunk, and it has zero length, legcrm_finish
		if ( !$constituent_id && !$issue ) {
			legcrm_finish( '{"OK": 0, "info": "Form must supply either constituent_id or issue -- form error in upload-upload.js." }' );
		} 

		$issue = $issue ? $issue : WIC_Entity_Activity::get_unclassified_post_array()['value'];
		$today = current_time("Y-m-d");
		global $current_user;
		$current_user_id = $current_user->get_id();
		
		// extract and sanitize file name
		$file_name = self::get_loader_filename();
		$file_size = $_FILES['file']["size"];		

		// prepare to do database updates for initial file transfer 
		global $sqlsrv;
		$activity_table		= 'activity';

		// read the chunk into memory (small max size: 512kb)
		$file_content = file_get_contents( $_FILES['file']['tmp_name'] ); 
		@unlink($_FILES['file']['tmp_name']);
		if ( '' == $file_content ) {
			legcrm_finish('{"OK": 0, "info": "Failed to open input stream from temporary copy of uploaded file."}');
		} 
		$activity_note =  'Uploaded file: ' . $file_name . ' (' . self::FileSizeConvert( $file_size ) . ')';


		// save the file contents as a new activity record 
		$save_result  = $sqlsrv->query( "
			INSERT INTO $activity_table
			( 
				constituent_id,
				activity_date,
				activity_type,
				activity_note,
				issue,
				file_name,
				file_size,
				file_content,
				last_updated_time,
				last_updated_by,
				office,
				upload_id,
				activity_amount,
				pro_con,
				email_batch_originated_constituent,
				related_inbox_image_record,
				related_outbox_record
			) VALUES
			( 
				?,
				?,
				?,
				?,
				?,
				?,
				?,
				?,
				?,
				?,
				?,
				0,
				0,
				'',
				0,
				0,
				0
			)",
			array ( 
				$constituent_id,
				$today,
				'wic_reserved_77777777',
				$activity_note,
				$issue,
				$file_name,
				$file_size,
				array($file_content,SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STRING('binary'),SQLSRV_SQLTYPE_VARBINARY('max')),	
				current_time( 'YmdHis' ),
				$current_user_id,
				get_office()
			)
		);

		if ( $save_result ) { // positive integer insert id
			$raw_list_item_elements = 
				(object) array (
					'constituent_id' => $constituent_id,
					'constituent_id_label' => '', 
					'activity_type' => 'wic_reserved_77777777',
					'activity_type_label' => 'Document',
					'activity_date' => $today,
					'issue' => $issue,
					'post_title' => WIC_DB_Access_Issue::get_the_title( $issue ),
					'pro_con' => '',
					'pro_con_label' => 'Pro/Con?',
					'activity_note' => $activity_note,
					'last_updated_by' => $current_user_id,
					'last_updated_time' => current_time( 'YmdHis' ),
					'ID' => $save_result // note that $sqlsrv->insert_id has been flushed at this point
				); 
			$list_item = WIC_Entity_Activity::format_activity_list_item ( $raw_list_item_elements );
			legcrm_finish( json_encode( (object) array ( "OK" => 1, "info" => $list_item )));
		} else {		
			legcrm_finish( '{"OK": 0, "info": "Unknown database error attempting to store uploaded file."}' );
		}
 
 	}
 
 	// sanitize loaded filename
 	private static function get_loader_filename() {
 		$file_name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
		return sanitize_file_name ( $file_name );	
  	}
 
 	// checks not dependent on type of upload
 	private static function die_standard_deaths() {

 		// did the file  come at all?
		if ( empty($_FILES) ) {
			 legcrm_finish( '{"OK": 0, "info": "Failed to actually upload file -- $_FILES empty."}');
		}

		// did the file chunk come in OK?
		if ( $_FILES['file']['error'] ) {
			 legcrm_finish( '{"OK": 0, "info": "' . self::codeToMessage( $_FILES['file']['error'] ) . '"}');
		}

		// check that the file apparently uploaded in fact an uploaded file
		if ( ! is_uploaded_file (  $_FILES['file']['tmp_name'] ) ) {
			die( '{"OK": 0, "info": "File identity violation -- working file not an uploaded file." }' );
		}
 	
 	}
 
 	/*
 	*
 	* this function supports loading of single chunk files for email attachments
 	*
 	* stores them as all email attachments
 	*
 	*/
 	public static function  handle_attachment_upload ( $draft_id ) {

		// do standard checks
		self::die_standard_deaths();
		
		// if bad call die -- can't save without parameters
		if ( !$draft_id ) {
			legcrm_finish( '{"OK": 0, "info": "Form must supply draft_id -- form error in upload-upload.js." }' );
		} 

		// extract and sanitize file name
		$file_name = self::get_loader_filename();
		$file_size = $_FILES['file']["size"];		

		// read the chunk into memory (small max size: 512kb)
		$file_content = file_get_contents( $_FILES['file']['tmp_name'] ); 
		@unlink($_FILES['file']['tmp_name']);
		if ( '' == $file_content ) {
			legcrm_finish('{"OK": 0, "info": "Failed to open input stream from temporary copy of uploaded file."}');
		} 

		if ( ! $list_item = WIC_Entity_Email_Attachment::handle_outgoing_attachment ( $file_content, $file_name, $draft_id ) ) {
			legcrm_finish( '{"OK": 0, "info": "Database error saving attachment."}');
		} else {
			legcrm_finish( json_encode( (object) array ( "OK" => 1, "info" => $list_item )));
		};

	} 
 
 	private static function get_the_upload( $id ) {
 		
 		$upload_details = array();
 		
		global $sqlsrv;
		
		// get the upload details 
		$upload_parms = $sqlsrv->query ( 
			"
			SELECT upload_status, upload_file, upload_chunks, serialized_upload_parameters FROM upload where ID = ?
			",
			array( $id ) 
		);	

		$upload_details['upload_status'] 	= $upload_parms[0]->upload_status;
		$upload_details['file_name'] 		= $upload_parms[0]->upload_file;
		$upload_details['upload_chunks']	= $upload_parms[0]->upload_chunks;
		$upload_details['serialized_upload_parameters']	= $upload_parms[0]->serialized_upload_parameters;

		// read the temp table chunks into a single temp file
		$handle = tmpfile();
		if ( ! $handle ) {
			return array ( 'response_code' => 1, 'output' => 'Unable to create temporary working file for verification; possible directory permission issues.' ); 
		}
		for ( $i = 0; $i < $upload_details['upload_chunks']; $i++ ) { 
			$chunk_result = $sqlsrv->query ( "SELECT chunk FROM upload_temp WHERE upload_id = ? and chunk_id = $i", array( $id ) );
			$chunk = $chunk_result[0]->chunk;
			fwrite ( $handle, $chunk );		
		}
		rewind ( $handle );
		$upload_details['handle'] = $handle;

		return ( $upload_details );
 	
 	}
 
 
 	
	public static function verify_upload ( $id, $data ) { 
 	
		// handles MAC uploads
		ini_set('auto_detect_line_endings', true);
 	
		$upload_details = self::get_the_upload ( $id );
		if ( isset ( $upload_details['response_code'] ) )  {
			return $upload_details;
		}
		extract ( $upload_details );
				
		extract ( self::decode_parameters ( $data ) );
		// does it really act like a csv file?
		$row = fgetcsv( $handle, 
			$max_line_length,
			$delimiter,
			$enclosure,
			$escape
		); 
		if ( false === $row ) {
			return array ( 'response_code' => 1, 'output' => "$file_name copied and opened, but unable to read file as csv or txt.  Check upload parameters." ); 
		} 
		//start over 
		rewind( $handle );
		// check for column count with previously read first row
		$count = count ( $row ); 
		$row_count = 1;
		while ( FALSE !== $row = fgetcsv( $handle, 
					$max_line_length, // do not limit line length, except at high number to prevent long wait for error message in huge file with bad delimiter
					$delimiter,
					$enclosure,
					$escape
			  	) 
			) {	 
			if ( count ( $row ) != $count ) { 	
				return array ( 'response_code' => 1, 'output' => sprintf (
								'%1s appears to have inconsistent column count. First row had %2$d columns, but row %3$d had %4$d columns.', 
								$file_name, $count, $row_count, count ( $row ) )
				);
			} 
			$row_count++;	
		}

		// reject NO rows count
		if ( 1 == $row_count ) {
			return array ( 'response_code' => 1, 'output' => "$file_name appears to have no data, possible error in file creation or upload parameters." ); 
		}
		
		return array ( 'response_code' => 1, 'output' => (object) ( array ( 'count' => $count, 'row_count' => $row_count ) ) ); 
	
 	}
 	

	/*
	*
	*	Process file chunks into records (after reconsolidation of chunks into flat temp file)
	*
	*/
	public static function stage_upload ( $id, $form_data ) {
	
		// access wordpress database object
		global $sqlsrv;
		
		$save_start = time();

		// get upload parameters		
		$upload_parameters = self::decode_parameters ( $form_data );
		extract ( $upload_parameters );
		set_time_limit ( $max_execution_time );  // attempt this -- host may not allow it 	
		
		// handles MAC uploads ( need to repeat here -- lasts only through the transaction )
		ini_set('auto_detect_line_endings', true);
		
		// get the temp file handle
		$upload_details = self::get_the_upload ( $id );
		if ( isset ( $upload_details['response_code'] ) )  {
			return $upload_details;
		}
		extract ( $upload_details );
		
		$serialized_upload_parameters = json_decode ( $serialized_upload_parameters );
		// do a cleanup in case user previously attempted or completed parse/staging process 
		if ( isset ( $serialized_upload_parameters->staging_table_name ) ) {
			$stub = $serialized_upload_parameters->staging_table_name;  
			self::delete_tables_with_name_stub ( $stub );
		}
	
		// reset status to copied if user is returning to this step from a later step
		if ( 'copied' != $upload_status ) {
			WIC_Entity_Upload::update_upload_status ( $id, 'copied' ); 
		}
		// note that a cancelled instance could be continuing on server at this point -- 
		// have to check staging table name at end of process too so don't overwrite new 
	
		// have already validated as a csv in verify_upload with consistent column count
  	    $columns = fgetcsv( $handle, $max_line_length, $delimiter, $enclosure, $escape  ); 
  	   	// need to reget the column count
      	$count_columns = count ( $columns );

		// set up new table name
		$table_name = 'staging_table_' .
				str_replace ( '-', '_', str_replace ( ' ', '_', str_replace ( ':', '_', 
					current_time( 'YmdHis' ) ) ) ) . 
					'_' . get_current_user_id();

		// create an array of reserved column names to test against
		// avoid duplicate column errors when reloading downloaded staging tables
		$reserved_column_names = array (
			'STAGING_TABLE_ID',
			'VALIDATION_STATUS',
			'VALIDATION_ERRORS',
			'MATCHED_CONSTITUENT_ID',
			'MATCH_PASS',
			'FIRST_NOT_FOUND_MATCH_PASS',
			'NOT_FOUND_VALUES',
			'INSERTED_NEW', 
			'STAGING_TABLE_ID_STRING',
			'CONSTRUCTED_STREET_ADDRESS',
			'new_issue_ID',
			'new_issue_title',
			'new_issue_content',
			'record_count', 
			'inserted_post_id', 
		);	
		// create a table with the appropriate number of columns -- get column names from first row if available
		$sql = "CREATE TABLE $table_name ( "; 
		$i = 1;
		$column_names = array();
		foreach ( $columns as $column ) {
			// use user supplied column name (sanitized) if none of the following obtain
			$column_name = ( 
							in_array ( self::sanitize_column_name ( $column ), $reserved_column_names ) ||	// not a reserved name after sanitization
							in_array ( self::sanitize_column_name ( $column ), $column_names ) || 				// not a dup after sanitization 
							'' == self::sanitize_column_name ( $column ) || 											// not empty after sanitization
							0 == $includes_column_headers ) 																	// not a data row
					?  'COLUMN_' . $i : self::sanitize_column_name ( $column ); 
			$column_names[] = $column_name; 			
			$sql .= ' [' . $column_name . '] varchar(max) NOT NULL, ';
			$i++;		
		}

		$column_names[] = 'CONSTRUCTED_STREET_ADDRESS';  // add the constructed street address at the end of the array

		$sql .=  	'STAGING_TABLE_ID bigint IDENTITY(1,1) PRIMARY KEY, ' .
					'CONSTRUCTED_STREET_ADDRESS varchar(max) DEFAULT \'\', ' .
					'VALIDATION_STATUS varchar(1) DEFAULT \'\', '	.					// y or n -- n if there are errors, y if validated clean
					'VALIDATION_ERRORS varchar(max) DEFAULT \'\', ' .				// concatenation of all field validation errors
					'MATCHED_CONSTITUENT_ID bigint DEFAULT 0, ' . // constituent id found in marked match pass
					'MATCH_PASS varchar(50) DEFAULT \'\', ' . 							// populated only if constituent id found; stops later pass attempts to find
					'FIRST_NOT_FOUND_MATCH_PASS varchar(250) DEFAULT \'\', ' .		// first match pass where values present if not found; may be found in later pass
					'NOT_FOUND_VALUES varchar(max) DEFAULT \'\', ' .				// concatenated values from not found match pass
					'INSERTED_NEW varchar(1) DEFAULT \'\' ' .							// 'y' if inserted new (updated on insert)   
					');';

		$sqlsrv->query ( $sql, array() );
		$result = $sqlsrv->success;
		$sqlsrv->query ( "CREATE INDEX ix_{$table_name}_MATCHED_CONSTITUENT_ID ON {$table_name} (MATCHED_CONSTITUENT_ID)", array());
		$result2 = $sqlsrv->success; 

		if ( false === $result || false == $result2 ) {
			return array ( 'response_code' => 0, 'output' => "Error creating staging table." ); 
		} else { // store a stub of upload parameters for reference by progress checker
			$result = $sqlsrv->query ( "
				UPDATE upload	
 				SET serialized_upload_parameters = ?
 				WHERE ID 						 = ?
 				",
 				array(  json_encode( array ( 'staging_table_name' => $table_name ) ), $id )
 			);
			if ( false == $result ) { // not found is an error ==, not ===
				return array ( 'response_code' => 0, 'output' => "Error creating staging table stub after update -- probable purge executed while upload in progress." ); 
			} 
		}
	
		/***********************************************************************************
		*
		*	Considered use of load data infile direct upload
		*   -- when it works, it is clearly faster, but  . . . 
		*  Whether it will work can depend on many factors, so can't rely on it for all users
		*   -- http://stackoverflow.com/questions/10762239/mysql-enable-load-data-local-infile
		*   -- http://dev.mysql.com/doc/refman/5.1/en/load-data.html
		*   -- http://dev.mysql.com/doc/refman/5.0/en/load-data-local.html
		*	 -- http://ubuntuforums.org/showthread.php?t=822084
		*   -- http://stackoverflow.com/questions/3971541/what-file-and-directory-permissions-are-required-for-mysql-load-data-infile
		*   -- http://stackoverflow.com/questions/4215231/load-data-infile-error-code-13 (apparmor issues)
		*	 -- https://help.ubuntu.com/lts/serverguide/mysql.html (configuration)
		*
		*  No real payoff in preserving it as an option for users.
		*
		*	Strategy: execution time aside, the risk with an insert approach is that 
		*  users will run into memory and packet size issues with larger packets in
		* 	long insert statements -- rather than force naive users to change these parameters, keep likely 
		* 	packet size low enough ( < 1 MB )
		*	// http://dev.mysql.com/doc/refman/5.5/en/packet-too-large.html
		*  
		************************************************************************************/
		
		// now prepare INSERT SQL stub to which will be added the values repetitively in loop below
		$first_column = $column_names[0];
		$sql_stub = "INSERT INTO $table_name ( [$first_column] " ;
		// add columns after column 0 with commas preceding
		$i = 0;
		foreach ( $column_names as $column_name ) {
			if ( $i > 0 ) {
				$sql_stub .= ", [$column_name] ";
			}	
			$i++;	
		}
		$sql_stub .= ') VALUES '; // CONSTRUCTED STREET ADDRESS is the last value -- empty 	
		
		// if don't have column headers need to start over since went to get count record
		if ( 0 == $includes_column_headers ) {		
			rewind ( $handle );
		}	
		/****************
		*   IN SETTING ROWS PER PACKET, FOLLOWING CONSIDERAITONS
		*
		*	MAXIMUM TRANSMISSION UNIT, AS GROSSED UP TO NETWORK PACKET SIZE AND THEN MAX BATCH SIZE 
		*   	default network packet size is 4096 for sql server: assume this is true.
		*   	https://docs.microsoft.com/en-us/sql/database-engine/configure-windows/configure-the-network-packet-size-server-configuration-option?view=sql-server-ver15
		*		max batch size is 65536* max packet size
		*   	https://docs.microsoft.com/en-us/sql/sql-server/maximum-capacity-specifications-for-sql-server?redirectedfrom=MSDN&view=sql-server-ver15
		*		So, want to be less than: 268M IN TOTAL LENGTH
		*
		*	PHP MEMORY LIMITS: experiment indicates that in 7.4 for string arrays, overhead is actually low. Default total memoray is 134M?
		*		Budgeting 20M for chunk size feels safe to start, theoretically could do 100M if analysis were really right
		*
		*	MAX # OF PARAMETERS ALLOWED BY SQL SERVER = 2100, SO column_count * rows_per_packet has to be under 2100
		**************/ 
		$max_rows_per_memory 			= $max_line_length > 0  ? abs( intval( 20000000/$max_line_length ) ) : 1; 
		$max_rows_per_parameter_limit 	= intval( 2100/($count_columns + 2));
		$max_rows 						= min( $max_rows_per_memory, $max_rows_per_parameter_limit );
		$rows_per_packet				= 0 == $max_rows ? 1 : $max_rows;

		$insert_count = 0;			 
				
		while ( ! feof ( $handle ) ) {

			$sql = $sql_stub;
			$j = 0;
			$values = array(); 
	       	while ( ( $data = fgetcsv( $handle, $max_line_length, $delimiter, $enclosure, $escape  ) ) !== false )  {	
				$row = '( ?';
				$values[] = self::attempt_decoding ( $data[0], $charset );
				$i = 0; 
				foreach ( $data as $column ) {	
					if ( $i > 0 ) {
						$row .= ',?' ;
						$values[] = self::attempt_decoding ( $column, $charset ); 
					} 
					$i++;  // only counter purpose is to skip first column, since added at start of row (handling punctuation);
				}
				$row .= ",?),";// add a place holder for the constructed street address
				$values[] = ''; // add an empty value for the constructed street address
				$sql .= $row; 
				$j++;
				$insert_count++;
				if ( $j == $rows_per_packet ) {
					break;					
				}
			}

			// drop the final comma
			$sql = substr ( $sql, 0, -1 );

			// execute the insert if did fget ( feof not returning false after last record -- must hit the eof with a final fget
			// this is a change in behavior since writing file from chunked database entries, but is a known issue
			// http://php.net/manual/en/function.empty.php 
			if ( ! empty ($values ) ) {
				$result = $sqlsrv->query ( $sql, $values );
				// exit on failure
				if ( false === $result ) {
					return array ( 'response_code' => 0, 'output' => "Database error on insertion of staging table records." ); 
				}
			}
		} // end not eof loop


		/*
		*
		* have uploaded staging table, now want to update the upload table with details about the upload
		*
		*/
		
		// expand the upload parameters array with results/accounting 
		$upload_parameters['method'] 					= "INSERTS in packets of $rows_per_packet rows via sqlsrv";
		$upload_parameters['execution_time_allowed'] 	= ini_get ( 'max_execution_time' ); // should be same as max setting if was successful
		$upload_parameters['actual_execution_time'] 	= time() - $save_start;
		$upload_parameters['peak_memory_usage'] 		= memory_get_peak_usage( true );
		$upload_parameters['columns_count']				= $count_columns;
		$upload_parameters['insert_count']				= $insert_count;
		$upload_parameters['staging_table_name']		= $table_name;
	
		$serialized_upload_parameters = json_encode( $upload_parameters );
		
		$interface_table = 'interface';
		// prepare sql to lookup fields in learned column map (omitting mappings to fields that are not enabled);
		$sql = "SELECT * FROM $interface_table i 
				WHERE upload_field_name = ?";
		// initialize column map for later use with unmapped columns
		$column_map = array();
		foreach ( $column_names as $column ) {
			// do lookups on field name 
			$lookup = $sqlsrv->query ( $sql, array ( $column ) );
			$found = '';
			// if input column name found in interface table, put into column map as mapped (if target not already mapped to) 			
			if ( isset ( $lookup [0] ) ) { 
				$found = (object) array ( 
					'entity' 			=> $lookup[0]->matched_entity, 
					'field'				=> $lookup[0]->matched_field, 
					'non_empty_count' 	=> 0,
					'valid_count'		=> 0,
				);
				// test whether db field has already been mapped to
				foreach ( $column_map as $column_object ) {
					if ( $column_object > '' ) { // only testing columns that have actually been mapped
						if ( $column_object->entity == $found->entity && $column_object->field == $found->field ) {
							// if already mapped to, blank out found value
							$found = '';
							break;
						}				
					}			
				}	
			}
			// place a map value in array for every column -- empty if not found or not unique
			$column_map[$column] = $found;			
		}
		$serialized_column_map = json_encode ( $column_map );	
		
		$upload_status = ( '' == WIC_Entity_Upload_Map::is_column_mapping_valid ( $column_map ) ) ? 'mapped' : 'staged';

		// before pulling trigger and saving, check that user hasn't cancelled and restarted this process -- this request was already aborted so return code will not be looked at
		if ( $table_name != self::get_staging_table_name ( $id ) ) { 
			return array ( 'response_code' => 0, 'output' => "Apparent user cancellation of parse process" ); 
		}
		
		// directly update the status record 	
		$upload_table 		= 'upload';
		global $current_user;
		$current_user_id = $current_user->get_id();
		$result = $sqlsrv->query ( "
			UPDATE $upload_table	
 			SET serialized_upload_parameters = ?,
 				serialized_column_map		 = ?,
 				upload_status				 = ?,
				last_updated_time			 = ?,
				last_updated_by				 = ?
 			WHERE ID 						 = ?
 			",
 			array(  $serialized_upload_parameters, $serialized_column_map, $upload_status, current_time( 'YmdHis' ), $current_user_id, $id )
 			);
		if ( false === $result ) {
			return array ( 'response_code' => 0, 'output' => "Database error on update of upload status after staging." ); 
		} else {
			return array ( 'response_code' => 1, 'output' => "" );
		} 
		
	}
	
	private static function attempt_decoding ( $value, $charset ) {
		// replace null with empty
		$value = ( '\N' == $value  ||  'NULL' == $value || NULL === $value ) ? '' : $value;
		// attempt to convert character set as instructed
		if ( 'UTF-8' != $charset && function_exists ( 'iconv' ) ) {
			$value_test = iconv ( $charset, 'UTF-8', $value );
			$value = $value_test ? $value_test : $value; // if conversion fails keep the original value
		} 
		// double check output and strip high plane
		if ( self::wic_is_utf8( $value ) ) {  	// test for UTF-8 or indistinguishable ASCII or UTF-7
			return trim ( preg_replace( '/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $value ) ); 
			// UTF-8 is standard and acceptable without recoding (but need to strip 4-character high-plane Unicode for MYSQL, see sanitize_incoming )
		} 
		// if didn't find UTF8, just strip non-ASCII -- most English imported data should not suffer much and will prevent many user problems
		return trim ( preg_replace("/[^\x01-\x7F]/","\xEF\xBF\xBD", $value) ); 
	}	

	public static function wic_is_utf8 ( $string ) {
  		// From https://www.w3.org/International/questions/qa-forms-utf-8.en.html
		return preg_match('%^(?:
			  [\x09\x0A\x0D\x20-\x7E]            # ASCII
			| [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
			|  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
			| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
			|  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
			|  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
			| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
			|  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
		)*$%xs', $string);	
	}
	
	// limit column name to letters, digits and underscore
	private static function sanitize_column_name ( $column_name ) { 
		$stripped = preg_replace( '/[^A-Za-z0-9_]/', '', $column_name );	
		$non_numeric_column_name = is_numeric( $stripped ) ? '' : $stripped;
		$clean_column_name = substr( self::attempt_decoding ( $non_numeric_column_name, 'UTF-8' ), 0, 64 ); // max column name length in mysql is 64
		return ( $clean_column_name ); // may be empty if reduces to empty or a number 
	}	
		
 	
 	// derived from http://php.net/manual/en/features.file-upload.errors.php
   	private static function codeToMessage( $code ) {
    	switch ( $code ) {
      	case UPLOAD_ERR_INI_SIZE:
         	$message = 'The uploaded file chunk exceeds the upload_max_filesize directive in php.ini.';
            break;
			case UPLOAD_ERR_FORM_SIZE: // we don't use this (lit says it does nothing on the client side)-- should be irrelevant
				$message = 'The uploaded file chunk exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
				break;
			case UPLOAD_ERR_PARTIAL:  // UPLOAD_ERR_PARTIAL is given when the mime boundary is not found after the file data.
				$message = 'The uploaded file was only partially uploaded.';
				break;
			case UPLOAD_ERR_NO_FILE:
				$message = 'No file was uploaded.';
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				$message = 'Missing a temporary folder.';
				break;
			case UPLOAD_ERR_CANT_WRITE:
				$message = 'Failed to write file to disk.';
				break;
			case UPLOAD_ERR_EXTENSION:
				$message = 'File upload stopped by extension.';
				break;
			default:
				$message = _( 'Unknown upload error.', 'wp-issues-crm' );
				break;
			}
      	return $message;
	} 
	 
 	private static function decode_parameters ( $form_data ) {
 		
 		$parameters_array = array();
 		
 		$parameters_array['includes_column_headers'] = $form_data->includes_column_headers;
 		
		// sanitize the delimiter by translating to valid delimiter and enforcing default
		$decode_delimiter = array (
			'comma' 	=> ',',
			'semi'		=>	';',
			'tab'		=>	"\t", // double quotes convert the string to a tab character
			'space'		=>	' ',
			'colon'		=>	':',
			'hyphen'	=>	'-',		
			'pipe'		=>	'|',		
		);
		$parameters_array['delimiter'] = isset ( $decode_delimiter[$form_data->delimiter] ) ? $decode_delimiter[$form_data->delimiter] : ',';

		// sanitize the enclosure by translating to valid enclosure and enforcing default
		$decode_enclosure = array (
			'1'		=>	'\'',
			'2'		=>	'"',
			'b'		=> '`',
		); 		
		$parameters_array['enclosure'] = isset ( $decode_enclosure[$form_data->enclosure] ) ? $decode_enclosure[$form_data->enclosure] : '"';

		// override unset, empty, blank or over-escaped escape character
		if ( '\\\\' == $form_data->escapeChar ) {
			$parameters_array['escape'] = "\\";		
		} elseif (  '' == trim( $form_data->escapeChar ) ) {
			$parameters_array['escape'] = "\x1D"; // if blank, throw in the group seperator ASCII character b/c php will default back to \
		} else {
			$parameters_array['escape'] = trim ( $form_data->escapeChar );
		}
		
		// higher max line length means faster loads because fewer packets (but only up to a fairly low point)
		$parameters_array['max_line_length'] 		= $form_data->max_line_length > 100 		?  	$form_data->max_line_length : 100 ;
		$parameters_array['max_execution_time'] 	= $form_data->max_execution_time > 30 	? 	$form_data->max_execution_time  : 30;
		
		// add charset to parameters array
		$parameters_array['charset'] = $form_data->charset;
		
		return ( $parameters_array );
	}

	public static function get_staging_table_record_count ( $id, $dummy ) {

		global $sqlsrv; 

		$staging_table_name = self::get_staging_table_name ( $id );

		if ( $staging_table_name > '' ) {
			$sql = "SELECT STAGING_TABLE_ID AS record_count FROM $staging_table_name ORDER BY STAGING_TABLE_ID DESC OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY";
			$results = $sqlsrv->query ( $sql, array() );
			$record_count = isset ( $results[0]->record_count ) ? $results[0]->record_count : 0 ;
		} else {
			$record_count = 0;
		}
		
		return array ( 'response_code' => 1, 'output' => $record_count ); 

	}

	public static function get_safe_file_size() {

		//  php parameters
		$post_max_size 			=	self::return_bytes( ini_get( 'post_max_size') );
		$upload_max_filesize 	= 	self::return_bytes( ini_get( 'upload_max_filesize') );
		$memory_limit 			=	self::return_bytes( ini_get( 'memory_limit') );

		// limit to 10M in absence of higher limits for all found parameters
		$size_limits = array(
			MAX_FILE_SIZE, // defined by app; should reflect sql server considerations
			$post_max_size 	? $post_max_size : 9999999,
			$upload_max_filesize ? $upload_max_filesize : 9999999, 
			$memory_limit ? $memory_limit/10 : 9999999, // 10 to reflect overhead; guesstimate
		);
		return min( $size_limits );
	}
	
	// https://stackoverflow.com/questions/6846445/get-byte-value-from-shorthand-byte-notation-in-php-ini
	private static function return_bytes($val) {
		$val  = trim($val);

		if (is_numeric($val))
			return $val;

		$last = strtolower($val[strlen($val)-1]);
		$val  = substr($val, 0, -1); // necessary since PHP 7.1; otherwise optional

		switch($last) {
			// The 'G' modifier is available since PHP 5.1.0
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return $val;
	}

	private static function get_staging_table_name ( $id ) {
	
		global $sqlsrv; 
		
		$upload_table = 'upload';
		$sql		  = "SELECT serialized_upload_parameters from $upload_table where ID = ?";
		$results 	  = $sqlsrv->query ( $sql, array( $id ) );
		if ( false === $results ) {
			return array ( 'response_code' => 0, 'output' => "Database error on retrieval of upload table." ); 
		}
		$serialized_upload_parameters = isset ( $results[0]->serialized_upload_parameters ) ?  json_decode ( $results[0]->serialized_upload_parameters ) : '' ;
		$staging_table_name = isset ( $serialized_upload_parameters->staging_table_name ) ? $serialized_upload_parameters->staging_table_name : '';

		return ( $staging_table_name );
	}
	
	// http://php.net/manual/de/function.filesize.php#112996 -- user comment
	public static function FileSizeConvert($bytes) {
	
		$bytes = floatval($bytes);
			$arBytes = array(
				0 => array(
					"UNIT" => "TB",
					"VALUE" => pow(1024, 4)
				),
				1 => array(
					"UNIT" => "GB",
					"VALUE" => pow(1024, 3)
				),
				2 => array(
					"UNIT" => "MB",
					"VALUE" => pow(1024, 2)
				),
				3 => array(
					"UNIT" => "KB",
					"VALUE" => 1024
				),
				4 => array(
					"UNIT" => "B",
					"VALUE" => 1
				),
			);

		foreach($arBytes as $arItem)
		{
			if($bytes >= $arItem["VALUE"])
			{
				$result = $bytes / $arItem["VALUE"];
				$result = str_replace(".", "." , strval(round($result, 1)))." ".$arItem["UNIT"];
				break;
			}
		}
		return $result;
	}

	private static function delete_tables_with_name_stub ( $name_stub ) {
	
		// safety check that received a valid staging table name stub
		if ( false === strpos( $name_stub, 'staging_table' ) ) return false;

		global $sqlsrv;
		$sql = "SELECT name FROM SYSOBJECTS WHERE xtype = 'U' and name like ?";
		$stub_tables = $sqlsrv->query( $sql, array(  $name_stub .'%' ) );

		// run through list of staging tables and delete all matching
		if ( is_array ( $stub_tables ) ) {
			foreach ( $stub_tables as $stub_table ) {
				foreach ( (array) $stub_table as $key => $value ) {
					$sql = "DROP TABLE IF EXISTS $value";
					$sqlsrv->query( $sql, array() );			
				}
			}
		}
	}



} // class
	
	
