<?php
/*
* class-wic-form-upload-complete.php
*
*
*/

class WIC_Form_Upload_Complete extends WIC_Form_Upload  {  			
	
	public function get_form_object ( &$data_array, $message, $message_level, $sql = '' ) {
		
				
		$css_message_level = $this->message_level_to_css_convert[$message_level];

		// set message based on status
		$upload_status = $data_array['upload_status']->get_value();
		if ( 'defaulted' == $upload_status ) { 
		// show message inviting match/rematch
			$message =  sprintf ( 'Ready to complete upload from %s.', $data_array['upload_file']->get_value() );
		} elseif ( 'started' == $upload_status ) {
			$message =  sprintf ( 'Upload interrupted for %s. You can safely attempt restart.', $data_array['upload_file']->get_value() );
		}


		$form =	'<form id = "' . $this->get_the_form_id() . '" class="wic-post-form" method="POST" autocomplete = "on">'; // start to frame form

			// if first run through explain game plan
			if ( 'defaulted' == $upload_status ) { 				
				$upload_parameters 	= json_decode ( $data_array['serialized_upload_parameters']->get_value() );
				$total_input = $upload_parameters->insert_count;	
				$form .= '<div id = "upload-game-plan">' .
					'<h3>' . 'What will happen:' . '</h3>' .
					'<ul class = "upload-status-summary" >' .
					'<li>' .
						'You <em>will</em> make <em>live database changes</em> -- so far you have only tested upload plans and settings.' .
					'</li>' .
					'<li>' .
						'You <em>will not</em> be able to go back and alter mapping, matching or defaults.' .
					'</li>' .
					'<li>' .
						'If, after completing the upload, you discover errors, you <em>will</em> be able to backout added constituents and activities, but you <em>will NOT</em> be able to backout updates to existing constituents.' .
					'</li>' .

					'<li>' .
						sprintf( 'Your original %d input records -- now mapped, validated and matched -- will be finally processed.', $total_input ) .
					'</li><ul>' .
				'</div>';
			}
			
			// place for progress bar -- ajax controlled; initial display none; 
			$form .= '<div id = "wic-finish-progress-bar"></div>';
			// results report
			$form .= '<div id = "upload-results-table-wrapper"><span id="upload-progress-legend"></span>' .
				  self::summary_results( $data_array ) .
			'</div>';


		   // in all cases, echo ID, serialized working fields, nonce
			$form .= $data_array['ID']->form_control();	
			$form .= $data_array['serialized_upload_parameters']->form_control();
			$form .= $data_array['serialized_match_results']->form_control();	
			$form .= $data_array['serialized_default_decisions']->form_control();		
			$form .= $data_array['serialized_final_results']->form_control();	
		 	$form .= WIC_Admin_Setup::wic_nonce_field(); 
			$form .= $this->get_the_legends( $sql ) .							
		'</form>';
		
		return  (object) array ( 'css_message_level' => $css_message_level, 'message' => $message, 'form' => $form ) ;
		
	}

	public static function summary_results ( &$data_array ) {
			// retrieve/compute file totals
		
		$match_results 		= json_decode ( $data_array['serialized_match_results']->get_value() );
		$default_decisions 	= json_decode ( $data_array['serialized_default_decisions']->get_value() );
		$final_results 		= json_decode ( $data_array['serialized_final_results']->get_value() );

		$valid_matched = 0;
		$valid_unique  = 0;
		$valid_dups		= 0;
		$unmatched_records_with_valid_components = 0;
		foreach ( $match_results as $slug => $match_object  ) {
			$valid_matched += $match_object->matched_with_these_components;
			$valid_unique  += $match_object->unmatched_unique_values_of_components;	
			$valid_dups	   +=	$match_object->not_unique;
			$unmatched_records_with_valid_components += $match_object->unmatched_records_with_valid_components;			
		}								

		// table headers				
		$table =  '<table class="wp-issues-crm-stats"><tr>' .
			'<th class = "wic-statistic-text">' . 'Upload Results' . '</th>' .
			'<th class = "wic-statistic">' . 'Planned' . '</th>' .					
			'<th class = "wic-statistic">' . 'Completed' . '</th>' .
		'</tr>';
		
		// new issues row
		$new_issue_count = $default_decisions->create_issues ? $default_decisions->new_issue_count : 0;
		$new_issue_result = isset ( $final_results->new_issues_saved ) ? $final_results->new_issues_saved : 0; 
		$table .= '<tr>' .
			'<td class = "wic-text">' . 'New issues from unique unmatched titles' . '</td>' .
			'<td class = "wic-statistic" >' . $new_issue_count . '</td>' .
			'<td class = "wic-statistic" id = "new_issues_saved" >' . $new_issue_result . '</td>' .
		'</tr>';
		
		// new constituents row
		$new_constituents_count = $default_decisions->add_unmatched ? $valid_unique : 0;
		$new_constituents_result = isset ( $final_results->new_constituents_saved ) ? $final_results->new_constituents_saved : 0; 
		$table .= '<tr>' .
			'<td class = "wic-text">' . 'New constituents' . '</td>' .
			'<td class = "wic-statistic" >' . $new_constituents_count . '</td>' .
			'<td class = "wic-statistic" id = "new_constituents_saved" >' . $new_constituents_result . '</td>' .
		'</tr>';

		// updates row
		$updated_constituents_count = $default_decisions->update_matched  ? $valid_matched : 0;
		$updated_constituents_result = isset ( $final_results->constituent_updates_applied ) ? $final_results->constituent_updates_applied : 0; 
		$table .= '<tr>' .
			'<td class = "wic-text">' . 'Updates for matched constituents' . '</td>' .
			'<td class = "wic-statistic" >' . $updated_constituents_count . '</td>' .
			'<td class = "wic-statistic" id = "constituent_updates_applied" >' . $updated_constituents_result . '</td>' .
		'</tr>';

		// total valid row
		$total_valid_count = $valid_matched + $unmatched_records_with_valid_components;
		$total_valid_records_processed = isset ( $final_results->total_valid_records_processed ) ? $final_results->total_valid_records_processed : 0; 
		$table .= '<tr>' .
			'<td class = "text">' . 'All constituent updates from valid input records (including details for unmatched)' . '</td>' .
			'<td class = "wic-statistic" >' . '--' . '</td>' .
			'<td class = "wic-statistic" id = "total_valid_records_processed" >' . $total_valid_records_processed . '</td>' .
		'</tr>';		


		$table .= '</table>';
		
		return ( $table );	
	} 
	
	 	
}