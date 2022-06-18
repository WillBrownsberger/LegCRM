<?php
/*
*
*	wic-entity-email-unprocess.php
*
*/
class WIC_Entity_Email_Unprocess  {

	public static function handle_undo ( $dummy_id, $data ) {
		// set uid count
		$uid_count = count( $data->uids );
		/*
		* repack uids as an array of ints, fully sanitizing since will use directly in sql statement.
		* cannot parametrize because sqlsrv generates type conversion error when passing 
		* array of integers in string form
		*/
		$uid_ints = array();
		foreach ( $data->uids as $uid) {
			$test = intval($uid);
			if ( is_int($test) && $test > 0 ) { // double checking
				$uid_ints[] =$test ;
			}
		}

		$uid_string_term =  implode( ',', $uid_ints );

		if ( 
			0 == count ( $uid_ints )  || 
			count ( $uid_ints ) != $uid_count 
			) {
			return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => false, 'message' => 'ID list was corrupted.' ) );
		}
		/*
		*
		* step one: attempt to reverse the deletes (testing in case have already been processed to the server)
		*
		*/
		global $sqlsrv;
		$undeleted_count = $sqlsrv->query(
			"
			UPDATE inbox_image
			SET to_be_moved_on_server = 0
			WHERE 
				no_longer_in_server_folder = 0 AND
				folder_uid IN( $uid_string_term )
				AND OFFICE = ?
			",
			array ( get_office() ) 
			);		

		// if too late, reset and stop here
		if ( $undeleted_count != $uid_count ) {
			self::reverse_undeletes ( $uid_string_term );
			return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => false, 'message' => 'Message moves already synchronized to inbox.' ) );
		}
		/*
		*
		* step oneA: reverse the filter creation executed by a block command
		*
		*/
		if ( ! empty( $data->block ) ) {
			// supporting bulk block/delete
			foreach ( $data->uids as $uid ) {
				WIC_Entity_Email_Block::unset_address_filter ( $uid, $data->wholeDomain );
			}
		} 
		// can now return for delete/block cases
		if ( ! empty( $data->deleter ) ) {
			return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => true, 'message' => '' ) );
		}
		/*
		* steps two and three apply only to reply actions as opposed to record action
		*/
		if ( $data->reply ) { // reply is always defined, although possibly false, if not blocking or deleting
		/*
		*
		* step two: attempt to recall the outgoing messages
		*
		* sql selects the activity records ( that are linked to the inbox_image_records that meet the folder_uid criterion AND ) have not been sent
		*/
			
			$unqueued_count = $sqlsrv->query(
				"
				UPDATE o 
				SET held = 1				
				FROM outbox o INNER JOIN inbox_image i on o.is_reply_to = i.ID
				WHERE 
					i.folder_uid IN( $uid_string_term )
					AND o.sent_ok = 0
					AND o.OFFICE = ?
				",
				array ( get_office() ) 
			);
			// if too late, reset and stop here
			if ( $unqueued_count != $uid_count ) {
				self::reverse_unqueues( $uid_string_term );
				self::reverse_undeletes ( $uid_string_term );
				return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => false, 'message' => 'Messages already transmitted.' ) );
			}
		/*
		*
		* step three: with messages successfuly put on hold, proceed to physically delete them (and their related outgoing activity records)
		*
		*/
			$sqlsrv->query(
				"
				DELETE FROM a FROM activity a INNER JOIN 
				outbox o on o.ID = a.related_outbox_record INNER JOIN 
				inbox_image i on o.is_reply_to = i.ID
				WHERE 
					i.folder_uid IN ( $uid_string_term )
					AND i.office = ?
				",
				array ( get_office() ) 
				);
			
			$activity_delete_success = $sqlsrv->success;

			$sqlsrv->query(
				"
				DELETE FROM o FROM outbox o INNER JOIN 
				inbox_image i on o.is_reply_to = i.ID
				WHERE 
					i.folder_uid IN ( $uid_string_term )
					AND i.office = ?
				",
				array ( get_office() ) 
				);

			$outbox_delete_success = $sqlsrv->success;

			if ( !$outbox_delete_success || !$activity_delete_success  ) {
				self::reverse_undeletes ( $uid_string_term );
				return array ( 'response_code' => false, 'output' =>  'Database error on deletion of outgoing messages.  Check outgoing queue and issue activity records.' );
			}
		} // close if $data->reply
		/*
		*
		* step four: remove any created constituent records and associated records if added by transaction
		*
		* not checking return codes in this step and beyond -- at this point, we are committed  
		* and, if fail, the only consequence is garbage records, not bad sent email
		*/
		$entity_array = array ( 'constituent', 'phone', 'email', 'address' );
		$office = get_office();
		foreach ( $entity_array as $entity ) {
			$id = ( 'constituent' == $entity ) ? 'ID' : 'constituent_id';	
			$table = $entity;		
			$sqlsrv->query (
				"DELETE FROM d FROM $table d 
					INNER JOIN activity a ON a.constituent_id = d.$id 
					INNER JOIN inbox_image i on i.ID = a.related_inbox_image_record
					WHERE 
						i.folder_uid IN( $uid_string_term ) AND
						a.email_batch_originated_constituent = 1 AND i.OFFICE = ?
				",
				array( $office )
				);
		}
		/*
		*
		* step five: physically delete the incoming email log records
		*
		*/		
		$sqlsrv->query(
			"
			DELETE FROM a FROM activity a
			INNER JOIN inbox_image i on i.ID = a.related_inbox_image_record
			WHERE 
				i.folder_uid IN($uid_string_term)  AND
				i.office = ?
			",
			array ( get_office() ) 
			);

		/*
		*
		* step six: undo training
		*
		* undo only the subject mapping -- this is enough to force ungroup and user can retrain
		*
		*/
		if ( $data->train ) {
			WIC_Entity_Email_Subject::unmap_subject ( $data->subject );
		}

		return array ( 'response_code' => true, 'output' =>  array( 'delete_successful' => true, 'message' => '' ) );

	}

	private static function reverse_undeletes( $uid_string_term ) {
		global $sqlsrv;
		$sqlsrv->query(
			"
			UPDATE inbox_image
			SET to_be_moved_on_server = 1
			WHERE 
				folder_uid IN($uid_string_term) AND
				OFFICE = ?
			",
			array (  get_office() ) 
			);		
	}
	
	private static function reverse_unqueues( $uid_string_term ) {

		global $sqlsrv;
		$requeued_count = $sqlsrv->query(
			"
			UPDATE  o SET o.held = 0 from outbox o inner join inbox_image i on o.is_reply_to = i.id
			WHERE 
				i.folder_uid IN($uid_string_term)
				AND o.held = 1
				AND i.OFFICE = ?
			",	
			array ( get_office() ) 
			);	
	}

}