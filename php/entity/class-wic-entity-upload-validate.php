<?php
/*
*
*	wic-entity-upload-validate.php
*
*	static functions supporting upload-validate.js
*/


class WIC_Entity_Upload_Validate  {

	public static function reset_validation ( $upload_id, $table  ) {
		
		// reset counts in column map
		$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];
		foreach ( $column_map as $column=>$entity_field_object ) {
			if ( $entity_field_object > '' ) { 
				$entity_field_object->non_empty_count = 0;		
				$entity_field_object->valid_count = 0;
			}
		}
		WIC_Entity_Upload_Map::save_column_map ( $upload_id, json_encode ( $column_map ) );
		
		// reset validation indicators on staging table
		global $sqlsrv;
		$sql = "UPDATE $table SET VALIDATION_STATUS = '', VALIDATION_ERRORS = ''";	
		$result = $sqlsrv->query( $sql, array() );
		return array ( 'response_code' =>  false !== $result, 'output' => false !== $result ?
			'Staging table validation indicators reset.' :
			'Error resetting staging table validation indicators' );
	}

	public static function validate_upload ( $upload_id, $validation_parameters ) {
	
		global $sqlsrv;
				
		// get the column to database field map for this upload
		$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];

		// construct data object array analogous to form, but with only the controls for the matched fields 
		// no multivalue construct in this context -- no multivalue fields are uploadable -- support multivalues as separate lines of input
		$data_object_array = array();
		$valid_values = array(); 

		$address_part_columns = array(); // reverse lookup for the parts of the address
		foreach ( $column_map as $column => $entity_field_object ) {
			// including those columns which have been mapped, except for the address_line_parts			
			if ( '' < $entity_field_object ) {
				if ( substr ( $entity_field_object->field, 0, 17 ) != 'address_line_part' ) {
					$field_rule = get_field_rules( $entity_field_object->entity, $entity_field_object->field ) ;
					$data_object_array[$column] = WIC_Control_Factory::make_a_control( $field_rule->field_type ); 	
					$data_object_array[$column]->initialize_default_values(  $entity_field_object->entity, $entity_field_object->field,  '' );
					// for select fields, set up an array of valid values items for validation loop (avoiding repetitive access)
					if ( method_exists ( $data_object_array[$column], 'valid_values' ) ) { 	// select/multiselect fields			
						$valid_values[$column] = $data_object_array[$column]->valid_values();
					} 
				} else {
					$address_part_columns[$entity_field_object->field] = $column;
				}
			}
		}		

		// get a chunk of records to validate
		$record_object_array = WIC_Entity_Upload::get_staging_table_records(  
			$validation_parameters->staging_table, 
			$validation_parameters->offset ,  
			$validation_parameters->chunk_size,
			'*' 
		);		
		
		// loop through records, use the controls to sanitize and validate each and update each with results
		foreach( $record_object_array as $record ) {
			$errors = '';
			$update_clause_array = array();
			
			// loop through columns for the record -- keying off data_object_array (so excluding the address parts )
			foreach ( $data_object_array as $column => $control ) {
			
				// CONSTRUCTED STREET ADDRESS is mapped IFF there are address_parts
				if ( 'CONSTRUCTED_STREET_ADDRESS' == $column ) {
					$control->set_value ( self::construct_street_address( $record, $address_part_columns ) );
				} else {
					$control->set_value ( $record->$column );
				}
				// since will be testing later for specified valid values and have already escaped data,
				// OK not to sanitize multiselect ( which is expecting an array value for sanitization )
				if ( 'multiselect' != $control->get_control_type() ) {
					$control->sanitize();
				}
				
				// invoke the control's validation routine -- does not generate errors on empty
				$error = $control->validate();
				
				// invoke required checking -- does generate errors on empty if field is "individual" required, but in 3.0 up, this is only post_title and activity_issue
				// 	-- these are link fields for activity, invalid as links if mapped and missing value
				// note that phone, email and address fields are all "group" required, so must be present on record to add it online, but not picked up as error by 
				// 		control->required_check and so can pass through the upload step if missing -- don't intend to force user to have complete file.
				$required_error = ''; 
				$required_error = $control->required_check();
				$error .= $required_error;
				
				// do validation for constituent ID field that doesn't require validation in form context since not user supplied
				// empty will be error for this
				if ( 'constituent' == $column_map->$column->entity && 'ID' == $column_map->$column->field ) {
					$wic_query = 	WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );
					// set up search arguments and parameters
					$search_parameters = array( // accept default search parameters -- 
						'select_mode' => 'id',
						'retrieve_limit' => 2,
					);
					$query_clause =  array (
							array (
								'table'	=> 'constituent',
								'key' 	=> 'ID',
								'value'	=> $control->get_value(),
								'compare'=> '=',
							)
						);
					$wic_query->search ( $query_clause, $search_parameters );
					$error .= ( 1 == $wic_query->found_count ) ? '' : 'Bad constituent ID';
				}
				// do additional validation for sanitization (e.g., date) that reduces input to empty
				if ( '' < $record->$column && '' == $control->get_value() ) {
					$error .= sprintf ( 'Invalid entry for %s -- %s.', $column_map->$column->field, $record->$column ); 					
				}
				
				// validate select fields -- assure that value in options set (whether in options table or function generated per control logic)
				// empty may or may not be valid value
				if ( method_exists ( $control, 'valid_values' ) ) { 
					if ( ! in_array ( $record->$column, $valid_values[$column] ) ) {
						if ( 'issue' == $column_map->$column->field ) {
							// attempt further validation on ID's since may not be eligible for assignment 
							if ( ! WIC_DB_Access_Issue::fast_id_validation( $record->$column ) ) {
								$error .= sprintf ( 'Issue %s is not valid -- may have been moved to trash.', $record->$column ); 
							}						
						} else {
							$error .= sprintf ( 'Invalid entry for %s -- %s.', $column_map->$column->field, $record->$column ); 						
						}
					}					
				}

				// update "non_empty_count" -- will be captioned as "tested"
				if ( $required_error || $control->get_value() > '' ) { 	
					$column_map->$column->non_empty_count++;
				}
				
				// for non-empty column values (as sanitized); 
				if ( $control->get_value() > '' ) { 	
					if ( '' == $error ) {
						// increment count of non-empty valid values for the column
						$column_map->$column->valid_count++;
						// set up update of staging table with sanitized value if non-empty and valid					
						$update_clause_array[] = array (
							'column' => $column,
							'value'	=> $control->get_value(),						
						);					
					} 
				}

				// accumulate all errors across columns for record; note that empty is not an error unless field is required
				$errors .= $error;
			}
			
			// update stating table record with validation results
			// this parallels update process for forms, but is distinct since only updating the staging table -- can't use same functions
			$staging_table = $validation_parameters->staging_table;
			$id = $record->STAGING_TABLE_ID;
			// code validation_status -- empty error is valid
			$validation_status = ( '' == $errors ) ? 'y' : 'n';
			// set up update sql from array
			$record_update_string = ' VALIDATION_STATUS = ?, VALIDATION_ERRORS = ? ';
			$record_update_array = array( $validation_status, $errors );
			if ( count ( $update_clause_array ) > 0 ) {
				foreach ( $update_clause_array as $update_clause ) {
					$record_update_string .= ', ['. $update_clause['column'] . '] = ? '; // column headers could be sql reserved words; use brackets		
					$record_update_array[] = $update_clause['value'];
				}
			}
			$record_update_array[] = $id;
	
			// run the update -- result = affected record count.  anything other than 1 is an error in this context. false is a database error.
			if ( 1 != $sqlsrv->query( "UPDATE $staging_table SET $record_update_string WHERE STAGING_TABLE_ID = ?", $record_update_array ) ) {
				return array ( 'response_code' => false, 'output' =>sprintf( 'Error recording validation results for record %s', $record->STAGING_TABLE_ID ) );
			}
		}
		
		// update the column map with the counts for the processed chunk of records
		$result = WIC_Entity_Upload_Map::save_column_map ( $upload_id, json_encode ( $column_map ) );
		if ( false === $result ) {
			return array ( 'response_code' => false, 'output' =>sprintf( 'Error updating validation totals.', $record->STAGING_TABLE_ID ) );
		}
		
		return array ( 'response_code' => true, 'output' => self::prepare_validation_results ( $column_map ) );

	}

	
	public static function prepare_validation_results ( $column_map ) {
				
		$table =  '<table id="validation-results" class="wp-issues-crm-stats"><tr>' .
			'<th class = "wic-statistic-text">' . 'Upload Field' . '</th>' .
			'<th class = "wic-statistic-text">' . 'Mapped to WP Issues CRM Field' . '</th>' .
			'<th class = "wic-statistic">' . 'Tested Count' . '</th>' .
			'<th class = "wic-statistic">' . 'Valid Count' . '</th>' .
		'</tr>';

		foreach ( $column_map as $column => $entity_field_object ) { 
			if ( $entity_field_object > '' && substr ( $entity_field_object->field, 0, 17 ) != 'address_line_part') {
				if ( 0 == $entity_field_object->non_empty_count ) {
					$validation_level_coloring = 'upload-validation-bad';
				} elseif ( $entity_field_object->non_empty_count == $entity_field_object->valid_count ) {
					$validation_level_coloring = 'upload-validation-perfect';
				} elseif ( $entity_field_object->valid_count / $entity_field_object->non_empty_count > .95 ) {
					$validation_level_coloring = 'upload-validation-ok';
				} else {
					$validation_level_coloring = 'upload-validation-bad';				
				}
				$table .= '<tr>' .
					'<td class = "wic-statistic-table-name">' . $column . '</td>' .
					'<td class = "wic-statistic-text" >' . $entity_field_object->field . ' (' .  $entity_field_object->entity . ')</td>' .
					'<td class = "wic-statistic" >' . $entity_field_object->non_empty_count . '</td>' .
					'<td class = "wic-statistic ' . $validation_level_coloring . '" >' . $entity_field_object->valid_count  . '</td>' .
				'</tr>';
			}
		}
		
		$table .= '</table>';	
	
		return ( $table );

	}	

	public static function construct_street_address( $record, $address_part_columns ) {
		
		$part_7_is_set = false;
		$constructed_street_address = '';
		for ( $k = 1; $k < 9; $k++ ) {
			$property_name = isset ( $address_part_columns['address_line_part_' . $k] ) ? $address_part_columns['address_line_part_' . $k] : '' ;
			if ( $property_name > '' ) {
				if ( isset ( $record->$property_name ) ) {
					if ( 7 == $k )  {
						$part_7_is_set = true;
					}
					$trimmed = trim ( $record->$property_name, "#., \t\n\r\0\x0B" ); 
					if ( $trimmed > '' ) {
						$constructed_street_address .= ( ( $constructed_street_address > '' && $k > 2 ) ? ( 8 == $k && !$part_7_is_set ? ' APT ' : ' '  ) : '' ) . $trimmed;
					}
				}
			}
		}
		return $constructed_street_address ;
	}

}

