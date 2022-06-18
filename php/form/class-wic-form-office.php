<?php
/*
*
*  class-wic-form-option-group.php
*
*/

class WIC_Form_Office extends WIC_Form_Parent  {
	
	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ) {
		return ( parent::get_the_buttons ( $data_array ) . '<a href="' . WIC_Admin_Setup::root_url() . '?page=office-list">' . 'Back to Office List' . '</a>');
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		return ( $this->get_the_verb( $data_array ) . ' Office. ' . $message );
	}

	protected function group_special( $group_slug ) { 
		return $group_slug == 'show_log' || $group_slug == 'show_last_seven';
	}

	protected function group_special_show_log (&$data_array) {
		$office = $data_array['ID']->get_value();
		global $sqlsrv;
		$result = $sqlsrv->query (
			"
			SELECT TOP 200 [event_type],[error_code],[event_date_time],[message_subject],[client_request_guid]
			FROM [mail_error_log]
			WHERE OFFICE = ? 
			ORDER BY ID DESC
			",
			array($office)
		);
		$output = "<div id='mail_error_log_list'><h3>Most recent mail error log entries -- showing up to 200</h3>";
		if ( ! $result ) {
			$output .= "<p><em>None found</em></p></div>";
			return $output;
		} else {
			$output .= 
				'<table class="wp-issues-crm-stats">
					<tr>' .
						'<th class = "wic-statistic-text">Event Type</th>' .
						'<th class = "wic-statistic-text">Error Code</th>' .					
						'<th class = "wic-statistic-text">Event Date Time</th>' .
						'<th class = "wic-statistic-text">Message Subject</th>' .
						'<th class = "wic-statistic-text">Client Request GUID</th>' .
					'</tr>';
			foreach ( $result as $row ) {
				$output .= 
					'<tr>' .
						'<td class = "wic-statistic-text">'. $row->event_type . '</td>' .
						'<td class = "wic-statistic-text">'. $row->error_code . '</td>' .					
						'<td class = "wic-statistic-text">'. $row->event_date_time . '</td>' .
						'<td class = "wic-statistic-text">'. $row->message_subject . '</td>' .
						'<td class = "wic-statistic-text">'. $row->client_request_guid . '</td>' .
					'</tr>';
			}
			$output .= '</table></div>';
			return $output;
		}
	
	}

	protected function group_special_show_last_seven(&$data_array) {
		$office = $data_array['ID']->get_value();
		global $sqlsrv;
		$result = $sqlsrv->query (
			"
			SELECT * 
			FROM
			(select office, CONCAT('t',DATEDIFF(dy, queued_date_time, [dbo].[easternDate]())) as dy, COUNT(id) as countM FROM outbox WHERE office = ? AND DATEDIFF(dy, queued_date_time, [dbo].[easternDate]())< 7 GROUP BY office, DATEDIFF(dy, queued_date_time, [dbo].[easternDate]())) pss 
			PIVOT
			(SUM(countM)
			FOR dy IN ([t0],[t1],[t2],[t3],[t4],[t5],[t6])
			) da
			",
			array($office)
		);
		$output = "<div id='mail_error_log_list'><h3>Last seven days outgoing mail volume</h3>";
		$output .= 
			'<table class="wp-issues-crm-stats">
				<tr>' .
					'<th class = "wic-statistic-text">Today</th>' .
					'<th class = "wic-statistic-text">Yesterday</th>' .					
					'<th class = "wic-statistic-text">Today - 2</th>' .
					'<th class = "wic-statistic-text">Today - 3</th>' .
					'<th class = "wic-statistic-text">Today - 4</th>' .
					'<th class = "wic-statistic-text">Today - 5</th>' .
					'<th class = "wic-statistic-text">Today - 6</th>' .
				'</tr>';
		foreach ( $result as $row ) {
				$output .= 
					'<tr>' .
						'<td class = "wic-statistic">'. $row->t0 . '</td>' .
						'<td class = "wic-statistic">'. $row->t1 . '</td>' .					
						'<td class = "wic-statistic">'. $row->t2 . '</td>' .
						'<td class = "wic-statistic">'. $row->t3 . '</td>' .
						'<td class = "wic-statistic">'. $row->t4 . '</td>' .
						'<td class = "wic-statistic">'. $row->t5 . '</td>' .
						'<td class = "wic-statistic">'. $row->t6 . '</td>' .
					'</tr>';
		}
		$output .= '</table></div>';
		return $output;
		
	}

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

	public static $form_groups = array(
		'option_group'=> array(
		   'group_label' => 'Offices',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('is_changed','ID','office_email','office_label','office_secretary_of_state_code','office_type','office_enabled','office_outlook_categories_enabled','office_send_mail_held','office_last_delta_token_refresh_time','last_updated_by','last_updated_time','user')),
		'show_last_seven'=> array(
			'group_label' => 'Recent Outgoing Mail Volume',
			'group_legend' => 'Last seven days',
			'initial_open' => '1',
			'sidebar_location' => '0',
			'fields' => array()),
		'show_log'=> array(
			'group_label' => 'Recent Mail Error Log Entries',
			'group_legend' => 'Shows up to 200 most recent mail error log entries for this office',
			'initial_open' => '1',
			'sidebar_location' => '0',
			'fields' => array()),
		
	);

}