<?php
/*
* 	class-wic-db-email-message-object.php
*
*	takes an email json image from office 365, formats for legacy processing and conducts much of that processing
*	
*	see classification of outcome codes	
*
*   this object is saved as serialized by the parse process and is retrieved by the inbox detail view and by the email processor
*
*/

class WIC_DB_Email_Message_Object {
	
	/*
	* this is the master template 
	*   upload tables derived directly from it for maintainability
	*	-- see wic_db_access_upload_email.php
	*
	* use utf-8 options on for preg_match where pattern may contain utf-8
	*/
	// final product properties based on all information
	public $email_address	= '';
	public $phone_number 	= ''; 
	public $first_name	= '';	
	public $middle_name	= '';
	public $last_name	= '';
	public $address_line = '';
	public $city		= '';
	public $state 		= '';
	public $zip 		= '';
	public $activity_date = '';	
	// object properties corresponding to parsed object from headerinfo()
	public $to_personal 		= '';	// extracted from to object
	public $to_email 			= '';	// extracted from to object
	public $from_personal 		= '';	// extracted from from object
	public $from_email 			= '';	// extracted from from object	
	public $from_domain 		= ''; 	// domain portion of $from_email
	public $reply_to_personal 	= '';	// extracted from reply_to object
	public $reply_to_email	 	= '';	// extracted from reply_to object
	public $to_array 			= array(); 	// processed from to object;
	public $cc_array 			= array(); 	// processed from from object	
	public $raw_date 			= '';	// The message date as found in its headers 
	public $email_date_time 	= '';	// The message date_time in blog local timze zone in mysql format (for display and sorting only)	
	public $subject 			= ''; 	// The message subject 
	public $message_id 			= ''; 	// Unique identifier (permanent unique identifier -- retained only for research purposes)
	// key value header array for use only by locally-defined tab functions
	public $internet_headers;
	// structure parts accessed through imap body fetch functions
	protected $text_body;  		// stripped for parsing -- prefer to generate from raw text body but can strip from raw html body
	public $raw_html_body;		// only used for display in message viewer
	// error properties
	// identifier -- need for attachment links
	public $inbox_image_id;
	// presentation variables -- loaded in object for transport across parse steps, but also saved on image record . . . never changed aftre parse
	public $category = '';
	public $snippet = '';
	public $account_thread_id = '';
	/* 
	* 
	* 
	*/

	// reinstatiate object from database
	public static function build_from_image_uid ($UID ) {  
		global $sqlsrv;
		$inbox_table = 'inbox_image';

		$sql = "
			SELECT * 
				FROM $inbox_table
				WHERE 
					folder_uid = ? AND
					no_longer_in_server_folder = 0 AND
					to_be_moved_on_server = 0 
					AND OFFICE = ?	
				";
		$message_array = $sqlsrv->query( $sql, array( $UID, get_office() ) );

		// unserialize the main object
		if ( $message_array ) {
			$message_object = json_decode( $message_array[0]->parsed_message_json );
		} else {
			return false;
		}
		if ( $message_object ) {
			// add in properties maintained unserialized on record
			$message_object->inbox_image_id				=  $message_array[0]->ID;
			$message_object->mapped_issue 				=  $message_array[0]->mapped_issue;
			$message_object->mapped_pro_con 			=  $message_array[0]->mapped_pro_con;
			$message_object->assigned_constituent	 	=  $message_array[0]->assigned_constituent;
			$message_object->is_my_constituent			=  $message_array[0]->is_my_constituent_guess; // translating from guess to non guess for save purposes
			$message_object->display_date				=  $message_array[0]->email_date_time;
			$message_object->inbox_defined_staff		=  $message_array[0]->inbox_defined_staff;
			$message_object->inbox_defined_issue		=  $message_array[0]->inbox_defined_issue;
			$message_object->inbox_defined_pro_con		=  $message_array[0]->inbox_defined_pro_con;
			$message_object->inbox_defined_reply_text	=  $message_array[0]->inbox_defined_reply_text;
			$message_object->inbox_defined_reply_is_final	=  $message_array[0]->inbox_defined_reply_is_final;

			// at time of parse, attempted to find matching constituent
			if ( $message_array[0]->assigned_constituent ) {
				$constituent_object = WIC_DB_Access_WIC::get_constituent_name( $message_array[0]->assigned_constituent, true );
				if ( $constituent_object ) {
					$message_object->first_name 	= $constituent_object->first_name 	? $constituent_object->first_name 	: $message_object->first_name;
					$message_object->middle_name 	= $constituent_object->middle_name 	? $constituent_object->middle_name 	: $message_object->middle_name;
					$message_object->last_name 		= $constituent_object->last_name 	? $constituent_object->last_name 	: $message_object->last_name;
				}
			} 

			return ( $message_object );
		} else {
			return false;
		}
		
	}

