<?php
/*
*
* class-wic-db-access-wic.php
*		main db access for constituents, etc.
*
*/


class WIC_DB_Access_WIC Extends WIC_DB_Access {


	/*
	*
	* save an individual database record for the object entity based on the save_update_array containing fields and values
	*
	*/
	protected function db_save ( &$save_update_array ) { 
		global $sqlsrv;
		$table  = $this->entity;  
		
		$set = $this->parse_save_update_array( $save_update_array, true ); // adds time stamp variables while formatting set clause

		$sql = "
				INSERT INTO [$table] 
				( {$set['set_clause_with_placeholders']} ) VALUES
				( {$set['insert_string']} )
				";
		$sqlsrv->query( $sql, $set['set_value_array']  );
		if ( $sqlsrv->success ) {
			$this->outcome = true;		
			$this->insert_id = $sqlsrv->insert_id;
		} else {		
			$this->outcome = false;
			$this->explanation = 'Unknown database error. Save may not have been successful';
		}
		$this->sql = $sql;
		return;
	}
	
	/*
	*
	* update an individual database record for the object entity based on the save_update_array containing fields and values
	*
	*/
	protected function db_update ( &$save_update_array ) {

		global $sqlsrv;
		$table  = $this->entity;

		// parse the array to set up clause and value array for $wpdp->prepare
		$set = $this->parse_save_update_array( $save_update_array, false ); // adds time stamp variables while formatting set clause
		$set_clause_with_placeholders = $set['set_clause_with_placeholders'];

		// set up the sql and do the update
		$sql = "
				UPDATE [$table]
				SET $set_clause_with_placeholders
				WHERE ID = ? 
				";
		$sqlsrv->query( $sql, $set['set_value_array']  );

		$this->outcome =  $sqlsrv->success;
		$this->explanation = ( $this->outcome ) ? '' : 'Possible database error. Update may not have been successful. Reload record to confirm.';
		$this->sql = $sql;

		return;
	}

