<?php
/*
*
* class-wic-db-access-issue.php
*
* collects misc function providing db access to issues
* 
* update of issues from issues creation screen now happen through wic_db_access_wic 
*
*/

class WIC_DB_Access_Issue  {

	/*
	*
	* Used to populate activity issue drop down with open issues
	*
	*/
	public static function get_wic_live_issues () {
		
		global $sqlsrv;		
		
		$post_table = 'issue';

		$sql = "
			SELECT p.ID, p.post_title  
			FROM $post_table p 
			WHERE wic_live_issue = 'open' AND post_type = 'post' AND OFFICE = ?
			ORDER BY p.post_title 
			";
		
		return $sqlsrv->query( $sql, array(get_office()) );
	}

	// necessary in bulk uploads where using title to get issue id
	public static function fast_id_lookup_by_title( $title ) {
		global $sqlsrv;
		$sql = $sqlsrv->query ( "SELECT TOP 1 ID from issue 
			WHERE post_type = 'post' 
			AND post_title = ?
			AND OFFICE = ?
			", 
			array ( $title, get_office() )
		);
		return   $sqlsrv->num_rows ? $sqlsrv->last_result[0]->ID : false;
	}

	// no office ref -- id
	public static function fast_title_lookup_by_id(  $id ) {
		if ( !$id = intval($id)) {
			return false;
		}
		global $sqlsrv;
		$sqlsrv->query ( "SELECT post_title from issue WHERE id = ? ", array ( $id ));
		return  $sqlsrv->num_rows ? $sqlsrv->last_result[0]->post_title : false ;
	}

	// version handles cases; no office ref
	public static function format_title_by_id(  $id ) {
		if ( !$id = intval($id)) {
			return false;
		}
		global $sqlsrv;
		$sqlsrv->query ( "SELECT post_title from issue WHERE id = ?", array ( $id ) );
		
		if ( ! $sqlsrv->num_rows ) {
			return "Deleted issue ( Issue $id )";
		} else {
			return $sqlsrv->last_result[0]->post_title;
		}

	}


	public static function fast_id_validation( int $id ) {
		global $sqlsrv;
		$post_table = 'issue';
		$sqlsrv->query ( "SELECT ID from $post_table WHERE id = ?", array ( $id ));
		return  $sqlsrv->num_rows > 0 ;
	}

	public static function create_reply_field_name( $pro_con_value) {
		global $sqlsrv;
		if ( !in_array( $pro_con_value, array('','0','1') ) ) {
			return false;
		} else {
			return 'reply' . $pro_con_value;
		}

	}

	// returns false on bad call; 0 if not exist; id if does exist;
	public static function does_reply_exist ( int $issue, $pro_con_value ) {
		global $sqlsrv;
		$link_field = self::create_reply_field_name ( $pro_con_value );
		if ( !$link_field ) {
			return false;
		} 

		$sqlsrv->query ( "SELECT $link_field FROM issue where ID = ?", array ( $issue ) );
		if ( $sqlsrv->num_rows ) {
			return $sqlsrv->last_result[0]->$link_field;
		} else {
			return false;
		}
	}

	// save type post as plain text, always; save type reply template as html
	public static function quick_insert_post ( $title, $content, $type = 'post', $category = 'uncategorized' ) {
		global $sqlsrv;
		$post_table = 'issue';
		$sqlsrv->query ( 
			"
			INSERT INTO $post_table 
			(
				last_updated_time,
				post_content,
				post_title,
				post_type,
				last_updated_by,
				post_category,
				office, 
				follow_up_status, 
				issue_staff,
				wic_live_issue 
				)
			VALUES
			([dbo].[easternDate](),?,?,?,?,?,?,?,?,?)
			",
			array ( 
				'post' == $type ? 
					// hard strip for post (hoping for somewhat legible plain text)
					preg_replace( '#\s{3,}#', "\n", // eliminate excess white space
						WIC_DB_Email_Message_Object::utf8_to_sanitized_text( // converts formatting tags to breaks, new lines
							WIC_DB_Email_Message_Object::strip_html_head( // strips dangerous tags and content within them
								$content 
							),
							true // ony strip break patterns, not other characters  
						)
					) :  
					// safety strip only for reply template (removes disallowed tags and all btw disallowed tags);preserve html
					WIC_DB_Email_Message_Object::strip_html_head( $content ),
				utf8_string_no_tags($title),
				utf8_string_no_tags($type),
				get_current_user_id(),
				'post' == $type ? utf8_string_no_tags($category) : '',
				get_office(),
				'',
				0,
				'post' == $type  ? 'open' : '' // defaulting posts created this way to open for assignment
			)
		);
		if ( $sqlsrv->success ) {
			return $sqlsrv->insert_id;
		} else {
			return false;
		}
	}