	// reinstatiate object from database by ID -- streamlined for message viewer
	public static function build_from_id ( $selected_page, $ID ) { // call with $selected_page = done to get an inbox image record
	
		global $sqlsrv;
		$table = 'done' == $selected_page ? 'inbox_image' : 'outbox' ;

		$sql = "
			SELECT * 
				FROM $table
				WHERE ID = ? AND OFFICE = ?
				";
		$message_array = $sqlsrv->query( $sql, array( $ID, get_office()));

		// unserialize the main object if found
		if ( $message_array ) {
			$message_object = ( 'done' == $selected_page ) ? json_decode( $message_array[0]->parsed_message_json ) : unserialize ( $message_array[0]->serialized_email_object );
		} else {
			return false;
		}
		
		// catch error from no serialized version of object if sent by c# side as auto reply
		if ( 'done' != $selected_page && ! $message_object ) {
			$message_object = json_decode( $message_array[0]->json_email_object );
			$message_object->html_body = $message_object->Html_body;
			$message_object->to_array = 
				array(
					array($message_object->To_array[0]->Name,$message_object->To_array[0]->Address,$message_object->To_array[0]->Constituent)
				);
			$message_object->cc_array = array();
			$message_object->bcc_array = array();
			$message_object->subject = $message_object->Subject;

		}

		// return the unserialized object if successful
		if ( $message_object ) {
			$message_object->message_id = $ID;
			$message_object->outbox =  ( $selected_page == 'done' ? 0 : 1 );
			switch ( $selected_page ) {
				case 'done':
					$message_object->assigned_constituent = $message_array[0]->assigned_constituent; // not present on outbox record
					$message_object->display_date =  $message_object->email_date_time;
					// update names from assigned constituent
					if ( $message_array[0]->assigned_constituent ) {
						$constituent_object = WIC_DB_Access_WIC::get_constituent_name( $message_array[0]->assigned_constituent, true );
						if ( $constituent_object ) {
							$message_object->first_name 	= $constituent_object->first_name 	? $constituent_object->first_name 	: $message_object->first_name;
							$message_object->middle_name 	= $constituent_object->middle_name 	? $constituent_object->middle_name 	: $message_object->middle_name;
							$message_object->last_name 		= $constituent_object->last_name 	? $constituent_object->last_name 	: $message_object->last_name;
						}
					} 

					break;
				case 'outbox':
				case 'draft':
					$message_object->display_date =  $message_array[0]->queued_date_time;
					break;
				case 'sent':
					$message_object->display_date =  $message_array[0]->sent_date_time;
					break;			
			}
			return $message_object;
		} else {
			return false;
		}
		
	}

	

	

	/*
	*
	* sanitization functions
	*
	*/