	/*
	*
	* BASIC SEARCH: ONLY 'AND' WORKING FROM TOP ENTITY
	*
	* retrieve database records joined across parent and child tables based on array of search parameters 
	*
	*/
	public function search( $meta_query_array, $search_parameters ) {

		
		global $sqlsrv;
		global $current_user;

		// default search parameters
		$select_mode 		= 'id';
		/*
		* valid modes are -- 
		*	|download| which will get temp file
		*   |id| which will get id list as results and do totals
		*   |*| (or anything else) which will result in all entity fields being returned as result
		*		not good for constituents where want to have subsidiary entities or activities where need issue/constituent
		*	In all three cases, $retrieve_limit is aapplied
		*/
		$sort_order 		= false;
		$compute_total 		= false;
		$retrieve_limit 	= '100';

		extract ( $search_parameters, EXTR_OVERWRITE );

		// implement search parameters
		$top_entity = $this->entity;

		$sort_clause = $sort_order ? $this->get_sort_order_for_entity( $this->entity ) : '';
		$order_clause = ( '' == $sort_clause ) ? '' : " ORDER BY $sort_clause "; 

		if ( 'id' == $select_mode || 'download' == $select_mode ) {
			$select_list = "[$top_entity]" . '.' . 'ID,' . preg_replace ( '#(ASC|DESC)#i', '', $sort_clause); // need for two layer search to order by	
			$select_comma = $sort_clause > '' ? ', ' : ' '; // sort clause includes no leading comma or final trailing comma
		} else { // for example, 'list' or '*' --  pulls all fields
			$select_list = '*'; 
			$select_comma = ',';
		}
		// ADD ROW NUMBER -- IN SQL SERVE, CANNOT RANDOMLY GROUP BY
		$select_list .= "$select_comma ROW_NUMBER() OVER ( PARTITION BY [$top_entity].id ORDER BY [$top_entity].id ) as rnk";
		$retrieve_limit = is_int( $retrieve_limit) ? $retrieve_limit: 100;
		 
		// prepare $where and join clauses
		if ( $this->entity_is_office_specific ) {  
			$table_array = array( $this->entity );
			$where = "AND [{$this->entity}].OFFICE = ?";
			$values = array( get_office() );
		} else {
			$table_array = array();
			$where = '';
			$values = array();
		}
		$join = '';

		// explode the meta_query_array into where string and array ready for prepare
		foreach ( $meta_query_array as $where_item ) { 

			$field_name		= $where_item['key'];
			$table 			= $where_item['table'];
			$compare 		= $where_item['compare'];

			// hard validate table and generate fatal error if not allowed value
			validate_table_name( $table );
			
			// accept tables for join clause		
			if( ! in_array( $table, $table_array ) ) {
				$table_array[] = $table;			
			}

			// top level entities 'office' field set above and 1 office field is enough to limit the join b/c ID's are unique on top level table
			if ( 'OFFICE' == strtoupper($field_name) ) {
				continue;
			}
			
			// set up $where clause with placeholders and array to fill them
			if ( '=' == $compare || '!=' == $compare || '>' == $compare || '<' == $compare || '>=' == $compare || '<=' == $compare ) {  // straight strict match			
				$where .= " AND [$table] .$field_name $compare ? ";
				$values[] = $where_item['value'];
			} elseif ( 'like' == $compare ) { // right wild card like match
				$where .= " AND [$table].$field_name like ? ";
				$values[] = $where_item['value'] . '%' ;	
			} elseif( 'scan' == $compare ) { // right and left wild card match
				$where .= " AND [$table].$field_name like ? ";
				$values[] = '%' . $where_item['value'] . '%';
			} elseif ( 'BETWEEN' == $compare ) { // date range
				$where .= " AND [$table].$field_name >= ? AND [$table].$field_name <= ?" ;  			
				$values[] = $where_item['value'][0];
				$values[] = $where_item['value'][1];
			} else {
				Throw new Exception(sprintf( "Incorrect compare settings for field %s.",  $where_item['key']  ) );
			}	 
		}
		// prepare a join clause		
		$array_counter = 0;
		foreach ( $table_array as $table ) {
			$child_table_link = $top_entity . '_id';
			$join .= ( 0 < $array_counter ) ? " INNER JOIN [$table] on [$table].$child_table_link = [$top_entity].ID " : " [$table] " ;
			$array_counter++; 		
		}
		$join = ( '' == $join ) ? "[{$this->entity}]" : $join; 

		// Assemble main phrase with placeholders for user input which is in $values array
		$sql_rollup =
			"
			WITH ID_ROLLUP AS (
				SELECT $select_list 
					FROM 	$join
					WHERE 1=1 $where 
				)
			";

		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();			
		$temp_table_phrase =  ( 'download' == $select_mode ) ? " INTO $temp_table " : '';
		$sql_list =  $sql_rollup . " SELECT TOP $retrieve_limit * $temp_table_phrase FROM ID_ROLLUP WHERE ID_ROLLUP.rnk = 1 $order_clause ";	
		$sql_count = $sql_rollup . " SELECT count(*) as all_count FROM ID_ROLLUP";
		$this->sql = $sql_list; 
		$sqlsrv->query( $sql_list, $values, true ); // true -- we need to go down the results branch and return empty array even if no rows

		if ( 'download' == $select_mode ) {

			if ( !$sqlsrv->success ) {
				Throw new Exception( 'Error in download, likely permission error.' );
			}			
		
		} else {
			$this->result = $sqlsrv->last_result;
			$this->showing_count = $sqlsrv->num_rows;
			$first_query_success = $sqlsrv->success; 
			// get total count if requested
			if ( $compute_total ) {
				$sqlsrv->query( $sql_count, $values, true );
				$this->found_count = $sqlsrv->last_result[0]->all_count;
				$second_query_success = $sqlsrv->success;
			} else {
				$this->found_count = $this->showing_count;
				$second_query_success = true;
			}
			$this->found_count_real = $compute_total;
			// finish reporting
			$this->retrieve_limit = $retrieve_limit;
			$this->outcome = $first_query_success && $second_query_success; 
			$this->explanation = $this->outcome ? '' : 'Database error in processing search request.  Logged for review.'; 
		}

	}	
	
