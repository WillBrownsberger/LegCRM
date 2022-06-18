<?php
/*
*	wic-entity-search_box
*	psuedo entity for fast (but complex) lookups for search box
*   note that autocomplete_object sanitizes_text_field for label output
*/

class WIC_Entity_Search_Box  {

	public static function search ( $look_up_mode, $term, $generic_autocomplete = false ) {

		global $sqlsrv;

		// note that the db proc will discard the whole string after a tag open, so not doing strip tags
		$sanitized_term = substitute_non_utf8($term);

		if ( ! WIC_Admin_Setup::wic_check_nonce() ) {
			return json_encode(
				(object) array ( 
					'response_code' => false, 
					'output' => "Unauthorized search/autocomplete attempt."
				)
			);

			error_log ( "Unauthorized search/autocomplete attempt. Term was: |$sanitized_term|. User was: " .  get_azure_user_direct() );
		}

		if ( strlen ( $sanitized_term ) < 1 ) {
			return json_encode ( 
				(object) array ( 
					'response_code' => true, 
					'output' => array() 
				) 
			);
		}
		
		$proc_to_call = $generic_autocomplete ? 'autocomplete' : 'searchBox';

		$sqlsrv->query( 'execute [dbo].[' . $proc_to_call . '] ?,?,?', 
			array( 
				$look_up_mode, 
				$sanitized_term, 
				get_azure_user_direct()
			)
		);

		if ( $sqlsrv->success && $sqlsrv->num_rows ) {
			$first_key = array_key_first( (array) $sqlsrv->last_result[0] );
			if ( 'JSON' == substr( $first_key, 0, 4) ) {
				// have JSON object, but may be spread across several return elements
				$return_string = '{"response_code":true,"output":';
				foreach ( $sqlsrv->last_result as $result_row ) {
					foreach ( $result_row as $key => $json_part ) { // normally only one part per row
						$return_string .= $json_part;
					}
				}
				$return_string .= '}';
				return $return_string;
			}
		}
		// if don't have success or it wasn't json, drop through to here
		error_log ( 
			"Unsuccessful call in search box or autocomplete.  Term was |$sanitized_term| and result was:" 
			. '>'. print_r($sqlsrv->last_result,true) . '<' 
			. (false === $sqlsrv->last_result ? '-- Boolean False' : '' )
		);
		return json_encode(
			(object) array ( 
				'response_code' => false, 
				'output' => "Search/autocomplete data error. Logged for review."
			)
		);
	}	
} 
