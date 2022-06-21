<?php
/*
*
* class-wic-entity-upload-set-defaults.php
*
*
* 
*/

 class WIC_Entity_Upload_Set_Defaults {
 
 	public static function update_default_decisions ( $id, $data ) {
		// test for non-zero, non-empty upload ID
		if ( !$id ) {
			return array ( 'response_code' => false, 'output' => 'update_default_decisions called with falsey upload ID.' );
		}
		// set up variables
		global $sqlsrv;
		$serialized_default_decisions = json_encode ( $data );
		$table = 'upload';
		// test for not-valid upload id
		$result = $sqlsrv->query ( "SELECT serialized_default_decisions from $table where ID = ?", array( $id ) );
		if ( ! count( $result ) ) {
			return array ( 'response_code' => false, 'output' => sprintf( 'update_default_decisions called with not valid upload ID: %d.', $id )  );
		}
		// test for actual chnage
		if ( $result[0]->serialized_default_decisions == $serialized_default_decisions ) {
			return array ( 'response_code' => true, 'output' => 'Upload_default_decisions called OK, but no change in default decisions.' );
		}
		// do the update -- 0 is not an OK result, since have already tested for change: 1 row should be affected
		$result = $sqlsrv->query ( "UPDATE $table set serialized_default_decisions = ? WHERE ID = ?", array ( $serialized_default_decisions, $id ) );
		// test result and return
		if ( ! $result ) {
			return array ( 'response_code' => false, 'output' => $result === false ?
				'Database error in recording default decisions.'
				:
				sprintf( 'Unexpected result recording default decisions for valid Upload ID %s: $sqlsrv reported no changed rows.', $id )		
			); 
		} else {
			return array ( 'response_code' => true, 'output' => 'Default decisions recorded.' );
		}			 
	}


	/*
	* for issue titles in upload that don't exist on wp_post, create a table for later possible post creation
	* table will also be used to show user what will be created
	*
	* note: makes no sense to default tags or cats -- in normal usage, these should vary across records 
	* also, they are unlikely to be included in input sources -- if they are, user knows how to do
	* backend processing and can solve own problems
	*/ 
	public static function get_unmatched_issue_table ( $id, $data ) {

		global $sqlsrv;

		$staging_table 			= $data->staging_table;
		$issue_title_column		= $data->issue_title_column;
		$issue_content_column 	= $data->issue_content_column; 	
	
		// new staging table name				
		$new_issue_table = $staging_table . '_new_issues';
		// issue table
		$post_table = 'issue';
		// drop table if it already exists (may have been remapped)
		$sql = "DROP TABLE IF EXISTS $new_issue_table";
		$result = $sqlsrv->query( $sql, array() );		
			
		$sql = "CREATE TABLE $new_issue_table (
			new_issue_ID bigint IDENTITY(1,1) PRIMARY KEY,
			new_issue_title varchar(255) NOT NULL,
			new_issue_content varchar(max) NOT NULL,
			record_count bigint default 0,
			inserted_post_id bigint default 0
			)";
		$result = $sqlsrv->query( $sql, array() );
		$sqlsrv->query ( "CREATE INDEX ix_{$new_issue_table}_new_issue_title ON {$new_issue_table} (new_issue_title)", array());

		$new_issue_content_source = ( $issue_content_column > '' ) ? $issue_content_column : "''";				
		
		// no reason for this to be case sensitive -- removing collate
		$sql = 	"INSERT INTO $new_issue_table ( new_issue_title, new_issue_content, record_count )
					SELECT new_issue_title, new_issue_content, record_count 
					FROM ( 
						SELECT 
							$issue_title_column as new_issue_title,
							$new_issue_content_source as new_issue_content, 
							count(STAGING_TABLE_ID) as record_count 
						FROM $staging_table 
						WHERE VALIDATION_STATUS = 'y' 
						GROUP BY $issue_title_column 
						) as issues
					LEFT JOIN $post_table ON new_issue_title = post_title
						AND post_type = 'post' AND OFFICE = ?
					WHERE post_title is null
						";

		$result = $sqlsrv->query( $sql, array( get_office()) );	
		
		$sql = "SELECT new_issue_title, record_count FROM $new_issue_table ORDER BY new_issue_title";
	
		$results = $sqlsrv->query( $sql, array() );
		
		// send display table back to client ( may be headers only -- js gets count from num rows );
		$table = '<table class="wp-issues-crm-stats">' .
		'<tr>' .
			'<th class = "wic-statistic-text">' . 'Input file issue titles -- possible new issues' . '</th>' .
			'<th class = "wic-statistic">' . 'Records' . '</th>' .
		'</tr>';
		foreach ( $results as $result ) {
			$table .= '<tr>' .
				'<td class = "wic-statistic-text">' . $result->new_issue_title . '</td>' .
				'<td class = "wic-statistic" >' . $result->record_count  . '</td>' .
			'</tr>'; 
		}
		$table .= '</table>';	
		return  array ( 'response_code' => true, 'output' => $table );
					
	}

}