	/*
	*
	* parse a save update array into clauses and value array for parametrized save or update 
	*  -- with just an ID clause in the array, just sets up to do a time stamp 
	*
	*/
	protected function parse_save_update_array( $save_update_array, $is_insert ) {
		global $current_user;
		$is_office_included = false;
		$update_phrase = $is_insert ? '' : ' = ?';
		// start strings
		$set_clause_with_placeholders = 'last_updated_time' . $update_phrase  ;
		$insert_string = "?";

		// timestamp fields in sqlserver have type datetime2(0); sqlsrv accepts php DateTime object
		$set_value_array = array (  new DateTime( 'now' ) );
		$entity_id = '';
		
		foreach ( $save_update_array as $save_update_clause ) {
			if ( $save_update_clause['key'] != 'ID' ) {
				$set_clause_with_placeholders .= ', ' . $save_update_clause['key'] . $update_phrase; 
				$insert_string .= ",?";
				// force authenticated office value in all forms that it is used in; 
				if ( 'OFFICE' == $save_update_clause['key'] ) {	
					$is_office_included = true;	
					$set_value_array[] = get_office();
				} else {
					$set_value_array[] = $save_update_clause['value']; 
				}
			} else { 
				$entity_id = $save_update_clause['value'];
			}
		}

		$set_clause_with_placeholders .= ', last_updated_by' . $update_phrase;
		$set_value_array[]  = get_current_user_id();		
		$insert_string .= ",?";

		if ( !$is_office_included && $this->entity_is_office_specific ) {
			$set_clause_with_placeholders .= ', OFFICE ' . $update_phrase ;
			$insert_string .= ",?";
			$set_value_array[] = get_office();
		}

		if ( $entity_id > 0 ) { 
			/*
			* non-zero in update case; can be empty string or string zero in save case; in php 8, 0 != '' but 0 == '0';
			* https://www.php.net/manual/en/migration80.incompatible.php
			* see setup in WIC_CONTROL_Parent::create_update_clause -- does issue an update clause even though ID is readonly
			*/ 
			$set_value_array[] = $entity_id; // tag entity ID on to end of array -- non-zero and used in update case
		}	
		return  array (
			'set_clause_with_placeholders' => $set_clause_with_placeholders,
			'insert_string' => $insert_string,
			'set_value_array'	=> $set_value_array,
		);
	}

	/* 
	* hard delete a record
	*
	* this is passed through from parent delete_by_id which is used in WIC_Control_Multivalue->set_value to clean out deleted rows
	* before populating the data_object_array (in other words before other updates coming from a form are applied) 
	*/
	protected function db_delete_by_id ( $id ) {
		global $sqlsrv;		
		$table  = $this->entity;
		$outcome = $sqlsrv->query( "DELETE from [$table] where ID = ?", array( $id ) );
		if ( false === $outcome ) {
			Throw new Exception( sprintf( 'Database error on execution of requested delete of %s.', $this->entity  ));
		} 
	}


	/* time stamp -- get time stamp from current table (which is verified off list in access object factory) for requested id */ 
	public function db_get_time_stamp ( $id ) { 
		global $sqlsrv;
		$table  = $this->entity;
		$sqlsrv->query( " SELECT last_updated_by, last_updated_time FROM [$table] WHERE ID = ? ", array( $id)  );
		return $sqlsrv->last_result[0];  		 
	}

	/* 
	*
	* 	retrieve list of results from found list of ids -- supports list classes 
	*   no user supplied fields
	*
	*/
	protected function db_list () { 

		

		// bad or empty prior outcome (shouldn't be here)
		if ( !$this->result ) {
			$this->list_result = array();
			$this->showing_count = 0;
			$this->found_count = 0;	 
			$this->outcome = false;
			$this->explanation = 'Bad call for a list when nothing to list.';
			return;
		}

		// proceed working directly from result set of prior query 
		$top_entity = $this->entity;
  		$id_list = '(';
		foreach ( $this->result as $result ) {
			$id_list .= $result->ID . ',';		
		} 	
		$id_list = trim($id_list, ',') . ')';
	
		global $sqlsrv;

		$clauses = self::prepare_list_clauses ( $this->entity );
		extract ( $clauses ); // $select_list, $join (and $table_array, not used here );
		$where = "[$top_entity]" . '.ID IN ' . $id_list . ' ';
		$group_by_clause = " GROUP BY " . $this->get_group_by_string_for_entity( $top_entity );
		$sort_clause = $this->get_sort_order_for_entity( $this->entity );
		$order_clause = ( '' == $sort_clause ) ? '' : " ORDER BY $sort_clause"; 

		$sql = 	"SELECT $select_list
					FROM 	$join
					WHERE $where
					$group_by_clause
					$order_clause
					";

		$this->sql = $sql; 
		$sqlsrv->query ( $sql, array(), true ); // no prepare -- this is internal shuffling of dictionary defs (never open to user modification)
		$this->list_result = $sqlsrv->last_result;
		$this->outcome = $sqlsrv->success; 
		$this->explanation = $sqlsrv->success ? '' : 'Database error in listing phase'; 
	}

