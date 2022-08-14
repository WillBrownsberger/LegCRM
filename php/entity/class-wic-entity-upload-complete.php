<?php
/*
*
*	wic-entity-upload-complete.php
*
*	static functions supporting upload-complete.js
*/


class WIC_Entity_Upload_Complete  {

	public static function complete_upload ( $upload_id, $data ){
		/* 
		*  data object properties include
		*  phase -- what upload function to call
		*  staging_table
		*  offset		
		*  chunk_size
		*  express (true or false)
		*/
		$method_to_call = 'complete_upload_' . $data->phase; 	
		// call underlying function for phase
		$result = self::$method_to_call( $upload_id, $data->staging_table, $data->offset, $data->chunk_size, $data->express );

		return array ( 'response_code' => $result !== false, 'output' => $result !== false ? 
			$result 
			:
			sprintf ( 'Error in upload completion -- phase %s.' , $data->phase ) 		
		);
	}
	
	/*	
	*
	* functions to support final completion of upload
	* completion is a three phase process:
	*	- add new issues if any
	*	- add new constituents if any
	*	- apply updates to old and new constituents
	*		in the case of the new constituents, the updates are additions of address, email, phone or activity records
	*		in case of old, same and/or updates to the base constituent record
	*	- results are retained serialized_final_results field on the upload table record
	*
	*/
	