	public static function quick_add_cross_link ( int $post_id, int $reply_id, $pro_con_value ) {
		global $sqlsrv;
		$link_field = self::create_reply_field_name ( $pro_con_value );
		if ( !$link_field ) { // will return reply, reply0 or reply1 or false
			return false;
		} 
		$result = $sqlsrv->query ( 
			"
			UPDATE issue SET $link_field = ? where ID = ? and OFFICE = ? 
			",
			array ( 
				$reply_id,
				$post_id,
				get_office()
			)
		);
		return $sqlsrv->success;
	}


	public static function quick_update_template ( int $id, $title, $content ) { 

		global $sqlsrv;

		$post_table = 'issue';
		$sqlsrv->query ( 
			"
			UPDATE $post_table 
			SET 
				last_updated_time = [dbo].[easternDate](),
				post_content = ?,
				post_title = ?,
				last_updated_by = ?
			WHERE ID = ?
				",
			array ( 
				WIC_DB_Email_Message_Object::strip_html_head( $content ) ,
				utf8_string_no_tags( $title ),
				get_current_user_id(),
				$id 
			)
		);

		return $sqlsrv->success;
	}

	public static function save_issue_serialized_shape_array ( $id, $serialized_shape_array ) {
		global $sqlsrv;
		$sqlsrv->query ( "UPDATE issue set serialized_shape_array = ? WHERE ID = ?", array( $serialized_shape_array, $id ));
		return $sqlsrv->success;
	}

	public static function get_post_details ( int $issue ) {
		global $sqlsrv;
		$post_table = 'issue';
		$sql = $sqlsrv->query ( "SELECT * from $post_table WHERE id = ?", array ( $issue ) );
		if ( !$sqlsrv->num_rows ) {
			return false;
		} else {
			return $sqlsrv->last_result[0];
		}
	}

	public static function get_the_title ( int $issue ) {
		$issue = self::get_post_details(  $issue );
		return $issue->post_title;
	}

	public static function quick_delete_post_and_unlink ( int $issue, int $reply_id, $pro_con_value ) {
		global $sqlsrv;
		$link_field = create_reply_field_name( $pro_con_value);
		if ( !$link_field ) return false;

		$sqlsrv->query(
			"DELETE p FROM issue p where ID = ? and OFFICE = ?",
			array( $reply_id, get_office() )
		);

		if ( ! $sqlsrv->success ) return false;

		$sqlsrv->query(
			"UPDATE issue SET $link_field = 0 where ID = ? and OFFICE = ?",
			array( $issue, get_office())
		);

		return $sqlsrv->success;
	}

	public static function get_array_of_reply_values ( int $issue ) {
		global $sqlsrv;
		$result = $sqlsrv->query(
			"SELECT reply, reply0, reply1 from issue WHERE ID = ? AND OFFICE = ?", array( $issue, get_office() ) 
		);
		
		// populate array with just the 'blank', '0', or '1' values that exist for pro_con replies
		$return_array = array();
		if ( $sqlsrv->num_rows ) {
			$possible_values = array ( '', '0', '1');
			foreach ( $possible_values as $value ) {
				if ( intval($result[0]->{'reply' . $value }) > 0) {
					if ( $value != '') { // OK before and after 8.0 because string zero never equalled empty
						$return_array[] = $value;
					} else {
						$return_array[] = 'blank';
					}
				}
			}
		}
		return $return_array;
	}


	public static function get_issue_edit_link( int $issue ) {
		return WIC_Admin_Setup::root_url() . '?page=wp-issues-crm-main&entity=issue&action=id_search&id_requested=' . $issue;
	}
	
}