	public static function prepare_list_clauses ( $entity ) {

		$top_entity = $entity;
		$table_array = array( $entity );
		$join =  "  $entity ";
		$select_list = '';

		// assemble list query based on specification in list entity
		$fields =  static::get_list_fields_for_entity( $entity ); 
		// retrieving those with non-zero listing order -- SQL error will occur if none have non-zero listing order
		// either for main list or for fields of multivalue entities which are included in main list
		foreach ( $fields as $field ) {
			// standard single field addition to list
			
			if ( !in_array( $field->field_slug, array('address','email','phone','user' ) ) ) { // not carrying field type in list field array so naming them
				$select_list .= ( '' == $select_list ) ? ("[$top_entity]" . '.') : (', ' . "[$top_entity]" . '.' );
				$select_list .= $field->field_slug . ' AS ' . $field->field_slug ;
			// else multivalue field calls for multiple instances, compressed into single value
			} else {
				// create comma separated list of list fields for entity 
				$select_list .= '' == $select_list ? '' : ', ';
				$sub_fields = static::get_list_fields_for_entity( $field->field_slug );
				$sub_field_list = ''; 
				foreach ( $sub_fields as $sub_field ) {
					if ( 'ID' != $sub_field->field_slug ) { 
						$sub_field_list .= ( '' == $sub_field_list ) ? "[$field->field_slug]" . '.' : ',\' | \',' . "[$field->field_slug]" . '.' ;
						$sub_field_list .= $sub_field->field_slug;
					}
				}

				/* 
				* NOTE: b/c phone, email and address (and activity) are multivalue as against constituent, constituent headed joins
				*	including all of these related recodrds can yield a high multiplicative count of rows for each constituent
				* Here, take max of multi values -- choosing non-blank values, not trying to show multiple valid values
				*		
				* Attractive alt would be to use string_agg and carry multiple rows of values and clean them up on the php side, 
				*	but string_agg gets long when have high activity counts for a single constituent so, 
				*		volume of data transfer and processing on php side goes up.
				*			formatters for email, phone and address are set up to unpack and dedup multi rows, so could try string_agg again later
				*	could also consider some form of over/partition/rank, but hard to conceptualize in dynamic sql context
				* 
				* BTW, the empty string at the beginning of the concat assures that there is something to concat in sub_field_list
				*/
				$select_list .= 'MAX(CONCAT(\'\',' . $sub_field_list . ')) AS ' . "[$field->field_slug]";
				$join .= ' LEFT JOIN [' .  $field->field_slug  . '] ON [' . 
					$entity . '].ID = [' . $field->field_slug . '].' . $entity . '_id ';
				$table_array[] = $field->field_slug;
			}		
		}	
		return array ( 'select_list' => $select_list, 'join' => $join, 'table_array' => $table_array ); // return array for use in advanced search

	}



	public static function get_constituent_name ( $id, $return_array = false ) {

		global $sqlsrv;
		$sqlsrv->query(
			"
			SELECT trim( concat ( first_name, ' ', last_name ) ) as name, first_name, middle_name, last_name, salutation, gender
			FROM constituent
			WHERE ID = ?
			",
			array ( $id )
			);

		if ( !$return_array) {
			return 0 == $sqlsrv->num_rows ? '' : $sqlsrv->last_result[0]->name;
		} else {
			return 0 == $sqlsrv->num_rows ? '' : $sqlsrv->last_result[0];
		}
	}	

	// option counts for use in displaying existing option usage in options update screens
	protected function db_get_option_value_counts( $field_slug ) {
		
		// the values passed to this function from form-option-group.php are derived from the $field_slug
		// column of the dictionary, which is never updated by the app, but in case . . .
		if( !is_string( $field_slug) || ! $field_slug || preg_match( '#[^0-9a-zA-Z-_]#', $field_slug ) ) {
			return array();
		}

		global $sqlsrv;
	
		$table = '' . $this->entity;
	
		$sql = "SELECT $field_slug as field_value, count(ID) as value_count from [$table] group by $field_slug ORDER BY $field_slug";
	
		$sqlsrv->query( $sql, array() );
		
		return ( $sqlsrv->last_result );	
	
	} 

}