	// quick look up -- includes initial construction of value if not already there
	public static function get_final_results ( $upload_id ) {

		global $sqlsrv;
		$sql = "SELECT serialized_final_results FROM upload where ID = ? AND OFFICE = ?";
		$result = $sqlsrv->query( $sql, array ( $upload_id, get_office()) );

		if ( $result[0]->serialized_final_results > '' )
			return json_decode ( $result[0]->serialized_final_results );
		// if still blank, then this is first pass -- return starting object with 0 values
		else { 
			$stub = '{
				"new_issues_saved":0,
				"new_constituents_saved":0, 
				"input_records_associated_with_new_constituents_saved":0, 
				"constituent_updates_applied":0, 
				"total_valid_records_processed":0
				}';
			return json_decode ( $stub ) ;				
		}

	}
		
	// quick update
	public static function update_final_results ( $upload_id, $serialized_final_results ) {
		global $sqlsrv;
		$table = 'upload';
		$sql = "UPDATE $table set serialized_final_results = '$serialized_final_results' WHERE ID = ? and office = ?";
		$result = $sqlsrv->query( $sql, array( $upload_id, get_office()) );
		return ( $result );
	}	
	
	// first phase of upload completion
	public static function complete_upload_save_new_issues ( $upload_id, $staging_table, $offset, $chunk_size, $express ) {
		// note that this function does not actually use $offset and $chunk-size -- does single save process, unphased.
		// never called with express true -- variable disregarded

		// set globals
		global $sqlsrv;
		$table = $staging_table . '_new_issues';
		
		// get result count object -- get will create blank object, since this is first phase of uplod
		$final_results = self::get_final_results ( $upload_id ) ;

		/*
		* is there a new issues table at all -- not created if no titles mapped
		* belt and suspenders -- check that new issues table exists -- shouldn't be calling this if it doesn't
		* see wic-upload-complete.js:  this workphase is not invoked unless option to create new issues is selected and
		*  . . . see wic-upload-set-defaults.js . . . that option is not offered unless title table is created
		* -- title table is created in wic-upload-set-defaults.js to show user what titles will be created
		*/ 
		$post_table = 'issue';
		$new_issues_saved = 0;
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ? ";
		$result = $sqlsrv->query ( $sql, array( $table ) );
		
		// if table was not found, $new_issues_saved stays 0; if found, it is a valid table name		
		if ( 0 < count ( $result ) ) {
			// select new issues that are still not inserted to assure rerunnability
			// if have already added them inserted_post_id will have been set > 0
			$sql = "SELECT * FROM $table WHERE inserted_post_id = 0";
			$new_issues = $sqlsrv->query( $sql, array() );

			// submit each record in the issue table for saving as new post 
			foreach ( $new_issues as $new_issue ) {
				$id_to_save = WIC_DB_Access_Issue::quick_insert_post ( $new_issue->new_issue_title, $new_issue->new_issue_content,'post', $category = 'uncategorized' );
				$new_issue_id = $new_issue->new_issue_ID;				
				
				// update issue staging table with new post ID 
				$sql = "UPDATE $table SET inserted_post_id = ?  WHERE new_issue_ID = ?";
				$sqlsrv->query ( $sql, array( $id_to_save, $new_issue_id ) );
				$new_issues_saved++;
			}				
		} 
		
		// save issue count and return the final results object as updated	(+= for restart)
		$final_results->new_issues_saved += $new_issues_saved;		
		self::update_final_results( $upload_id, json_encode ($final_results ) );
		return ( $final_results ) ;		
	}
	
	/*
	*
	* Save the new constituents ( previously identified through the matching process )
	*
	*/
	private static function complete_upload_save_new_constituents	( $upload_id, $staging_table, $offset, $chunk_size, $express ) {
		
		// set globals
		global $sqlsrv;
		
		
		// construct data object array with only the controls we need to spoof form data entry
		$data_object_array = array(); 
		
		// if running express, run directly off staging table
		$table = $express ? $staging_table : $staging_table . '_unmatched';
		
		// get the current counts -- from prior phase or pass
		$final_results = self::get_final_results ( $upload_id );
		
		/*
		* is there a new constituent table at all -- not created if no constituents unmatched
		* belt and suspenders -- again, shouldn't be calling this if it doesn't exist
		* see wic-upload-complete.js:  this phase is not invoked unless option to create new constituent is selected and
		*  . . . see wic-upload-set-defaults.js . . . that option is not offered to set unless there are new constituents to save
		*/ 
		$sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ? ";
		$result = $sqlsrv->query ( $sql, array ( $table ) );

		// initialize count variables
		$new_constituents_saved = 0;
		$input_records_associated_with_new_constituents_saved = 0; // multiple input records can group to a single new constituent
		
		// skip processing if no unmatched table found
		if ( 0 < count ( $result ) ) {
			$sql = "SELECT * FROM $table ORDER BY STAGING_TABLE_ID OFFSET ?  ROWS FETCH NEXT ? ROWS ONLY";
			$new_constituents = $sqlsrv->query( $sql, array($offset, $chunk_size) );

			// get array of columns in table that are mapped to constituent fields -- 
			// same selection of columns used in previous construction of $table 
			// see create_unique_unmatched_table
			$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];
			// create reverse lookup for use in express processing of staging table
			$field_to_column_array = array();
			foreach ( $column_map as $column => $entity_field_object ) {
				if ( '' < $entity_field_object ) { // unmapped columns have an empty entity_field_object
					if ( 'constituent' == $entity_field_object->entity ) {
						$field_rule = get_field_rules ( 'constituent', $entity_field_object->field );
						$data_object_array[$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
						$data_object_array[$field_rule->field_slug]->initialize_default_values(  'constituent', $field_rule->field_slug, '' );	
						$field_to_column_array[$entity_field_object->field] = $column;			
					}
				}		
			}	
			
			/*
			*	need ID in the array for updates through the query object
			*	note that the possible situations here are as follows:
			*		a) ID is not in the object array, so not also not on the staging table -- clean; just create new id's
			*			staging table records will be sent to method db_save with ID = 0 and get new ID in save process
			*		b) ID is in the object array so also on the staging table and, by logic enforced in upload_match_strategies, the only match field
			*			-- if 0, is not valid, not in unmatched table
			*			-- if empty, not matchable, so not capable of being not found, so not in the unmatched table
			*			-- if non-empty valid, then matched (b/c must be validation for ID is match) , not in the unmatched table
			*			-- if non-empty not valid, not in unmatched table
			*	In other words, if ID is mapped, unmatched table will be created, but is always empty.
			*/
			if ( ! isset ( $data_object_array['ID'] ) ) {
				$field_rule = get_field_rules ( 'constituent', 'ID' );					
				$data_object_array[$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
				$data_object_array[$field_rule->field_slug]->initialize_default_values(  'constituent', $field_rule->field_slug, '' );
			}
			$data_object_array['ID']->set_value ( 0 );
			
			// create a db access object to which will spoof partial forms			
			$wic_access_object = WIC_DB_Access_Factory::make_a_db_access_object( 'constituent' );
			
			// process the unmatched file (or primary file in express)
			foreach ( $new_constituents as $new_constituent ) { 

				// first test to see if this job might have already been done -- assuring rerunnability
				// just skip the record if already processed
				if ( 0 == $new_constituent->MATCHED_CONSTITUENT_ID ) { 	
					
					// populate data_object_array with values from staging table (none allowed are multivalue)
					foreach ( $data_object_array as $field => $control ) {
						// if express, running direct from staging table, must translate fields to column names
						if ( $express ) {
							$column = isset ( $field_to_column_array[$field] ) ? $field_to_column_array[$field] : '';
							if ( $column  > '' ) {
								if ( isset ( $new_constituent->$column ) ) {
									$control->set_value ( $new_constituent->$column );
									$control->sanitize(); // must sanitize here because bypassed on express
								}
							}
						// if not express, running on _unmatched table which has translated column names already
						} else {
							if ( isset ( $new_constituent->$field ) ) {				// test isset b/c may not have ID among fields
								$control->set_value( $new_constituent->$field ); 	// should loop through all $new_constituent fields
							}
						}
					}
					
	
					// use database object -- no search_logging or extra time stamps in this:  efficient
					// also does prepare on all values, so this is as robust as form -- validated, sanitized, now escaped
					$wic_access_object->save_update( $data_object_array );
					// if save successful, log save in several ways 
					if ( $wic_access_object->outcome ) {
						
						// do count for the final results 
						$new_constituents_saved++;

						// update the unmatched table (or, if express, the staging table ) -- this is to assure rerunnability
						$id_to_save = $wic_access_object->insert_id;
						$id_to_update = $new_constituent->STAGING_TABLE_ID;
						$inserted_new_phrase = $express ? ", INSERTED_NEW = 'y' " : "";  // if express maintaining the staging table
						$sql = "UPDATE $table SET MATCHED_CONSTITUENT_ID = ? $inserted_new_phrase
							WHERE STAGING_TABLE_ID =  ?";
						$result = $sqlsrv->query ( $sql, array($id_to_save, $id_to_update) ); 
						
						// if not express, have to update the staging table
						if ( ! $express ) {
							// update the possibly multiple staging table records with the insert ID		
							$staging_table_id_string =  $new_constituent->STAGING_TABLE_ID_STRING ;
							$staging_table_id_array  =  explode ( ',', $staging_table_id_string );
							// set up the right number of ?
							$in_parms = '';
							foreach ( $staging_table_id_array as $id ) {
								$in_parms .= ',?';
							}
							$in_parms = trim ( $in_parms,',');
							// prepend id_to_save
							array_unshift( $staging_table_id_array, $id_to_save );
							// posting insert ID's back to the original staging table
							$sql = "UPDATE $staging_table SET MATCHED_CONSTITUENT_ID = ?, INSERTED_NEW = 'y' 
									WHERE STAGING_TABLE_ID IN  ( $in_parms )";
							$result = $sqlsrv->query ( $sql, $staging_table_id_array );
							if ( $result !== false ) {
								$input_records_associated_with_new_constituents_saved += ( count ( $staging_table_id_array ) - 1 ); // -1 b/c unshifted MATCHED_CONSTITUENT_ID on to it
							} 
						}	// close not express 
					}  // close did successful save?
				}	// close test for already processed 
			}	// close loop through unmatched table
		}	// close unmatched table found

		$final_results->new_constituents_saved += $new_constituents_saved;
		if ( $express ) {
			$final_results->input_records_associated_with_new_constituents_saved += $new_constituents_saved;
		} else {
			$final_results->input_records_associated_with_new_constituents_saved += $input_records_associated_with_new_constituents_saved;
		}
		self::update_final_results( $upload_id, json_encode( $final_results ) );
		return ( $final_results ) ;		
	}
	
	/*
	*
	* it all builds to this! -- final update of constituents
	*
	*/
	private static function complete_upload_update_constituents	( $upload_id, $staging_table, $offset, $chunk_size, $express ) {

		global $sqlsrv;
		
		
		$final_results = self::get_final_results ( $upload_id );
		
		// before doing any updates, if on first pass, purge any activities saved on a failed attempt and reset update counters
		// don't purge emails, phones or addresses, since update these if already exist, but activities can be duped
		// note that update counters may be incorrect in case of repeated failures in update stage -- reset to 0, but non-activity updates not reversed
		if ( 0 == $offset ) {
			self::purge_activities_for_upload_id ( $upload_id );
			$final_results->constituent_updates_applied = 0;		
			$final_results->total_valid_records_processed = 0;
			$final_results_interim = json_encode ( $final_results );
			self::update_final_results( $upload_id, $final_results_interim );
		}
		
		// have multiple entities to update -- create array of data object arrays for each
		$data_object_array_array = array(
			'constituent' 	=> array(),
			'address'		=> array(),
			'email'			=> array(),
			'phone'			=> array(),		
			'issue'			=> array(),	// order matters -- need to process issue before activity to look up title if using it	
			'activity'		=> array(),
		); 
		
		// set counters of activity for this pass
		$constituent_updates_applied 	= 0; // counts non-new valid records (i.e., matched records)
		$total_valid_records_processed 	= 0; // counts all valid records (matched and unmatched, possible multiple records for each new unmatched)

		// get array of array of columns of table mapped to entity fields 
		$column_map = WIC_Entity_Upload_Map::get_column_map ( $upload_id ) ['output'];

		// inject controls into data_object_arrays for each mapped column; also create a separate array of mapped/used columns
		$used_columns = array();
		$all_mapped_columns = array(); // use for definition of staging table retrieval only
		$address_part_columns = array (); // use as reverse lookup for address part columns
		foreach ( $column_map as $column => $entity_field_object ) {
			if ( '' < $entity_field_object  ) { // unmapped columns have an empty entity_field_object, also bypass address_parts for purposes of actual saving
				if ( substr ( $entity_field_object->field, 0, 17 ) != 'address_line_part' ) {
					$used_columns[] = $column;
					$field_rule = get_field_rules (  $entity_field_object->entity, $entity_field_object->field );					
					$data_object_array_array[ $entity_field_object->entity ][$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
					$data_object_array_array[ $entity_field_object->entity ][$field_rule->field_slug]->initialize_default_values(  $entity_field_object->entity, $field_rule->field_slug, '' );				
				} else {
					$address_part_columns[$entity_field_object->field] = $column;
				}
				$all_mapped_columns[] = $column; 
			} 	
		}	
		
		// if not express, get the serialized default decisions
		if ( ! $express ) { 
			$table = 'upload';
			$sql = "SELECT serialized_default_decisions FROM $table where ID = ?";
			$result = $sqlsrv->query( $sql, array($upload_id) );
			$default_decisions = json_decode ( $result[0]->serialized_default_decisions );
		}		
		// need ID in the array if not there
		// in same loop, populate an array of data access objects
		// in same loop, add default decisions into array
		// in same loop, add constituent_id into the multivalue entities
		$wic_access_object_array = array();
		foreach ( $data_object_array_array as $entity=>&$data_object_array ) {	// looping on pointer, so altering underlying object

			$entity_class = 'WIC_Entity_' . $entity;

			// add ID to each array
			if ( ! isset ( $data_object_array['ID'] ) ) {
				$field_rule = get_field_rules ( $entity, 'ID' );					
				$data_object_array[$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
				$data_object_array[$field_rule->field_slug]->initialize_default_values( $entity, $field_rule->field_slug, '' );
			}
			$data_object_array['ID']->set_value ( 0 );
			
			// set up corresponding access object
			$wic_access_object_array[$entity] = WIC_DB_Access_Factory::make_a_db_access_object( $entity );
			
			// if not express, supplement array with default fields set 
			if ( !$express ) {
				// (will not be overlayed by values from staging record in loop below since by def are not mapped )
				// for each entity, loop through default fields and if set, add them into array with value set
				// upload default field groups are named to match entities 
				// this function eliminated

				$group_fields = WIC_Form_Upload::list_field_slugs( $entity );
				// note that $group_fields will be empty for issue and constituent,  
				// but the return is an array, so foreach does not generate error but does nothing 
				foreach ( $group_fields as $field_order => $field_slug ) {
					if ( $default_decisions->$field_slug > '' ) {
						$field_rule = get_field_rules (  $entity, $field_slug );
						$data_object_array[$field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
						$data_object_array[$field_slug]->initialize_default_values(  $entity, $field_slug, '' );
						$data_object_array[$field_slug]->set_value( $default_decisions->$field_slug );
					}			
				}
			}
			// add constituent id control
			if ( $entity != 'constituent' && $entity != 'issue' ) {
				$field_rule = get_field_rules ( $entity, 'constituent_id' );					
				$data_object_array[$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
				$data_object_array[$field_rule->field_slug]->initialize_default_values( $entity, $field_rule->field_slug, '' );
			}
			// add upload_id control with value set
			if ( 'activity' == $entity) {
				$field_rule = get_field_rules ( 'activity', 'upload_id' );					
				$data_object_array[$field_rule->field_slug] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
				$data_object_array[$field_rule->field_slug]->initialize_default_values( $entity, $field_rule->field_slug, '' );
				$data_object_array[$field_rule->field_slug]->set_value ( $upload_id );
			} 
		}
		// necessary to unset when using foreach with pointer -- http://php.net/manual/en/control-structures.foreach.php
		unset ( $entity );
		unset ( $data_object_array );		

		// get staging records -- processing all, without validity checking, 
		// but invalid do not have matched_constitutent_id, so will be skipped
		$used_columns_string = "[" . implode ( '],[', $all_mapped_columns ) . ']';
		$sql = "SELECT $used_columns_string, MATCHED_CONSTITUENT_ID, INSERTED_NEW
				  FROM $staging_table ORDER BY STAGING_TABLE_ID OFFSET ? ROWS FETCH NEXT ? ROWS ONLY 
				  ";
		$staging_records = $sqlsrv->query( $sql, array( $offset, $chunk_size ) );

		// set up search parms for use within loop
		$search_parameters = array();
		
		// determine whether to use issue titles in the loop; add the issue control to activity if using titles and not already mapped
		if ( 	isset ($data_object_array_array['issue']['post_title'] ) && //  have title and 
				!isset( $data_object_array_array['activity']['issue'] ) ) {			//  need title (issue was not mapped or defaulted)
			$use_title = true;
			$field_rule = get_field_rules ( 'activity', 'issue' );					
			$data_object_array_array['activity']['issue'] = WIC_Control_Factory::make_a_control( $field_rule->field_type );	
			$data_object_array_array['activity']['issue']->initialize_default_values( 'activity', 'issue', '' );
		} else {
			$use_title = false;		
		}

		foreach ( $staging_records as $staging_record ) {
			
			// populate the data object arrays with values from mapped columns (omitting the control columns on the staging record)
			// note that controls for set defaults are already populated 
			foreach ( $used_columns as $column ) { 
				if ( $express && 'CONSTRUCTED_STREET_ADDRESS' == $column ) { // if express, didn't populate this column yet
					$data_object_array_array[$column_map->$column->entity][$column_map->$column->field]->set_value( WIC_Entity_Upload_Validate::construct_street_address( $staging_record, $address_part_columns ) );
				} else {
					$data_object_array_array[$column_map->$column->entity][$column_map->$column->field]->set_value( $staging_record->$column );
				}
				// have to run sanitization here if running express because it is normally done in validation, which is bypassed on express
				if ( $express ) {
						$data_object_array_array[$column_map->$column->entity][$column_map->$column->field]->sanitize();
				}
			}
			
			// if not express apply switches to determine whether to skip updates
			if ( ! $express ) {
				if ( 0 == $staging_record->MATCHED_CONSTITUENT_ID || // never matched -- invalid OR matched to dups on db OR unmatched, but unmatched save off   
					  ( ! $default_decisions->update_matched && '' ==  $staging_record->INSERTED_NEW ) )// update_matched is false && this record not new
					  { 
					  continue; // go to next staging file record without doing an update ( and without incrementing valid record counter at bottom of loop )
				}
			}
			// now go through array of arrays, and do updates
			foreach ( $data_object_array_array as $entity => $data_object_array ) {
				if ( 'constituent' == $entity ) { 
					if ( ! $express ) { // if express, then already updated constituent fully and no default decisions
						// don't touch the basic constituent record if protecting identity data (setting supports soft identity matching) 
						if ( $default_decisions->protect_identity ) {
							continue;					
						}
						// if constituent and just added it, don't reupdate the top entity record
						// also, if only have ID control, nothing to update on the top entity record
						// assuming not either of those go ahead and update (even without, will count as valid record processed) 
						if ( 'y' != $staging_record->INSERTED_NEW &&  1 < count ( $data_object_array ) ) { 
							$data_object_array['ID']->set_value ( $staging_record->MATCHED_CONSTITUENT_ID );
							// do the update, passing the blank_overwrite choice
							$wic_access_object_array[$entity]->upload_save_update( $data_object_array, $default_decisions->protect_blank_overwrite );
						}
					} 
				} elseif ( 'issue' == $entity ) {
					// if have post_title through mapping or default and have assured that all post_titles exist on database				
					if ( ! $use_title ) {
						continue; // continue to next entity					
					} else {
						$fast_id_lookup_by_title = WIC_DB_Access_Issue::fast_id_lookup_by_title ( $data_object_array['post_title']->get_value() );
						if ( false !== $fast_id_lookup_by_title ) {
							$data_object_array_array['activity']['issue']->set_value( $fast_id_lookup_by_title ) ;					 	
						} else {
							// this shouldn't be possible
							Throw new Exception(
								sprintf ( 'Issue missing with post_title: %1$s in update constituent phase.' , $data_object_array['post_title']->get_value() )
							); 
						} 					
					}
				} else { // so not constituent and not issue, in other words is any of the multivalue entities
					if 	( 
							3 > count ( $data_object_array )  // multivals always have ID and constituent ID, if that's all, nothing to update
							|| ( 4 > count ( $data_object_array ) && 'activity' == $entity ) // activity also has the upload ID control in every case 
						) { 
						continue; // continue to next entity				
					} 	
					// set current constituent id for the entity
					$data_object_array['constituent_id']->set_value ( $staging_record->MATCHED_CONSTITUENT_ID );
					// if an activity, will not do dup checking ( note that record will also be non-blank, since issue is required )
					if ( 'activity' == $entity ) {
						$id_to_update = 0;
					// if not an activity, do dup checking (if already exists, update rather than saving new); if missing all data, don't update or add
					} else {
						//setup a query array for those fields used in upload match/dedup checking for multi-value fields
						$query_array = array();
						// while preparing query array, set up test for missing email_address or phone_number 
						// will not store or update if missing these fields (added to allow these to be blank in validation stage) 
						$all_data_missing_for_entity = true;
						foreach ( $data_object_array as $field_slug => $control ) { 
							/*
							* for phone, email, address, upload dedup fields are type and constituent id;
							*	 have relaxed type requirement, so could be blank or absent, in which case, just checking if constituent has any
							* 	 don't want to add search clause if blank value, because not an array, just empty
							* if no type, will find record of any type as dup and will overwrite unless address with protect_identity set
							*/
							///if ( $control->is_upload_dedup() ) { // discarded dedup flag
							if ( in_array( $field_slug, array('constituent_id', 'address_type', 'email_type','phone_type') ) ) { // use dedup fields for searching (ONLY Type and ID )

								if ( $control->get_value() > '' ) { // this is correct in 8.0 -- 0 could be valid type value and constituent_id ***should*** never be zero at this point
									$query_array = array_merge ( $query_array, $control->create_search_clause () );
								}
							} elseif ( $control->get_value() > 0 ) { // don't consider 0 to be valid value -- need to assert this, not just assert non-empty, since php 8.0
								$all_data_missing_for_entity = false;
							}
						} 
						// by pass this entity if missing phone or email number or all address data -- this emulates the "group" required test for these fields online
						//   -- don't want to save blank phone, email or address rows
						if ( $all_data_missing_for_entity ) {
							continue; // go to the next entity						
						}
						// execute a search for the multivalue entity -- treating it as a top level entity, but query object is OK with that
						$wic_access_object_array[$entity]->search ( $query_array, $search_parameters );
						// if matches found, take the first for update purposes 
						if ( $wic_access_object_array[$entity]->found_count > 0 ) {
							$id_to_update = $wic_access_object_array[$entity]->result[0]->ID; // left to right construction only one possible, 'result' is not a variable; no 7.1 ambiguity
							// don't touch the found address record if protecting identity data (setting supports soft identity matching)
							if ( ! $express && $default_decisions->protect_identity && 'address' == $entity ) {
								continue;					
							}
						// but if don't have the address for this type, proceed regardless of protect_identity setting 					
						} else {
							$id_to_update = 0;
						} 					
					}
					// prepare call to save update -- id 0 will be a save
					$data_object_array['ID']->set_value ( $id_to_update );
					// now, either update found record ( email,phone, address or activity ) or save new one
					// pass user decision as to whether blanks should be overwritten (only matters on update, so $express is irrelevant)
					$protect_blank_overwrite = $express ? false : $default_decisions->protect_blank_overwrite; 
					$result = $wic_access_object_array[$entity]->upload_save_update ( $data_object_array, $protect_blank_overwrite ) ;
				} 
			} // close loop for entities

			// increment counters -- note that, at top of loop, continuing past invalid records ( by testing for match field )		
			if ( 'y' != $staging_record->INSERTED_NEW ) { // non-new updates 
				$constituent_updates_applied++;
			}
			$total_valid_records_processed++;
		} // close for loop for staging table

		// save tallies		
		$final_results->constituent_updates_applied += $constituent_updates_applied;		
		$final_results->total_valid_records_processed += $total_valid_records_processed;
		self::update_final_results( $upload_id, json_encode ( $final_results ) );
		return ( $final_results ) ;		

	} // close  update constituents	

	public static function purge_activities_for_upload_id( $upload_id ) {
		global $sqlsrv;
		$table = 'activity';
		$sql = "DELETE FROM $table WHERE upload_id = ?";
		$result = $sqlsrv->query ( $sql, array( $upload_id ) );
		return ( $result );
	} 
}
