<?php
/*
*
*	wic-entity-upload-map.php
*
*	static functions supporting upload-map.js
*
*
*	NOTE ABOUT CONSTRUCTED_STREET_ADDRESS, ADDED IN VERSION 3.0 
*		+ built into column_map and staging table in initial creation in upload_upload (php)
*		+ excluded from creation of draggables for drag/drop mapping (but the parts are in the droppables);
*		+ mapping of both parts and the full street_address prevented in is_column_mapping_valid (allowed, but error and cannot advance) 
*		+ in js, when save map, test to see if parts are mapped, and then on server map or unmap the CONSTRUCTED_STREET_ADDRESS accordingly (maps to address_line)
*		+	 note that it will be properly initialized on load of map form, because form starts by doing a map update
*		+	 never placed in interface table, because update_interface_table is driven by the js update function for the column map which acts only on draggables
*		+ excluded from creation of update controls in the validate step and the complete step ( not taken in anyway in the match or default steps)
*		+	 but create reverse lookup at both stages to speed construction 
*		+ combined before sanitization in both of those procedures (note that sanitize updates the staging table while express does not ); 
*/




class WIC_Entity_Upload_Map  {
	
	// get sample data for columns
	public static function get_sample_data ( $staging_table_name ) {
		global $sqlsrv;
		$sql = "SELECT top 5 * from $staging_table_name";
		$object_array = $sqlsrv->query( $sql, array() );

		// convert to associative array
		$result = array();
		foreach ($object_array as $object ) {
			$result[] = (array) $object;
		}
		$inverted_array = array();
		foreach ( $result as $key => $row ) {
			foreach ( $row as $column_head => $value ) {
				$inverted_array[$column_head][$key] = $value;			
			}
		}
		$column_head_array = array();
		foreach ( $inverted_array as $column_head => $column ) {
			$column_head_array[$column_head] = safe_html ( WIC_Entity_Email_Process::first_n1_chars_or_first_n2_words( implode( ', ', $column ), 100, 15 ) );
		}
		return ( $column_head_array );
	}

	public static function get_column_map ( $upload_id ) {
		global $sqlsrv;
		$table = 'upload';
		$sql = "SELECT serialized_column_map FROM $table where ID = ?";
		$result = $sqlsrv->query( $sql, array( $upload_id ) );
		return array ( 'response_code' => true, 'output' => json_decode ( $result[0]->serialized_column_map ) );
	}	
	
	// general save -- not reporting back to ajax
	public static function save_column_map ( $upload_id, $serialized_map ) {
		global $sqlsrv;
		$table = 'upload';
		$sql = "UPDATE $table set serialized_column_map = ? WHERE ID = ?";
		$result = $sqlsrv->query( $sql, array( $serialized_map, $upload_id) );
		return ( $result );
	}	

	// for access from upload-map.js
	public static function update_column_map( $upload_id, $column_map  ) {

		// also update entry for CONSTRUCTED_STREET_ADDRESS
		$address_parts_mapped = false;
		foreach ( $column_map as $column=>$entity_field_object ) { 
			if ( $entity_field_object > '' && 'address_line_part' == substr ( $entity_field_object->field, 0, 17 )) {
				$address_parts_mapped = true;
			}
		}
		$column_map->CONSTRUCTED_STREET_ADDRESS = $address_parts_mapped ? 
			(object) array ( 'entity' => 'address',	'field'=> 'address_line', 'non_empty_count' => 0, 'valid_count'	=> 0 ) : 
			'';

		$outcome = WIC_Entity_Upload_Map::save_column_map( $upload_id , json_encode( $column_map ) ) ;
		// check whether any columns mapped after latest changes and whether mapping valid
		// upload status is degraded to staged if nothing mapped or invalid; set to mapped if something mapped 
		// got to this stage when changed a mapping (which may be an upgrade from staged or a downgrade from later step)
		$mapping_errors = self::is_column_mapping_valid( $column_map ); 
		$upload_status = ( '' == $mapping_errors ) ? 'mapped' : 'staged'; 
		$outcome2 = WIC_Entity_Upload::update_upload_status( $upload_id, $upload_status );		
		// if both database accesses OK, send any mapping validation errors back to upload-map.js				
		if ( false !== $outcome && false !== $outcome2['response_code'] ) {
			return array ( 'response_code' => true, 'output' => $mapping_errors );
		// otherwise flag the database error
		} else {
			
			return array ( 'response_code' => false, 'output' => 'AJAX update_column_map ERROR in update_column_map.' ) ;
		}
	}
	
