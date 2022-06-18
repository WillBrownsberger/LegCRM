<?php
/*
*
*	wic-entity-email-attachment.php
*
*   this class collects attachment related functions
*
*	
*/

class WIC_Entity_Email_Attachment {

	
		// called only on upload of attachments for draft
	public static function handle_outgoing_attachment ( &$file_content, $file_name, $draft_id ) {
	
		// rely on user integrity in not obfuscating uploaded file type
		$attachment_type =  wic_mime_content_type_from_filename( $file_name );
		
		$attachment_id = self::save_attachment (
			1, 					// message is in the outbox, even as draft
			$draft_id,			// tested for in uploader
			$file_name,			// has already been sanitized
			$attachment_type, 
			base64_encode($file_content), // attachments are sized as base64 encoded strings
			'',					// no cid
			'attachment' 		// not supporting inline images
		);
	
		if ( $attachment_id ) {
			return self::construct_attachment_list_item  ( $draft_id, $attachment_id, $file_name );
		} else {
			return false;
		}
	}

	public static function save_attachment (   
		$message_in_outbox, 
		$message_id, 
		$message_attachment_filename, 
		$attachment_type, 
		$attachment, 
		$message_attachment_cid,
		$message_attachment_disposition = 'attachment'  
		) {
	
		// set vars
		global $sqlsrv;
		$attachments_table = 'inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = 'inbox_image_attachments_xref';
		$attachment_size = strlen ( $attachment );
		$saveable = ( MAX_MESSAGE_SIZE - 1000 < $attachment_size ); // saveable translates as true = unsaveable, too big!  (not actually constaining max message size; could be multiple attachments)
		$attachment_id = false;	
				
		// does an identical attachment exist?
		// doing the hashing on the database side for consistency
		$attachment_found_result = $sqlsrv->query (
			"SELECT TOP 1 attachment_id FROM $attachments_xref_table WHERE attachment_md5 = HASHBYTES('MD5',?) ",
			array( 
				array($attachment,SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),SQLSRV_SQLTYPE_VARBINARY('max')),
			)
		);
		if ( $attachment_found_result ) {
			$attachment_id =  $attachment_found_result[0]->attachment_id;
		} 

