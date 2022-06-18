<?php
/*
*
* class-wic-entity-email-subject.php
*		fast access for subject map interface
*
* note that column collation is default, case insensitive, for incoming_email_subject, but 
* in most instances cast as  COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 to get case sensitive results
*
*
* email_batch_time_stamp is just a time stamp; name is a vestige of an earlier design, preserved for transition 
*
*
*/

class WIC_Entity_Email_Subject {

	private static function get_forget_date() {
		// get interval or 60;
		$forget_date_interval =  intval(WIC_Admin_Settings::get_setting( 'forget_date_interval' ));
		if (  0 >= $forget_date_interval || 10000 <= $forget_date_interval ) $forget_date_interval = 60;
		
		$date = new DateTime('now');
		$date->sub(new DateInterval('P'. $forget_date_interval . 'D'));
		return $date->format('Y-m-d');
	}


	public static function get_subject_line_mapping ( $incoming_email_subject ) {
	
		global $sqlsrv;
		// search sql -- return most recent learned association if there is one	not forgotten
		// note that if multiple matches due to wildcards, most recent taken
		$results = $sqlsrv->query ( "EXECUTE [getSubjectLineMapping] ?, ?",
			array (  get_office(), $incoming_email_subject  )	
		);

		// do search sql -- get latest mapped issue
		$found_array = false; 
		if ( isset ( $results[0] ) ) { 	
			$found_array = array (
				'mapped_issue' 			=> $results[0]->mapped_issue, 
				'mapped_pro_con'		=> $results[0]->mapped_pro_con,
			);
		} else {
			$found_array = array (
				'mapped_issue' 			=> 0, 
				'mapped_pro_con'		=> ''
			);
		}

		return ( $found_array );

	}

	// on new map of subject line, just apply to all still pending messages with same subject line
	// when training from inbox, this will only be messages arrived since last inbox refresh, light
	// when adding in subject line manager this could take a few seconds
	private static function apply_new_subject_line_map ( $subject_line, $issue, $pro_con ) {
		global $sqlsrv;
		$sqlsrv->query ( 
			"
			UPDATE inbox_image
			SET mapped_issue = ?, mapped_pro_con = ?
			WHERE 
			subject LIKE ? COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0 AND
				office = ?
			",
			array (
				$issue,
				$pro_con,
				$subject_line,
				get_office()
			)
		 );
		return;
	}

	// invoked by -process.php, but takes pass through data object from client -- see email-process.js
	// always adds latest, without checking for any prior
	public static function save_new_subject_line_mapping ( &$data ) {		

		global $sqlsrv;
		$table = 'subject_issue_map';
		$result = $sqlsrv->query (
			"INSERT INTO $table ( 
				incoming_email_subject, 
				email_batch_time_stamp, 
				mapped_issue, 
				office, 
				mapped_pro_con ) VALUES
			(?,?,?,?,?)",
			array (	
				$data->subject,
				current_time( 'YmdHis' ), 
				$data->issue, 
				get_office(), 
				$data->pro_con
				)
		);

		
		if ( false !== $result ) {
			self::apply_new_subject_line_map ( $data->subject, $data->issue, $data->pro_con );
		}
		