	// for use with upload-map.js
	public static function update_interface_table ( $upload_field, $entity_field_object ) {

		global $sqlsrv;

		// if unmatching the field, entity_field_object will come in as empty string		
		if ( is_object ( $entity_field_object ) ) {
			$entity = $entity_field_object->entity;
			$field = $entity_field_object->field;
		} else { 
			$entity = '';
			$field  = '';		
		}	

		$table = 'interface';
		$sql = "SELECT matched_entity, matched_field from  $table WHERE  upload_field_name = ?";
		$result = $sqlsrv->query( $sql, array( $upload_field ) );
		if ( isset ( $result[0] ) ) {
			if ( 	$result[0]->matched_entity != $entity  ||
					$result[0]->matched_field != $field 
				) {
				if ( '' != $field && '' != $entity ) {	
					$sql = "UPDATE $table SET matched_entity = ?, matched_field = ? WHERE upload_field_name = ?";
					$result = $sqlsrv->query ( $sql, array( $entity, $field, $upload_field) );
				} else { // no empty entries
					$sql = "DELETE from $table WHERE upload_field_name = ?";				
					$result = $sqlsrv->query ( $sql, array ( $upload_field ) );
				}
			} 
		} else {
			$sql = "INSERT INTO $table ( upload_field_name, matched_entity, matched_field ) VALUES ( ?,?,? )";
			$result = $sqlsrv->query ( $sql, array($upload_field, $entity, $field) );
		}
		
		return array ( 'response_code' => false !== $result, 'output' => false === $result ?  'AJAX update_interface_table ERROR on server side.' : '');

	}



	
	/*
	* is_column_mapping_valid enforces some column required logic for non-defaultable columns, but these fields are not necessarily required individually at the row level
	* does it in hard_coded way -- complements wic-set-defaults.js which offers for defaultable columns
	*
	* note address and activity fields are defaultable, so no required rules here -- see js
	* group required for fn/ln/email is enforced implicitly in  matching specification and also explicitly at upload stage b/c can match by custom ID field 
	*
	*/
	public static function is_column_mapping_valid ( $column_map ) {

		$columns_mapped = false;
		$address_line_mapped = false;
		$address_line_part_mapped = false;
		$address_data_mapped = false;
		$address_type_mapped = false;
		$phone_mapped = false;
		$phone_number_mapped = false;
		$email_mapped = false;
		$email_address_mapped = false;						
		$issue_mapped = false;
		$issue_title_mapped = false;
		$express_ineligible = false;								

		$mapping_errors = '';
		
		foreach ( $column_map as $column => $entity_field_array ) {
			if ( $entity_field_array  > '' ) { 
				if ( $entity_field_array->entity > '' && $entity_field_array->field > '' ) {
					$columns_mapped = true;
				}
				if ( 'address' == $entity_field_array->entity ) {
					if ( 'address_line' == $entity_field_array->field && 'CONSTRUCTED_STREET_ADDRESS' != $column  ) {
						$address_line_mapped = true;
					}
					if ( 'address_line_part' == substr( $entity_field_array->field, 0, 17 ) ) {
						$address_line_part_mapped = true;
					}				
					if ( in_array( $entity_field_array->field, array ( 'city', 'state', 'zip','address_line' ) ) || $address_line_part_mapped ) {
							$address_data_mapped = true;					
					}
					if ( 'address_type' == $entity_field_array->field ) {
							$address_type_mapped = true;					
					}
				}				
				if ( 'phone' == $entity_field_array->entity ) {
					$phone_mapped = true;
					if ( 'phone_number' == $entity_field_array->field || 'extension' == $entity_field_array->field ) {
						$phone_number_mapped = true;					
					}				
				}
				if ( 'email' == $entity_field_array->entity ) {
					$email_mapped = true;
					if ( 'email_address' == $entity_field_array->field ) {
						$email_address_mapped = true;					
					}				
				}												
				if ( 'issue' == $entity_field_array->entity ) {
					$issue_mapped = true;
					if ( 'post_title' == $entity_field_array->field ) {
						$issue_title_mapped = true;					
					}				
				}
				if ( 'issue' == $entity_field_array->entity || 'activity' == $entity_field_array->entity || 'ID' == $entity_field_array->field ) {
					$express_ineligible = true;
				}
			}
		}
		
		if ( false == $columns_mapped ) {
			$mapping_errors = 'No columns mapped.';
		} else {
			if ( true == $phone_mapped && false == $phone_number_mapped )	{
				$mapping_errors .= 'Cannot map phone type or extension without mapping phone number.';			
			}
			if ( true == $email_mapped && false == $email_address_mapped ) {
				$mapping_errors .= 'Cannot map email type without email address.';							
			}	
			if ( true == $issue_mapped && false == $issue_title_mapped ) {
				$mapping_errors .= 'Cannot map issue content without mapping issue title.';							
			}
			if ( true == $address_line_mapped && true == $address_line_part_mapped ) {
				$mapping_errors .= 'Cannot map both Street Address and a part of Street Address.';
			}
			if ( false == $address_data_mapped && true == $address_type_mapped ) {
				$mapping_errors .= 'Cannot map address type without mapping some address data.';
			}			
			// this error must be last	
			if ( true == $express_ineligible && ''== $mapping_errors ) {
				$mapping_errors = 'EXPRESS_INELIGIBLE';
			}
		}
		return ( $mapping_errors );
	} 

}
