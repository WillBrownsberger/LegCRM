<?php
/*
*
* class-wic-entity-upload-regrets.php
*
*
* 
*/
class WIC_Entity_Upload_Regrets {

	// note that reversal is rerunnable/restartable

	public static function backout_new_constituents( $upload_id, $data ) {
		global $sqlsrv;

		$staging_table = $data->table;
		$entity_array = array ( 'constituent', 'activity', 'phone', 'email', 'address' );

		$return_result = true;
		foreach ( $entity_array as $entity ) {
			$id = ( 'constituent' == $entity ) ? 'ID' : 'constituent_id';	
			$table = $entity;		
			$sql = "DELETE FROM d FROM $table d INNER JOIN $staging_table s ON s.MATCHED_CONSTITUENT_ID = d.$id WHERE 'y' = s.INSERTED_NEW ";
			$result = $sqlsrv->query ( $sql, array() );
			if ( false === $result ) {
				$return_result = false;			
			}
		}

		// purge activities from upload (those for existing constituents);
		WIC_Entity_Upload_Complete::purge_activities_for_upload_id( $upload_id );

		// restore staging table flags so can rerun from beginning in express mode 
		if ( $data->express ) {
			$sql = "UPDATE $staging_table SET MATCHED_CONSTITUENT_ID = 0, INSERTED_NEW = ''";
			$result = $sqlsrv->query ( $sql,array() );
			if ( false === $result ) {
					$return_result = false;			
			}
		}

		// on successful completion, set final results for new constituents saved to zero, update upload status		
		if ( false !== $return_result ) {
			$final_results = WIC_Entity_Upload_Complete::get_final_results ( $upload_id );
			$final_results->new_constituents_saved = 0;
			$final_results = json_encode ( $final_results );	
			WIC_Entity_Upload_Complete::update_final_results( $upload_id, $final_results );					
			WIC_Entity_Upload::update_upload_status ( $upload_id, $data->express ? 'mapped' : 'reversed' ); // reversal is a final disposition for non-express uploads
		}

		return array ( 'response_code' => $return_result, 'output' => $return_result ?
			''
			:
			'Error in backout process.' 
		);	  
	}	
}