		return array ( 'response_code' => $result, 'output' => ( false === $result ? 'Database error.  Logged for review.' : '' ) );

	}
	
	// mapped subject list
	public static function show_subject_list ( $dummy, $data_object ) {

		$forget_date = self::get_forget_date(); // never blank, coerced to 60 days ago if not supplied

		// look up subject->issue map entries where either contains the search phrase		
		global $sqlsrv;
		$values = array();

		$forget_date_phrase = " AND email_batch_time_stamp > ? ";
		$values[] = $forget_date; 
		$values[] = get_office();
		$values[] = get_office();
		$values[] = get_office();
		$search_string = utf8_string_no_tags( $data_object->search_string );
		if ( $search_string > '' ) {
			$like_term = '%'. $search_string . '%';
			$values[] = $like_term;
			$values[] = $like_term;
			$search_phrase = " AND os.incoming_email_subject LIKE ? OR post_title LIKE ? "; // for search, leave it case insensitve
		} else {
			$search_phrase = '';
		}

		$row_array = $sqlsrv->query ( 
			"
			SELECT os.email_batch_time_stamp, os.incoming_email_subject, os.mapped_issue, 
			IIF( p.ID IS NULL, 
				CONCAT('Hard deleted issue ( ID was ', os.mapped_issue,' )' ), post_title 
			) as post_title, 
			IIF( os.mapped_pro_con = '', 'Pro/Con',iif ( os.mapped_pro_con = 0, 'Pro', 'Con' )) as option_label
			FROM
				( 
					SELECT incoming_email_subject COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 as incoming_email_subject, 
						MAX(email_batch_time_stamp) as email_batch_time_stamp
					FROM subject_issue_map  
					WHERE 1=1 $forget_date_phrase AND OFFICE = ?
					GROUP BY incoming_email_subject  COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8
				) s  
			INNER JOIN subject_issue_map os on os.incoming_email_subject = s.incoming_email_subject  COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 and os.email_batch_time_stamp = s.email_batch_time_stamp
			LEFT JOIN issue p on p.ID = os.mapped_issue
			WHERE p.OFFICE = ? AND os.OFFICE = ? $search_phrase
			ORDER BY os.email_batch_time_stamp DESC	
			OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY
			",
			$values
		); 
		
		// output table of results
		if ( is_array( $row_array) && count ( $row_array ) > 0 ) {		
			$output =	'<table class="wp-issues-crm-stats">' .
						'<colgroup>
							<col style="width:35%">
							<col style="width:45%">
							<col style="width:10%">
							<col style="width:10%">
						 </colgroup>' .  
						'<tbody>' .
						'<tr class = "wic-button-table-header">' .
						'<th class = "wic-statistic-text incoming-email-subject-list-item">' . 'Incoming Email Subject' . '</th>' .
						'<th class = "wic-statistic-text">' . 'Mapped Issue' . '</th>' .
						'<th class = "wic-statistic-text">' . 'Mapped Pro/Con' . '</th>' .
						'<th class = "wic-table-buttons">' . 'Forget' . '</th>' .
						'</tr>';
	
			$forget_button_args = array(
				'button_class'				=> 'wic-form-button wic-subject-delete-button incoming-email-subject-list-item', // style like the unqueue button
				'button_label'				=> '<span class="dashicons dashicons-no"></span>',
				'type'						=> 'button',
				'name'						=> 'wic-forget-subject-button',
				);	
		

			foreach ( $row_array as $i => $row ) { 
				$forget_button_args['value'] = utf8_string_no_tags ( $row->incoming_email_subject );
				$forget_button_args['title']	= 'Forget this subject line';
				$output .= '<tr>' . 
					'<td class = "wic-statistic-text incoming-email-subject-list-item" title="Subject last trained on: ' . safe_html( $row->email_batch_time_stamp ) . '.">' . safe_html ( $row->incoming_email_subject ) . '</td>' .
					'<td class = "wic-statistic-text incoming-email-subject-list-item" >
						<a 
							target = "_blank" 
							title="Open issue in new window." 
							href="' . WIC_Admin_Setup::root_url() . '?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . safe_html( $row->mapped_issue ) . 
						'">' . 
						safe_html( $row->post_title ) . 
						'</a></td>' .
					'<td class = "wic-statistic-text" >' . safe_html( $row->option_label ) . '</td>' .
					'<td>' . WIC_Form_Parent::create_wic_form_button ( $forget_button_args )  . '</td>' .
				'</tr>';
			} 
		 
		$output .= '</tbody></table>';
		} else  {
			$output = '<p><em>No subjects found -- either all subjects were learned before your forget date, your search phrase is not found, or you have not gotten started yet!</em></p>';
		}
		return array ( 'response_code' => true, 'output' => $output );
	}
	
	public static function delete_subject_from_list ( $dummy, $subject ) {
		// deletes all instances of exact subject, including any earlier superseded
		// however, since mappings can include wildcards, some of the affected messages could have other valid mappings
		global $sqlsrv;
		$subject_table = "subject_issue_map";
		$response_code = $sqlsrv->query ( "
			DELETE FROM $subject_table 
			WHERE incoming_email_subject = ? COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 AND OFFICE = ?
			", 
			array ( $subject, get_office() ) );

		// now need to remap messages that might have other valid mappings
		if ( false !== $response_code ) {
			self::remap_after_subject_delete ( $subject );
		}
		
		return array ( 	'response_code' => false !== $response_code, 
						'output' =>  
							false !== $response_code 
							? 
							"Subject deleted OK"
							: 
							"Database error in deletion of subject.",
		);
	}

	public static function remap_after_subject_delete ( $subject ) {
		// find all (still pending) that have subject line like the deleted subject (which could include wildcards)
		global $sqlsrv;
		$inbox_table = 'inbox_image';
		$affected_messages = $sqlsrv->query( 
			"
			SELECT ID, subject FROM $inbox_table 
			WHERE 			
				subject LIKE ?  COLLATE LATIN1_GENERAL_100_CS_AS_SC_UTF8 AND
				no_longer_in_server_folder = 0 AND
				to_be_moved_on_server = 0
				AND OFFICE = ?
			",
			array ( $subject, get_office() )
		);

		// if any, remap them each
		if ( $affected_messages ) {
			$sql_template = "
				UPDATE $inbox_table
				SET mapped_issue = ?, mapped_pro_con = ?
				WHERE ID = ?
				AND OFFICE = ?
				";
			foreach ( $affected_messages as $affected_message ) {
				$mapping = self::get_subject_line_mapping ( $affected_message->subject ); // need to be inside the loop because of wildcard mapping				
				$sqlsrv->query ( 
					$sql_template, 
					array(
						$mapping['mapped_issue'],
						$mapping['mapped_pro_con'],
						$affected_message->ID,
						get_office()
					)
				);
			}
		}	
	}


	public static function manual_add_subject ( $dummy, $subjectLineObject ) {
		
		global $sqlsrv;
		$response_code =  $sqlsrv->query (
			"
			INSERT INTO subject_issue_map 
				(
				incoming_email_subject, 
				email_batch_time_stamp, 
				mapped_issue, 
				mapped_pro_con,
				office
				)
			VALUES ( ?,?,?,?,? ) 
			",
			array(
				utf8_string_no_tags ( $subjectLineObject->subject ),
				current_time( 'YmdHis' ),
				$subjectLineObject->issue,
				$subjectLineObject->proCon,
				get_office()
			)
		);
		
		if ( false !== $response_code ) {
			self::apply_new_subject_line_map ( utf8_string_no_tags ( $subjectLineObject->subject ), $subjectLineObject->issue, $subjectLineObject->proCon );
		}

		return array ( 	'response_code' => false !== $response_code, 
						'output' =>  
							false !== $response_code 
							? 
							"Subject added OK"
							: 
							"Database error in addition of subject.",
		);
		
	}

	// unmap latest subject line -- going by latest
	// invoked only by unprocess, so latest for subject is latest for subject/pro_con
	public static function unmap_subject ( $subject ) {
		global $sqlsrv;
		$sqlsrv->query ( 
			"
			DELETE from subject_issue_map 
			WHERE incoming_email_subject = ? AND OFFICE = ? AND
			ID IN 
			( SELECT TOP 1 ID FROM subject_issue_map
			ORDER BY email_batch_time_stamp DESC )
			",
			array ( $subject, get_office() )
			);
	}



}