	public static function sanitize_incoming ( $raw_incoming ) {
		global $wic_inbox_image_collation_takes_high_plane;
		/*
		* WIC_Entity_Email_Subject::sanitize_incoming
		*
		* (1) Remove transfer encoding and convert to UTF8
		* (2) Strip any 4 byte (high plane) UTF-8 characters like emoticons 
		*		-- these will not store properly in MySQL ( unless upgraded https://mathiasbynens.be/notes/mysql-utf8mb4 )
		*		-- replace with characters to avoid creating unsafe strings
		*			http://stackoverflow.com/questions/8491431/how-to-replace-remove-4-byte-characters-from-a-utf-8-string-in-php
		*			http://unicode.org/reports/tr36/#Deletion_of_Noncharacters
		* (3) Do a sanitize text field to strip any tags
		*
		* NOTE: function imap_utf8 does NOT work as advertized -- iconv_mime_decode is much more reliable		
		*/
		$first_pass = iconv_mime_decode ( 
				$raw_incoming, 
				ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 
				'UTF-8' 
			);
		if ( $wic_inbox_image_collation_takes_high_plane ) {
			return utf8_string_no_tags ( $first_pass );
		} else {
			return utf8_string_no_tags( preg_replace( '/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $first_pass ) );
		}
	}

	protected function domain_only ( $email_address ) {
		$from_array = preg_split( '#@#', $email_address );
		return isset ( $from_array[1] ) ? $from_array[1] : '';
	}

	// hard strip, but preserves paragraph breaks as double LF
	public static function utf8_to_sanitized_text ( $string, $break_patterns_only = false ) {
		// html break filters to be applied in succession by preg_replace 
		$break_patterns = array (
			 // (1) visual paragraph boundaries -- div, p, h tags (open or close ) and any surrounding whitepace (not read in html)
			'#(?i)\s*<\s*/?\s*(?:div|p|blockquote|address|center|table|h\d)\b[^>]*>\s*#',
			//  (2) visual line break patterns -- br tag and any surrounding whitespace
			'#(?i)<\s*/?\s*br\s*/?\s*>\s*#', 
			//  (3) treat a form feed as a paragraph boundary
			'#\f#',
			//  (4) treat a pipe separator as a line break
			'#\|#', 
		);
		$break_patterns_replacements =array( 
			"<br/><br/>", // these will be replaced in the second step of the array processing
			"\n", // repeated line breaks will be treated as a paragraph break by address parser
			"\n\n",
			"\n" 
		);
		// character filters to be applied in succession by preg_replace 
		$filters = array ( 
			// (1) horizontal spaces filter -- standardize to single blank --  https://www.compart.com/en/unicode/category/Zs  http://php.net/manual/en/regexp.reference.unicode.php
			'#[\t\p{Zs}]+#u',  
			// (2) character filter -- pass only unicode letters, digits, email characters, plus slash and white spaces, NO COMMAS OR PARENS OR TAG CHARACTERS  -- http://php.net/manual/en/regexp.reference.character-classes.php
			// white space included is vertical ( LF,FF ), but not the horizontal spaces other than the plain space; CR passed for replacement at next step
			'#[^\pL0-9 .@_%+/\-\r\v]#u', 
			// (3) get rid of \r carriage returns http://php.net/manual/en/regexp.reference.escape.php
			'#\r(\n)?#', 
			// (4) simplify debugging by eliminating excess LF's
			'#\n{2,}#',
		);
		$filters_replacements = array ( 
			 ' ', // single blank
			 '',  // empty string
			 "\n", // single line feed
			 "\n\n", // double line feed	
		);

		// allow for a lighter strip when using to get legible text
		if ( $break_patterns_only ) {
			return strip_tags(html_entity_decode(preg_replace( $break_patterns, $break_patterns_replacements, $string )));
		}

		$clean_string = preg_replace ( $filters, $filters_replacements,		 // standardize characters
			html_entity_decode (											 // decode entities (fix?: this could create tags . . . .using output only for address parsing)
				strip_tags( 												 // get rid of all other tags
					preg_replace( $break_patterns, $break_patterns_replacements, // put in double LF for block tags
						$string 
					)
				),
				0, // no flags
				'UTF-8'
			)
		);
		return ( $clean_string );
	}

	/* 
 	* strip head, script and styles for security; (would like to also run kses_post, but it strips cid and potentially other valid email elements)
	*
	* the old approach in this function was regex based:
	*	$patterns_to_strip = array( 
	*		'#(?i)<(head|style|script|embed|applet|noscript|noframes|noembed)\b[^>]*>(?:[\s\S]*?)</\1\s*>#', // strip head, style and script tag (AND, unlike KSES, the content between them)
	*		'#(?i)<!--(?:[\s\S]*?)-->#', // strip comments, including some mso conditional comments
	*		'#(?i)<[^>]*doctype[^>]*>#', // strip docttype tags
	*		'#(?i)<!\[if([^\[])*\]-?-?>#', // hacky specific solution to common conditional comment formats
	*			'#(?i)<!\[endif\]-?-?>#', // 
	*
	*	);	
	* 	return 	balanceTags ( preg_replace (  $patterns_to_strip, '', $html ), true );
	*
	* the old approach above choked on long head elements, returning empty string ("catastrophic backtracking" when tested in regex101 ).
	*    -- this result predicted here: https://stackoverflow.com/questions/7130867/remove-script-tag-from-html-content
	*	 -- see also https://stackoverflow.com/questions/1732348/regex-match-open-tags-except-xhtml-self-contained-tags/1732454#1732454
	*	 -- similar post: https://techsparx.com/software-development/wordpress/dom-document-pitfalls.html
	* the approach below derives from the first mentioned comment;
	*   if this approach shows weaknesses, consder htmlpurifier: http://htmlpurifier.org/
	*   in taking the html into a dom and spitting it back out, the function also removes all comments including outlook conditional comments
	*/
	public static function strip_html_head( $html ) {
		if ( $html =='') {
			      return '';
			}
		/*
		* see comments to http://php.net/manual/en/domdocument.construct.php -- optional declaration of character code in the constructor does not control loadHTML; unsure on version declaration, so go with defaults
		*/
		$dom = new DOMDocument(); // http://php.net/manual/en/class.domdocument.php
		/*
		*
		* https://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
		*
		* loadHTML interprets incoming as ISO-8859 and garbles UTF-8 Characters; use html-entities instead
		*
		* alternative is to add a metatag in front, but this may not work if there is a conflicting meta tag 
		*
		* note -- upstream from this function, $html was converted UTF-8 if charset detected; if none defined, effectively assuming UTF-8 here
		*   -- will flatten unidentified charset to ascii if not using mb4 encoding, since will high plane will cause errors;
		*   -- if not flattened, might not be UTF-8/ascii so some risk remains . . . 
		*   -- most likely no charset case is plain text which in my universe of correspondents is likely ascii or utf-8 so risk is low
		*/
		$converted_html = htmlspecialchars_decode(htmlentities( $html,  ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8' ),ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
		// encodes all possible html entities then decodes html special characters
		if ( ! @$dom->loadHTML( $converted_html  ) ) { // suppress complaints about the validity of the html, but act on full error
			return '<h3>Malformed HTML message. Could not safely present.</h3>';
		} 

		$forbidden_tags = array (
			'head', 'style', 'script', 'embed', 'applet', 'noscript', 'noframes', 'noembed'
		);
		
		foreach ( $forbidden_tags  as $forbidden_tag ) {
			$forbidden = $dom->getElementsByTagName( $forbidden_tag );
			$remove = [];
			foreach($forbidden as $item) {
				$remove[] = $item;
			}
			foreach ($remove as $item){
				$item->parentNode->removeChild( $item ); 
			}
		}

		// while we are here, make sure that links open in a new window
		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			$link->setAttribute( 'target' , '_blank'  );
			$link->setAttribute( 'rel' , 'noopener noreferrer'  ); // contra tabnabbing https://www.jitbit.com/alexblog/256-targetblank---the-most-underestimated-vulnerability-ever/
		}

		$body_elements = $dom->getElementsByTagName( 'body' );
		// taking the first, hopefully only
		foreach ( $body_elements as $body ) {
			// have UTF-8 characters, but re-encode them for safe presentation in other charsets
			// does not encode html control tags <>'"
			// encodes all possible html entities then decodes html special characters
			$clean_html =  htmlspecialchars_decode(htmlentities( $dom->saveHTML( $body ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8' ),ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
			// convert any php tag opening/closing to entities -- avoid saving executable php
			$clean_html = str_replace (  array ('<?', '?>'), array ('&lt;?', '?&gt;'), $clean_html );

			return $clean_html;		
		}
		
	}

	public static function pre_sanitize_html_to_text ( $html ) {
		// white space in html has no effect beyond first space, so strip it to a space
		return preg_replace ( '#\s+#', ' ', $html );
	}

	
	// make an array of address that is safe and always an array from unreliable incoming address information
	private function repack_address_array ( $address_array ) { 
		$clean_array = array();
		if ( !$address_array || !is_array( $address_array ) ) {
			return $clean_array;
		} else  {
			foreach ( $address_array as $address ) {
				$clean_array[] = array( 
					$address->emailAddress->name, 
					filter_var( $address->emailAddress->address, FILTER_VALIDATE_EMAIL ) ? $address->emailAddress->address : '',
					self::quick_check_email_address ( $address->emailAddress->address )
				);
			}
		}
		return ( $clean_array );		
	}
	
	public static function quick_check_email_address ( $email_address ) {
		if ( !filter_var( $email_address, FILTER_VALIDATE_EMAIL )  ) {
			return -1;
		}
		global $sqlsrv;
		$result = $sqlsrv->query ( 
			"
			SELECT TOP 1 constituent_id 
			FROM email e INNER JOIN constituent c on c.id = e.constituent_id 
			WHERE email_address = ? AND c.office = ?",
			array( $email_address, get_office())
		);
	if ( $result ) {
			return $result[0]->constituent_id;
		} else {
			return 0;
		}
	}
	
	private  function convert_internet_headers_to_key_value_array( $object_array ) {

		$header_key_value = array();
		foreach ( $object_array as $header ) {
			$header_key_value[$header->name] = $header->value;
		}
		return $header_key_value;
	 }
	
} // class 