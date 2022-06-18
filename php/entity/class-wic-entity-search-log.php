<?php
/*
*
*	wic-entity-search-log.php
*
*   Note: In versions 2.7 and up, only advanced searches are logged.
*
*	Class logs searchs and supports retrieval of searches
*
*/

class WIC_Entity_Search_Log extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'search_log';
	} 

	/**************************************************************************************************************************************
	*
	*	Search Log Request Handlers
	*
	*
	*	Search log retrieval can take two approaches -- either to the search form or to the found list
	*	Need to carry old search ID back if showing the found list, so that export and redo search buttons can work, so
	*	   maintain old search ID in array returned from ID retrieval 
	*
	***************************************************************************************************************************************/

	// request handler for search log list -- re-executes query 
	public function id_search( $args ) {
		
		$search = false;
		if ( $args['id_requested'] && is_numeric ( $args['id_requested'] ) ) {
			$search = $this->get_search_from_search_log(  $args );
		}

		if ( $search ) {
			$class_name = 'WIC_Entity_'. $search['entity']; 
			// returning from search log, go to found item(s) if any, or redisplay search form
			if ( $search['result_count'] > 0) {
				${ $class_name } = new $class_name ( 'redo_search_from_meta_query', $search  ) ;
			} else {
				${ $class_name } = new $class_name ( 'search_form_from_search_array', $search ) ;		
			}
		} else {
			printf ( '<h3>Bad search log request -- no search in search log with with ID "%1$s".</h3>' , $args['id_requested'] );	
		}		
	}	
	
	// request handler for back to search button -- brings back to filled search form, but does not reexecute the query
	public function id_search_to_form( $args ) {
		$search = $this->get_search_from_search_log(  $args );
		$class_name = 'WIC_Entity_'. $search['entity'];
		${ $class_name } = new $class_name ( 'search_form_from_search_array', $search ) ;		
	}

	/**************************************************************************************************************************************
	*
	*	Formatters for search log list
	*
	***************************************************************************************************************************************/
	public static function favorite_formatter ( $favorite ) {
		$dashicon = $favorite ? '<span class="dashicons dashicons-star-filled"></span>' : '<span class="dashicons dashicons-star-empty"></span>';
		return ( $dashicon );	
	}	
	
	// update the favorite setting in AJAX call
	public static function set_favorite ( $id, $data ) { 
		global $sqlsrv;
		$favorite = $data->favorite ? 1 : 0;		
		$search_log_table = 'search_log';
		$is_named_phrase = ( 1 == $favorite ) ? '' : ' and is_named = 0 ';
		// don't unfavorite a named search -- named searches are always favorited
		$sql = "UPDATE $search_log_table SET favorite = $favorite WHERE ID = ? AND OFFICE = ? $is_named_phrase ";
		$sqlsrv->query( $sql, array( $id, get_office()) ); 
		$result = $sqlsrv->success;
		return array ( 'response_code' => $result, 'output' => $result ? 'Favorite set OK.' : 'Favorite not set -- DB error.' ); 
	}
	
	public static function share_name_formatter ( $name ) {
		return ( $name > '' ? $name : 'private' );
	}
	
	public static function update_name ( $id, $name ) {
		global $current_user;
		global $sqlsrv;
		$search_log_table = 'search_log';
		$user_id = get_current_user_id();
		$user_id_phrase = $current_user->current_user_authorized_to ( 'edit_theme_options' ) ? '' : " and user_id = $user_id";
		$sanitized_name = utf8_string_no_tags ( $name );
		// favorite named searches, but don't unfavorite unnamed searches
		if ( $sanitized_name  > '' ) {
			$is_named = 1;			
			$favorite_phrase = ", favorite = 1 ";
		} else {
			$is_named = 0;
			$favorite_phrase = '';
		}

		$sqlsrv->query( 
			"UPDATE $search_log_table SET share_name = ?, is_named = $is_named $favorite_phrase where ID = ? AND OFFICE = ? $user_id_phrase ",
			array ( $sanitized_name, $id, get_office() ) 
		);

		if ( false !== $sqlsrv->success ) {
			$share_phrase = $name > '' ? 'Search will be shared' : 'Search will be visible only to you.';
			return array ( 'response_code' => true, 'output' =>  'Name update successful. ' . $share_phrase );  
		} else {
			return array ( 'response_code' => false, 'output' => 'Name update not successful.  Probable security error -- if you are not an administrator, you can only name your own searches.' ); 
		}
	}
	
	public static function serialized_search_array_formatter ( $serialized ) {
		
		
		$search_array = unserialize ( $serialized );
		$search_phrase = '';
		
		// first repack search array, exploding any items that are row arrays
		// two components are labeled as in advanced search array 
		$unpacked_search_array_definitions = array();
		$unpacked_search_array_terms = array();
		foreach ( $search_array as $search_clause ) { 
			if ( isset ( $search_clause[0] ) ) { 
				$new_clause = array();
				$row_type = substr( $search_clause[1][0]['table'], 16 );
				foreach ( $search_clause[1] as $clause_component ) {
					if ( $row_type . '_field' == $clause_component['key'] ) {
						$value_components = explode ('___', $clause_component['value'] );
						$new_clause['key']		= $value_components[1];
						$new_clause['table']	= $value_components[0];
					} elseif ( $row_type . '_comparison' == $clause_component['key']  ) {
						$new_clause['compare'] = $clause_component['value']; 				
					} elseif ( $row_type . '_value' == $clause_component['key']  ) { 
						$new_clause['value']  = $clause_component['value']; 
					} elseif ( $row_type . '_type' == $clause_component['key']  ) { 
						$new_clause['type'] =  $clause_component['value']; 
					} elseif ( $row_type . '_aggregator' == $clause_component['key']  ) { 
						$new_clause['aggregator'] =  $clause_component['value'];
					} elseif ( $row_type . '_issue_cat' == $clause_component['key']  ) { 
						$new_clause['issue_cat'] =  $clause_component['value']; 
					}										
				}
				$unpacked_search_array_terms[] = $new_clause;			
			} else {
				// discard detailed search definition terms; show only primary search entity
				if ( 'primary_search_entity' == $search_clause['key']) {
					$unpacked_search_array_definitions[] = $search_clause;	
				}
			}		
		}
		$search_array = array_merge ( $unpacked_search_array_definitions, $unpacked_search_array_terms );

		if ( count ( $search_array ) > 0 ) { 
			foreach ( $search_array as $search_clause ) { 
					
				$value = $search_clause['value'] ?? '' ; // default
				$search_phrase .= ( 'advanced_search' == $search_clause['table'] ? '' : $search_clause['table'] . ': ' ). 
				( isset ( $search_clause['aggregator'] ) 	? ' ' . $search_clause['aggregator'] . ' of ' : '' ) .
				( isset ( $search_clause['type'] )  		? ' type ' . $search_clause['type'] . ' ' : '' ) .
				($search_clause['key'] ?? '' ) . ' ' . 
				( $search_clause['compare'] ?? '' )  . ' ' . 
				safe_html( $value ) . '<br />';

			} 
		}
		return ( $search_phrase );	
	}	

	// log a search entry
	public static function search_log ( $args ) {
		
		extract ( $args );
		// on redo_search_from_meta_query, do not log, instead retain original search ID
		if ( isset ( $search_parameters['redo_search'] ) ) {
			return ( $search_parameters['old_search_id'] );
		}

		global $sqlsrv;
		$search_log_table = 'search_log';
		$user_id = get_current_user_id();

		$search = serialize( $meta_query_array );
		$parameters = serialize ( $search_parameters );
		$save_result =$sqlsrv->query(
			"
			INSERT INTO $search_log_table
				(
				user_id,
				office, 
				search_time, 
				entity, 
				serialized_search_array,  
				serialized_search_parameters, 
				result_count,
				serialized_shape_array, favorite,share_name,is_named,download_time )
			VALUES 
				( 
				$user_id,
				?, 
				?, 
				'advanced_search', 
				?, 
				?, 
				?,
				'',0,'',0,''
				)
			", 
			array 
				( 
				get_office(),
				current_time( 'YmdHis' ), 
				$search, 
				$parameters, 
				$found_count 
				) 
		); 

		if ( $sqlsrv->success ) {
			return (  $sqlsrv->insert_id );
		} else {
			echo '<h3>Unknown database error prevented logging of search.</h3>'; // unlikely error and not warranting an error box; 		
		}
	}
	
	// find an ID search in a serialized array and return the id searched for
	private static function extract_id_search_from_array( $serialized_search_array ) {
		$latest_search_array = unserialize ( $serialized_search_array );
		$latest_searched_for = '';
		foreach ( $latest_search_array as $search_clause ) {
			if ( 'ID' == $search_clause['key']  ) {
				$latest_searched_for = $search_clause['value'];	
			}		
		} 
		return ( $latest_searched_for );
	}
	
	
	// pull the specified search off the search log by search id 
	// (for constituent export, does not pass search parameters, only search terms)
	public static function get_search_from_search_log ( $args ) { 
		
		$id = $args ['id_requested'];
		global $sqlsrv;
		$search_log_table = 'search_log';
		
		$search_object = $sqlsrv->query ( "SELECT * from $search_log_table where id = ? AND OFFICE = ?", array($id, get_office()) )[0];
		
		if ( $search_object ) {
			$unserialized_search_array = unserialize ( $search_object->serialized_search_array );
			$return = array (
				'search_id' => $id,
				'share_name' => $search_object->share_name,
				'user_id' => $search_object->user_id,
				'entity' =>  $search_object->entity, 
				'unserialized_search_array' =>  $unserialized_search_array,
				'unserialized_search_parameters' => unserialize( $search_object->serialized_search_parameters ),
				'result_count' =>$search_object->result_count
				);
		} else {
			$return = false;
		}
		return ( $return );		
	}
	
	/*
	*
	* mark a search as having been downloaded
	*
	*/
	public static function mark_search_as_downloaded ( $id ) {
		global $sqlsrv;
		$search_log_table = 'search_log';
		
		$update_result = $sqlsrv->query (
			"
			UPDATE $search_log_table
			SET download_time = ?
			WHERE id = ?
			",
			array( current_time( 'Y-m-d H:i:s' ), $id ) ); // do not specify office; if somehow downloaded, let it be recorded
		
		if ( 1 != $update_result ) {
			Throw new Exception( 'Unknown database error in posting download event to search log.' );
		}	
	}	
	
  	public static function time_formatter( $value ) {
		$date_part = substr ( $value, 0, 10 );
		$time_part = substr ( $value, 11, 8 ); 		
		return ( $date_part . ' ' . $time_part ); 
	} 

	public static function download_time_formatter ( $value ) {
		IF ( $value < '2019' ) {
			return '';
		}
		return ( self::time_formatter ( $value ) );	
	}

	public static function user_id_formatter ( $user_id ) {

		$display_name = '';		
		if ( isset ( $user_id ) ) { 
			if ( $user_id > 0 ) {
				$user =  get_users( array( 'fields' => array( 'display_name' ), 'include' => array ( $user_id ) ) );
				$display_name = safe_html ( $user[0]->display_name ); // best to generate an error here if this is not set on non-zero user_id
			}
		}
		return ( $display_name );
	}

	protected static $entity_dictionary = array(

		'download_time'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Last Download',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 20,
			'option_group' =>  '',),
		'entity'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Entity',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 30,
			'option_group' =>  '',),
		'favorite'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Favorite',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'ID'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'ID',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_named'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'result_count'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Count',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'search_time'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Search Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_search_array'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Search Details',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_search_parameters'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Serialized Search Parameters',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'share_name'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Share Name',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'user_id'=> array(
			'entity_slug' =>  'search_log',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'User ID',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
	
 	 );




}