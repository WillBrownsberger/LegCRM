<?php
/*
*
* class-wic-entity-upload-match.php
*
*
* 
*/

 class WIC_Entity_Upload_Match {
		/*
	*
	* functions to support matching
	*
	*/	

	// reset_match also initializes match_results if not previously matched 
	// also resets defaults
	public static function reset_match ( $upload_id, $data  ) {

		// in case previously matched, bust back to validated so that if match fails midstream will be forced to come back
		WIC_Entity_Upload::update_upload_status ( $upload_id, 'validated' );

		global $sqlsrv;

		// reset counts in column map
		$match_results = self::get_match_results ( $upload_id ) ;
		foreach ( $match_results as $slug=>$match ) {
			$match->order = 0;
			$match->total_count = 0;
			$match->have_components_count = 0;
			$match->have_components_and_valid_count = 0;
			$match->have_components_not_previously_matched = 0;
			$match->matched_with_these_components = 0;
			$match->not_found = 0;
			$match->not_unique = 0;						
			$match->unmatched_unique_values_of_components = '?';
		}

		// unset NOMATCH in case it was was added by a match bypass
		unset  ( $match_results->NOMATCH );
		
		// capture user decisions about which match strategies to use and in what order
		$order_counter = 0;
		foreach ( $data->usedMatch as $slug ) {
			$order_counter++; // don't start at 0 is 0 means not used
			$match_results->$slug->order = $order_counter;		
		}		

		// save fresh array ready to get started		
		self::update_match_results ( $upload_id, $match_results );

		// reset default settings -- see notes above
		WIC_Entity_Upload_Set_Defaults::update_default_decisions ( $upload_id, '' );
		// reset validation indicators on staging table
		$table = $data->table;
		$sql = "UPDATE $table SET MATCHED_CONSTITUENT_ID = 0, MATCH_PASS = '', FIRST_NOT_FOUND_MATCH_PASS = '', NOT_FOUND_VALUES = '' ";	
		$result1 = $sqlsrv->query( $sql, array() ); // 0 is an OK result if reset did nothing
		$unmatched_table = $data->table . '_unmatched';
		$sql = "DROP TABLE IF EXISTS $unmatched_table";
		$result2 = $sqlsrv->query( $sql, array() );		
		$result = $result1 !== false && $result2 != false ;

		return array ( 'response_code' => false !== $result, 'output' => false !== $result ? 
			'Staging table match indicators reset.' 
			:
			'Error resetting staging table match indicators' 
		);		
	}

	/*
	*
	* match_upload answers AJAX call to test match a chunk of staging table records
	* 	according to the matching rules for the particular match pass 
	* does database lookups and records interim results
	* returns updated result table
	*
	*
	*/
	public static function match_upload ( $upload_id, $match_parameters ) {
	
		global $sqlsrv;
	
		// get the current staging row		
		$match_results = self::get_match_results ( $upload_id );
		$match_rule = $match_results->{$match_parameters->working_pass};
		$match_fields_array = $match_rule->link_fields;

		// get the column to database field map for this upload
		$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];		

		// set up an array of the columns being used to create staging table retrieval sql and minimize size of retrieval array
		$column_list_array = array();		
		// look up match fields in column array to get back to input column, add to match field array
		foreach ( $match_fields_array as &$match_field ) { // passing by pointer so directly modify array element
			foreach ( $column_map as $column => $entity_field_object ) {
				if ( '' < $entity_field_object ) { // unmapped columns have an empty entity_field_object
					if ( $match_field[0] == $entity_field_object->entity && $match_field[1] ==  $entity_field_object->field ) {
						$match_field[3] = $column;
						$column_list_array[] = $column;	
					}
				}		
			}
		}  	

		unset ( $match_field ); // this is critical -- surprising results results in for loop further below if not done; 
										// see http://php.net/manual/en/control-structures.foreach.php -- reference remains after loop
		$column_list = implode ( ',', $column_list_array );		
		// get a chunk of records to validate
		$record_object_array = WIC_Entity_Upload::get_staging_table_records(  
			$match_parameters->staging_table, 
			$match_parameters->offset,  
			$match_parameters->chunk_size, 
			$column_list
		);		
		
		// create a constituent db object for repetitive use in the look up loop
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' ); 

		// define consistent search parameters for use with lookups
		$search_parameters = array (
			'select_mode' 		=> 'id', 	// only want id back
			'sort_order' 		=> false, 	// don't care for sort
			'compute_total' 	=> false,	// no need to find total of all dups	
			'retrieve_limit'	=> 2,			// one dup is too many
		);


		// loop through records, if have necessary fields, look for match using the standard query construct
		foreach( $record_object_array as $record ) {

			// reinitialize meta query array
			$meta_query_array = array();

			// keep overall tally -- should be same in all passes
			$match_rule->total_count++;

			// necessary values present? (otherwise store temporarily in position 4)
			$missing = false;
			foreach ( $match_fields_array as $match_field ) { 	
				if ( ''   ==  $record->{$match_field[3]} ) { // php 7.1 {}
					$missing = true;
					break;
				} else {
					$meta_query_array[] = array (
						'table'	=> $match_field[0],
						'key' 	=> $match_field[1],
						'value'	=> 0 == $match_field[2] ? $record->{$match_field[3]} : WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words ( $record->{$match_field[3]}, $match_field[2], 1 ),  // php 7.1 {}
						'compare'=> 0 == $match_field[2] ? '=' : 'like',
					);					
				}			
			}	
	
			if ( ! $missing ) {
				$match_rule->have_components_count++; // $match_field[4] fully populated for all match fields
			} else {
				continue;			
			}			
			// valid?
			if ( 'y' == $record->VALIDATION_STATUS ) {
				$match_rule->have_components_and_valid_count++;			
			} else {
				continue;			
			}	
			// not already matched? note, may have already been "not found"
			if ( '' == $record->MATCH_PASS ) {
				$match_rule->have_components_not_previously_matched++;			
			} else {
				continue;			
			}	 		
			// construct sql from link fields, do lookup field 
			$wic_query->search ( $meta_query_array, $search_parameters );
			// now, maintain pass tallies and record outcome on staging table
			// first, initialize match_pass found variables -- populated below only in case of found match
			// and not previously populated (don't reach these lines at all if previously populated)			
			$match_pass = '';
			$matched_constituent_id = 0;
			// initialize $not_found_match_pass recording variables -- these are populated below only in case of
			// not_found match and not already populated -- so, it holds the first (should be most unique) pass in which
			// all necessary variables were present for matching and the values of those variables -- 
			// a subsequent less unique pass may result in a match in which case these won't be used, but if no match in any 
			// pass, these will be used to create constituent stub for insertion in final upload completion stage
			// note that all invalid records are bypassed above, so need not test for validity
			$first_not_found_match_pass 	= '';
			$not_found_values					= ''; 	
			// not found case -- populate not found pass and values
			if ( 1 > $wic_query->found_count ) { // i.e, found count = 0
				$match_rule->not_found++;	
				if ( '' == $record->FIRST_NOT_FOUND_MATCH_PASS  ) {
					$first_not_found_match_pass = $match_parameters->working_pass;
					foreach ( $match_fields_array as $match_field ) {
						$not_found_values .= ( 0 == $match_field[2] ) ? 
							$record->{$match_field[3]} : WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words ( $record->{$match_field[3]}, $match_field[2], 1 );  // php 7.1 {}
					}	 				
				} 
			// found at least one case -- populate match pass (which means no matching will be attempted on future passes) and set matched id
			} elseif ( 0 < $wic_query->found_count ) {
				$match_rule->matched_with_these_components++;
				$match_pass = $match_parameters->working_pass;
				$matched_constituent_id = $wic_query->result[0]->ID;
				// found multi case -- populate match pass, and match to first, but count as not-unique (user can decide to rematch, but doesn't have to) 
				if ( 1 < $wic_query->found_count ) {
					$match_rule->not_unique++;
				}
			}
			// mark staging table with outcome if there was match in this pass; 
			// stamping with match_pass indicates that there was a match; 
			// stamping the match_id indicates that the match was accepted (unique or first found)
			if ( '' < $match_pass || '' < $first_not_found_match_pass ) {
				// should have exactly one non-blank value
				if  ( '' < $match_pass && '' < $first_not_found_match_pass ) {
					return array ( 'response_code' => false, 'output' =>sprintf( 'Inconsistent values for record  %s at match recording', $record->STAGING_TABLE_ID ) );	
				}
 				// set up to update either the found variables or the not found variables  
				$staging_table = $match_parameters->staging_table; 
				$staging_id = $record->STAGING_TABLE_ID;
				$values = array();
				if ( '' < $match_pass ) {
					$set_clause = " SET MATCH_PASS = ?, MATCHED_CONSTITUENT_ID = ? ";
					$values[] = $match_pass;
					$values[] = $matched_constituent_id;
				} else {
					$set_clause =  " SET FIRST_NOT_FOUND_MATCH_PASS = ?, NOT_FOUND_VALUES = ? ";
					$values[] = $first_not_found_match_pass;
					$values[] = $not_found_values ;
				}
				$values[] = $staging_id;
				$sql = "UPDATE $staging_table 
					$set_clause  
					WHERE STAGING_TABLE_ID = ?";
				$result = $sqlsrv->query( $sql, $values );
				
				// $result = record count.  anything other than 1 is an error in this context. false is a database error.
				if ( 1 != $result ) {
					return array ( 'response_code' => false, 'output' => sprintf ( 'Error recording match results for record %s', $record->STAGING_TABLE_ID ) );
				}
			}
		}
		// update the match_results array with the counts
		$match_results->{$match_parameters->working_pass} = $match_rule;
		// save the match results array
		self::update_match_results ( $upload_id, $match_results );

		$table = self::prepare_match_results ( $match_results );
		return array ( 'response_code' => true, 'output' => $table );
			
	}

	/*
	*	In this step, create an intermediate table grouping staging table by the identifiers in the match process --
	*			group by the first match stage for which record had all available identifiers; include only those not matched in any pass
	*	This "_unmatched" table includes constituent stubs with all fields on the constituent entity and pointers back to staging table records.
	*	Complete report of unmatched counts
	*   Note:  no longer enforcing group required identifier -- below version 3.0, required at least one of fn/ln/email to be present to process record
	*
	*/	
	public static function create_unique_unmatched_table ( $upload_id, $match_parameters ) {
		$staging_table = $match_parameters->staging_table;
		extract ( self::create_constituent_field_arrays( $upload_id ) );   // $constituent_field_array, $staging_table_column_array	

		// get match result array ( has everything but the unmatched filled in at this stage )
		$match_results = WIC_Entity_Upload_Match::get_match_results( $upload_id );

		// set all unmatched counters to 0 -- initialized as '? up to this point (next step may not hit them all)
		foreach ( $match_results as $rule ) {
			$rule->unmatched_unique_values_of_components = 0;			
		}	

		// first create the table which will hold the unmatched records
		$unmatched_staging_table = $staging_table . '_unmatched'; 
		global $sqlsrv;
		
		// use all the available constituent columns (constituent level data items that have been mapped to)
		// these are items like first name, last name, date of birth that exist on the constituent database record
		$sql = "CREATE TABLE $unmatched_staging_table ( "; 
		foreach ( $constituent_field_array as $field ) {
			$sql .= ' ' . $field . ' varchar(max) NOT NULL, ';
		}
		// . . . include standardized tracking columns in the table too
		$sql .=  'STAGING_TABLE_ID bigint IDENTITY(1,1) PRIMARY KEY,
					FIRST_NOT_FOUND_MATCH_PASS varchar(50) NOT NULL,
					MATCHED_CONSTITUENT_ID bigint NOT NULL,
					STAGING_TABLE_ID_STRING varchar(max) NOT NULL )';
		$result = $sqlsrv->query ( $sql, array() );
		$result = $sqlsrv->query ( "CREATE INDEX ix_{$unmatched_staging_table}_FIRST_NOT_FOUND_MATCH_PASS ON {$unmatched_staging_table} (FIRST_NOT_FOUND_MATCH_PASS)", array());

		if ( false === $result ) {
			return array ( 'response_code' => false, 'output' => 'Error creating unique values table.' );		
		}

		// now populate that table with unique unfound values 
		// from the staging table, collect all input columns mapped to constituent level data items
		// MATCH_PASS = '' means never matched.  Might have FIRST_NOT_FOUND_MATCH_PASS = '' if lacked values for all passes
		$select_column_list = '';  
		foreach ( $staging_table_column_array as $column ) {
			$select_column_list .= ' MAX([' . $column . ']), ';		
		}
		$values_list = "[" . implode( "],[", $constituent_field_array) . "]"; 
		
		$sql = 	"INSERT INTO $unmatched_staging_table ( $values_list, FIRST_NOT_FOUND_MATCH_PASS, MATCHED_CONSTITUENT_ID, STAGING_TABLE_ID_STRING) 
					SELECT $select_column_list FIRST_NOT_FOUND_MATCH_PASS, 0, STRING_AGG( CAST(STAGING_TABLE_ID AS VARCHAR(max)),',' )
					FROM $staging_table 
					WHERE MATCH_PASS = '' AND FIRST_NOT_FOUND_MATCH_PASS > '' AND VALIDATION_STATUS = 'y'
					GROUP BY FIRST_NOT_FOUND_MATCH_PASS, NOT_FOUND_VALUES
					";
		// populate table using the selected sql ( dependent on $unmatch_all )
		$insert_count = $sqlsrv->query ( $sql, array() );
		if ( false === $insert_count ) {
			return array ( 'response_code' => false, 'output' => 'Error inserting records in unique values table.' );		
		} 

		// calculate updateable records remaining unmatched from each match pass (after all matches done )
		$sql = "
				SELECT FIRST_NOT_FOUND_MATCH_PASS as pass_slug, COUNT(FIRST_NOT_FOUND_MATCH_PASS) AS pass_count
				FROM $staging_table 
				WHERE MATCH_PASS = '' AND FIRST_NOT_FOUND_MATCH_PASS > '' 
				GROUP BY FIRST_NOT_FOUND_MATCH_PASS
				";
		$result_array = $sqlsrv->query ( $sql, array() );
		if ( false !== $result_array ) {
			if ( 0 != count ( $result_array ) ) { 
				foreach ( $result_array as $result ) { 
					$match_results->{$result->pass_slug}->unmatched_records_with_valid_components = $result->pass_count;		
				}
			}	
		}

		// calculate unique counts from each match pass
		$sql = "SELECT FIRST_NOT_FOUND_MATCH_PASS as pass_slug, COUNT(FIRST_NOT_FOUND_MATCH_PASS) AS pass_count
				FROM $unmatched_staging_table 
				GROUP BY FIRST_NOT_FOUND_MATCH_PASS";
		$result_array = $sqlsrv->query ( $sql, array() );
		if ( false === $result_array ) {
			return array ( 'response_code' => false, 'output' => 'Error creating unique values table.' );		
		} 

		if ( 0 != count ( $result_array ) ) { 
			foreach ( $result_array as $result ) { 
				$match_results->{$result->pass_slug}->unmatched_unique_values_of_components = $result->pass_count;		
			}
		}
		
		
		// this may reflect all 0's in unmatched unique if did not find group identifiers 	
		WIC_Entity_Upload_Match::update_match_results ( $upload_id, $match_results );
		
		return array ( 'response_code' => true, 'output' => self::prepare_match_results ( $match_results ) );
	
	}

	
	// create parallel arrays of WIC constituent fields and associated fields in the upload -- working directly from column map
	public static function create_constituent_field_arrays( $upload_id ) {
		
		$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];

		$constituent_field_array = array();
		$staging_table_column_array = array();	

		foreach ( $column_map as $column => $entity_field_object ) {
			if ( '' < $entity_field_object ) { // unmapped columns have an empty entity_field_object
				if ( 'constituent' == $entity_field_object->entity  ) {
					$constituent_field_array[] = $entity_field_object->field;
					$staging_table_column_array[] = $column;	
				}
			}		
		}	
		return array ( 'constituent_field_array' => $constituent_field_array, 'staging_table_column_array' => $staging_table_column_array );
	
	}




	public static function prepare_match_results ( $match_results ) {
		
		// extract the active match rules
		$active_match_rules = array();
		foreach ( $match_results as $slug => $match_object ) {
			if ( 0 < $match_object->order ) {
				$active_match_rules[$match_object->order]	= $match_object;		
			}		
		}
		ksort ( $active_match_rules );
						
		$table =  '<table class="wp-issues-crm-stats">' .
		'<tr><td></td>	<th class = "wic-statistic-text" colspan="4">' . 'Match pass input' . '</th>' .
							'<th class = "wic-statistic-text" colspan="4">' . 'Match pass results' . '</th>' .	
							'</tr>' .
		'<tr>' .
			'<th class = "wic-statistic-text wic-statistic-long">' . 'Match Pass' . '</th>' .
			'<th class = "wic-statistic">' . 'Total records' . '</th>' .
			'<th class = "wic-statistic">' . 'With match data' . '</th>' .
			'<th class = "wic-statistic">' . ' ... also with no errors' . '</th>' .
			'<th class = "wic-statistic">' . ' ... also not already matched' . '</th>' .
			'<th class = "wic-statistic">' . 'Matched' . '</th>' .
			'<th class = "wic-statistic">' . 'Not found' . '</th>' .
			'<th class = "wic-statistic">' . 'Matched multiple (took first)' . '</th>' .
			'<th class = "wic-statistic">' . 'Unmatched unique value combos' . '</th>' .
		'</tr>';

		$total_matched = 0;
		$total_not_unique = 0;
		$total_unique_unmatched_values = 0;

		foreach ( $active_match_rules as $order => $upload_match_object ) { 
			$table .= '<tr>' .
				'<td class = "wic-statistic-table-name">' . $upload_match_object->label . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->total_count  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->have_components_count  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->have_components_and_valid_count  . '</td>' .			
				'<td class = "wic-statistic" >' . $upload_match_object->have_components_not_previously_matched  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->matched_with_these_components  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->not_found  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->not_unique  . '</td>' .
				'<td class = "wic-statistic" >' . $upload_match_object->unmatched_unique_values_of_components  . '</td>' .
			'</tr>';
			
			$total_matched += $upload_match_object->matched_with_these_components;
			$total_not_unique += $upload_match_object->not_unique;
			if ( '?' !== $upload_match_object->unmatched_unique_values_of_components ) {
				$total_unique_unmatched_values += $upload_match_object->unmatched_unique_values_of_components;
			} else {
				$total_unique_unmatched_values = '<em>?</em>';
			}
		}
		$table .= 	'<tr>' .
						'<td class = "wic-statistic-table-name">' . 'Total, all passes:' . '</td>' .
						'<td  colspan="4"></td>' .
						'<td class = "wic-statistic" >' . $total_matched . '</td>' . 
						'<td class = "wic-statistic" ></td>' .
						'<td class = "wic-statistic" >' . $total_not_unique . '</td>' .
						'<td class = "wic-statistic" >' . $total_unique_unmatched_values . '</td>' . 
						'<tr>' ;						
		$table .= '</table>';	
	
		return ( $table );

	}	

	// quick update
	public static function update_match_results ( $upload_id, $unserialized_match_results ) {
		global $sqlsrv;
		$serialized_match_results = json_encode ( $unserialized_match_results );
		$table = 'upload';
		$sql = "UPDATE $table set serialized_match_results = ? WHERE ID = ?";
		$result = $sqlsrv->query( $sql, array($serialized_match_results, $upload_id ));
		return ( $result );
	}	

	// quick look up
	public static function get_match_results ( $upload_id ) {
		global $sqlsrv;
		$table = 'upload';
		$sql = "SELECT serialized_match_results FROM $table where ID = ?";
		$result = $sqlsrv->query( $sql, array( $upload_id ) );
		return ( json_decode( $result[0]->serialized_match_results ) );
	}

}