		// if none found, save it
		if ( ! $attachment_id ) {
		
			$result_main = $sqlsrv->query ( 
				"
				INSERT INTO $attachments_table  
				( 
					attachment_size,
					attachment_type,
					attachment
				) VALUES
				( ?,?,? )
				",
				array ( 
					$attachment_size,
					$attachment_type, 
					array($attachment,SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),SQLSRV_SQLTYPE_VARBINARY('max')),
					//https://docs.microsoft.com/en-us/sql/connect/php/constants-microsoft-drivers-for-php-for-sql-server?view=sql-server-ver15
				)
			);
			$attachment_id = $sqlsrv->insert_id;
			// return false if could not save
			if ( ! $result_main ) {
				return false;
			}
		}	
		
		// regardless, insert an XREF record
		
		$result_xref  = $sqlsrv->query ( 
			"
			INSERT INTO $attachments_xref_table  
			( 
				attachment_id,
				attachment_md5,
				message_in_outbox,
				message_id,
				message_attachment_cid,
				message_attachment_filename,
				message_attachment_disposition 
			) VALUES
			( ?,HASHBYTES('MD5',?),?,?,?,?,? )
			",
			array ( 
				$attachment_id,
				//$attachment_md5,
				array($attachment,SQLSRV_PARAM_IN,SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_BINARY),SQLSRV_SQLTYPE_VARBINARY('max')),
				$message_in_outbox,
				$message_id,
				$message_attachment_cid,
				$message_attachment_filename,
				$message_attachment_disposition
			)
		);
		
		// return false if could not save
		if ( ! $result_xref ) {
			return false;
		}
		
		return $attachment_id;
		
	}

	// serves images and attachments (returns nothing if not found)
	public static function emit_stored_file ( $attachment_id, $message_id, $message_in_outbox ) {  

		// set vars
		global $sqlsrv;
		$attachments_table = 'inbox_image_attachments';	// this table includes all attachments, not just inbox attachments	
		$attachments_xref_table = 'inbox_image_attachments_xref';	
		
		// retrieve file name for attachment used in current message	
		$result_xref = $sqlsrv->query ( "SELECT message_attachment_filename, message_attachment_disposition FROM $attachments_xref_table WHERE attachment_id = ? AND message_id = ? AND message_in_outbox = ?", array ( $attachment_id, $message_id, $message_in_outbox ) );

		// retrieve the attachment
		$result = $sqlsrv->query ( "SELECT attachment_type, attachment FROM $attachments_table WHERE ID = ?", array ( $attachment_id ) );
		// send the headrrs and attachment
		if ( $result && $result_xref ) {
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-type: {$result[0]->attachment_type}");
			header("Content-Disposition: {$result_xref[0]->message_attachment_disposition}; filename=\"{$result_xref[0]->message_attachment_filename}\"");
			header("Expires: 0");
			header("Pragma: public");

			$fh = fopen( 'php://output', 'w' ); 
			fwrite( $fh, base64_decode($result[0]->attachment) ); // file is base64 encoded in the database 
			fclose ( $fh );	
		} else {
			header('HTTP/1.0 404 Not Found');
		}
		exit;
	}
	
	public static function replace_cids_with_display_url( $html_body, $message_id, $message_in_outbox = 0 ) { 
	
		$attachments = self::get_message_attachments ( $message_id, $message_in_outbox );
		// replace cid or other matching source with url -- handling html body recursively
		foreach ( $attachments as $attachment ) {
			if ( $attachment->message_attachment_cid )  {
				$src_cid_string = '#src\s*=\s*(\'|")(cid:)?' . $attachment->message_attachment_cid . '\1#';
				$src_url = 'src="' . self::construct_attachment_url ( $message_id,  $attachment->attachment_id, $message_in_outbox ) . '"';
				$html_body = preg_replace ( $src_cid_string, $src_url, $html_body );
			}		
		}
		return $html_body;	
	}

	public static function construct_attachment_url ( $message_id, $attachment_id, $message_in_outbox ) {
		return WIC_Admin_Setup::root_url() . 
			'?page=wp-issues-crm-main&entity=email_attachment' . 
			'&message_id=' . $message_id . 
			'&attachment_id=' . $attachment_id . 
			'&message_in_outbox=' . $message_in_outbox .
			'&attachment_id=' . $attachment_id . 
			'&wic_nonce=' . WIC_Admin_Setup::wic_create_nonce($attachment_id);
	}

	// always invoked in outbox context
	private static function construct_attachment_list_item  ( $message_id, $attachment_id, $file_name )  {
		return '<li class="wic-attachment-list-item">
					<span title = "Delete attachment" class="dashicons dashicons-dismiss"></span>
					<span class="wic-attachment-list-item-message_id">' . $message_id . '</span> ' .
					'<span class="wic-attachment-list-item-attachment_id">' . $attachment_id . '</span> ' .
					'<a target = "_blank" href="' . self::construct_attachment_url ( $message_id, $attachment_id, 1 ) . '" >' 
						. $file_name . 
					'</a>
				</li>';
	}

	public static function generate_attachment_list( $draft_id ) {
		$list = '';
		$attachments = self::get_message_attachments ( $draft_id, 1, false );
		foreach ( $attachments as $attachment ) {
			$list .= self::construct_attachment_list_item ( $draft_id, $attachment->attachment_id, $attachment->message_attachment_filename );		
		} 
		return ( $list );
	}
	
	public static function get_message_attachments ( $message_id, $message_in_outbox, $get_attachment_body = false ) {
		// set vars
		global $sqlsrv;
		$include_body = $get_attachment_body ? ", attachment " : '';	
		$results = $sqlsrv->query (
			"
			SELECT x.*, attachment_size, attachment_type $include_body  
			FROM inbox_image_attachments_xref x INNER JOIN inbox_image_attachments a ON x.attachment_id = a.ID 
			WHERE x.message_id = ? and x.message_in_outbox = ?
			",
			array ( $message_id, $message_in_outbox )
		);
		return $results;			
	}
	
	public static function link_original_attachments ( $old_message, $new_message, $old_message_in_outbox){

		// set vars
		global $sqlsrv;
		$attachments_xref_table = 'inbox_image_attachments_xref';
	
		$sql = $sqlsrv->query(
			"
				INSERT INTO $attachments_xref_table( 
						attachment_id,
						attachment_md5,
						message_in_outbox,
						message_id,
						message_attachment_cid,
						message_attachment_filename, 
						message_attachment_disposition )
					SELECT 
						attachment_id,
						attachment_md5,
						1,
						?,
						message_attachment_cid,
						message_attachment_filename, 
						message_attachment_disposition 
					FROM $attachments_xref_table 
					WHERE
						message_id = ? AND
						message_in_outbox = ?
			",
			array ( $new_message, $old_message, $old_message_in_outbox )
		);

	}
	
	// from online request to delete an attachment from a message -- delete only the xref records if other messages if have the same attachment
	public static function delete_message_attachments ( $dummy, $xref ) {
		// set vars
		global $sqlsrv;
		// delete the xref record; LIMIT to 1 in case user has uploaded same attachment twice and is deduping
		$sqlsrv->query ( 
			"DELETE FROM inbox_image_attachments_xref WHERE attachment_id = ? AND message_ID = ? and message_in_outbox = 1", 
			array ( $xref->attachment_id, $xref->message_id ) 
		); 
		// check if there are any other xref records for the attachment
		$result = $sqlsrv->query ( 
			"SELECT TOP 1 ID FROM inbox_image_attachments_xref WHERE attachment_id = ?", 
			array ( $xref->attachment_id ) 
		); 
		// if not, delete the attachment
		if ( ! $result ) {
			 $sqlsrv->query ( 
				"DELETE FROM inbox_image_attachments WHERE ID = ?", 
				array ( $xref->attachment_id ) 
			);		
		}	
	}
	
	
} // close class