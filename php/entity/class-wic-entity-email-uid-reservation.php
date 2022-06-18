<?php
/*
*  wic-entity-email-uid-reservation-php
*
*
* This class manages the uid reservation queue.
*
* The architecture of WP Issues CRM email reading/replying  intends for users to be 
* able to submit processing requests which might collide with automated activities or even with each other.
*
* IMAP does not handle simultaneous requests well ( no reliable reservation method ) so WP Issues CRM needs to do its own
* management of simultaneous requests.  We use a reservation list.  When a process
* wants to act on a message, it saves the UID ( invariant identifier within a mailbox folder)
* of the message into this list.  The list has the UID as the primary key which MySQL requires to be
* unique. 
* 
* If a second process attempts to reserve the same message before the first is done, it will get a duplicate
* key error -- in effect, we are having the robust MySQL database referee who got there first.  On a false return, process_email will bypass the message.
*
* If the second process attempts to reserve the same message after the first is done and has deleted the reservation, the second process will fail at the next step of retrieving the message which
* will have been moved out of the inbox folder. Again, it will bypass the message.
*
* If a catastrophic failure leaves the message on the reservation list, then, on next loading inbox, an alert will be displayed and the user can take appropriate action.
*
*/
class WIC_Entity_Email_UID_Reservation {

	public static function reserve_uid ( $uid, $data ) {

		if ( ! $uid || ! is_numeric ( $uid ) ) {
			$message = "Bad call to WIC_Entity_Email_UID_Reservation::reserve_uid.\nIncluded 0 or non_numeric uid.  data passed was \n" 
				. print_r($data, true) . "\n\n";
			Throw new Exception( $message );
			return false;
		}

		global $sqlsrv;
		$reservation_time = microtime( true );
		$result = $sqlsrv->query (
			"
			INSERT INTO uid_reservation
				(uid,time_stamp,reservation_time,batch_subject,office)
				VALUES ( ?, [dbo].[easternDate](), ?, ?, ? )	
			",
			array (
				$uid,
				$reservation_time,
				$data->sweep ? 'In Multiple Subject Sweep' : $data->subject,
				get_office()
			)
		);

		if ( ! $result  ) {
			error_log ( 'Two WP Issues CRM send processes attempted to send the same message; only one was allowed to do so.  This shows as a Duplicate Key error, but the error is an intended safeguard against dup sends.  ' );
		}
		return $result;
	}

	public static function release_uid ( $uid ) {
		global $sqlsrv;
		$uid_reservation_table = 'uid_reservation';
		$sql = "DELETE FROM $uid_reservation_table WHERE uid = ? and OFFICE = ?";
		$sqlsrv->query ( $sql, array ( $uid, get_office() ) );
	}

	public static function check_old_uid_reservations() {
		global $sqlsrv;
		$uid_reservation_table = 'uid_reservation';
		$result = $sqlsrv->query (
			"
				SELECT * FROM $uid_reservation_table WHERE reservation_time < ? and office = ?
			",
			array ( microtime( true ) - 120, get_office() )
		);
		
		$stuck_count =  count ( $result );
		if ( $stuck_count > 0 ) {
			$output = "<p>At some point, $stuck_count messages were reserved for processing, but, due to an unknown error, never released. </p>  
					   <p><em>You can just hit the </em><code>Clear</code><em> button below to clear the reservation list. The worst thing that can happen is that a message could get double-recorded or double-replied.</em></p>
					<p>If you want to be sure to avoid double actions on these messages, before you <code>Clear</code>, do the following: </p>
					<ol>
						<li>Check for any message that appears to be stuck -- won't move out of your WP Issues CRM Inbox when you attempt to record/reply to it.</li>
						<li>Check if they appear to have been recorded/replied to -- look at the issue they are assigned to and/or look at the outgoing mail queue.</li>
						<li>If they have been recorded/replied to, delete them manually from your inbox.</li>
					</ol>
					<p>Here is a list of the possibly stuck messages:</p>" .
					'<table class="wp-issues-crm-stats">
						<tr><th class ="wic-statistic-text">Batch Stamp</th><th class ="wic-statistic-text">Message Subject</th></tr>';
				foreach ( $result as $stuck_message ) {
					$output .= 	'<tr><td class ="wic-statistic-text">' 
							. $stuck_message->time_stamp .
							'</td><td class ="wic-statistic-text">'
					   		. $stuck_message->batch_subject .
						   	'</td></tr class ="wic-statistic-text">'; 
					}					
			$output .= '</table><button id="clear-reservation-queue-button" type="button" class = "wic-form-button">Clear</button>';
			return $output;
		} else {
			return false;
		}
	}

	public static function clear_old_uid_reservations ( $dummy_id, $dummy_data ) {
		global $sqlsrv;
		$uid_reservation_table = 'uid_reservation';
		$sql = "DELETE FROM  $uid_reservation_table WHERE OFFICE = ? ";
		$result = $sqlsrv->query ( $sql, array( get_office() ) );
		return array ( 'response_code' => false !== $result, 'output' => (false !== $result) ? '' : 'Database error clearing uid reservations.');
	}

}