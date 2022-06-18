<?php
/*
*
*	wic-entity-email-block.php
*
*/
Class WIC_Entity_Email_Block {
		
	public static function set_address_filter ( $current_uid, $whole_domain ) {
		// set up database calls
		global $sqlsrv;
		$inbox_table = 'inbox_image';
		// get the from email from underlying message
		$result = $sqlsrv->query( 
			"
			SELECT from_email, subject FROM $inbox_table 
			WHERE folder_uid = ? 
			",
			array ( $current_uid )
		 );
		$from_email = $result[0]->from_email;
		$subject = $result[0]->subject;
		// split email into parts
		$from_array = preg_split( '#@#', $from_email );
		$from_box = $from_array[0];
		$from_domain = $from_array[1];
		// insert the filter record
		$filter_table = 'inbox_incoming_filter';
		$whole_domain = $whole_domain ? 1 : 0; // assure a numeric value
		$sqlsrv->query ( 
			"
			INSERT INTO $filter_table 
				( 
				from_email_box,
				from_email_domain,
				subject_first_filtered,
				filtered_since,
				block_whole_domain,
				office 
				)
				VALUES ( ?,?,?,?,?,? )
			",
			array (
				$from_box,
				$from_domain,
				$subject,
				current_time( 'YmdHis' ),
				$whole_domain,
				get_office()
			)
		);

	}

	// called in backout of deletes from transaction
	public static function unset_address_filter ($current_uid, $whole_domain ) {
		// set up database calls
		global $sqlsrv;
		$inbox_table = 'inbox_image';
		// get the from email from underlying message
		$result = $sqlsrv->query( 
			"
			SELECT from_email, subject FROM $inbox_table 
			WHERE folder_uid = ? and OFFICE = ?
			",
			array ( $current_uid, get_office())
		 );
		if ( is_array ( $result ) && count( $result ) > 0 ) {
			$from_email = $result[0]->from_email;
			// split email into parts
			$from_array = preg_split( '#@#', $from_email );
			$from_box = $from_array[0];
			$from_domain = $from_array[1];	
			// set up delete from filter table
			$filter_table = 'inbox_incoming_filter';
			/*
			* delete the filter(s)
			*/
			$sqlsrv->query ( 
				"
				DELETE FROM $filter_table
				WHERE 
					from_email_box = ? AND
					from_email_domain = ?
					AND OFFICE = ?
				",
				array ( $from_box, $from_domain, get_office() )
			);
		}
	}

	public static function delete_address_filter ( $id, $dummy ) {
		global $sqlsrv;
		$filter_table = 'inbox_incoming_filter';	
		$result = $sqlsrv->query ( "DELETE from $filter_table where ID = ? AND OFFICE = ?", array($id, get_office()) );
		$response_code = ( 1 === $result );
		$output = $response_code ?  'Filter deleted OK.' : 'Database error on filter deletion or no such filter.  Refresh and retry.';
		return array ( 'response_code' => $response_code, 'output' => $output ); 
	}



	public static function load_block_list ( $dummy1, $dummy2 ) {
	
		global $sqlsrv;
		$filter_table = 'inbox_incoming_filter';
		$results = $sqlsrv->query ( "SELECT * from $filter_table WHERE office = ? ORDER BY from_email_domain, from_email_box", array( get_office()) );
		if ( $results ) {
			
			$delete_block_button_args = array(
				'button_class'				=> 'wic-form-button wic-delete-block-button',
				'button_label'				=> '<span class="dashicons dashicons-no"></span>',
				'type'						=> 'button',
				'name'						=> 'wic-email-delete-block-button',
				);	
			$output = '
				<table class="wp-issues-crm-stats" id="block-list-headers">
					<colgroup>
						<col style="width:20%">
						<col style="width:15%">
						<col style="width:15%">
						<col style="width:40%">
						<col style="width:10%">
					</colgroup>
					<tbody>
						<tr class = "wic-button-table-header" >
							<th class="wic-statistic-text">Sender Domain</th>
							<th class="wic-statistic-text">Sender Mailbox</th>
							<th class="wic-statistic-text">Blocked Since</th>
							<th class="wic-statistic-text">First Subject Blocked</th>
							<th class="wic-statistic-text">Remove Block</th>
						</tr>
					</tbody>
				</table>
				<div id="blocks-scroll-box">
					<table class="wp-issues-crm-stats">
						<colgroup>
							<col style="width:20%">
							<col style="width:15%">
							<col style="width:15%">
							<col style="width:40%">
							<col style="width:10%">
						 </colgroup>
						<tbody>
			';
			$some_blocked_whole_domain = false;
			foreach ( $results as $blocked ) {
				$blocked_class = $blocked->block_whole_domain ? ' class = "redrow" ' : '';
				$some_blocked_whole_domain = $blocked->block_whole_domain ? true : $some_blocked_whole_domain;
				$delete_block_button_args['value'] = $blocked->ID;
				$output .= 
				'<tr ' . $blocked_class . ' >' .
					'<td class="wic-statistic-text">' . $blocked->from_email_domain .'</td>' .
					'<td class="wic-statistic-text">' . $blocked->from_email_box. '</td>' .
					'<td class="wic-statistic-text">' . $blocked->filtered_since . '</td>' .
					'<td class="wic-statistic-text">' . $blocked->subject_first_filtered . '</td>' . 
					'<td>' . WIC_Form_Parent::create_wic_form_button ( $delete_block_button_args )  . '</td>' .
				'</tr>';
			}
			$output .= '</tbody></table></div>';
			if ( $some_blocked_whole_domain ) {
				$output .= '<p class="domain-blocked-legend">Red color of row indicates that whole domain blocked, not just the particular sender.</p>';
			}
		} else {
			$output = '<div id="inbox-congrats"><h1>No filters in place.</h1>' . 
				'<p><em>Set filters by choosing to block unhelpful messages as they arrive -- click the <span class="dashicons dashicons-warning"></span> button while viewing a message.</em></p></div>';
		}
		return array ( 'response_code' => true, 'output' => $output ); 

	
	}



}