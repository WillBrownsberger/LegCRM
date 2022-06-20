<?php
/*
*
*	wic-entity-upload.php
*
*  File upload is handled in stages, with js modules and helper php classes for each
*  Some stage transitions ( parse parameter setting, mapping and default setting ) are just user input and can be redone on same form
*  Other stages that involves processing -- parsing, validation, matching, actual database update ( but not actual physical file upload) can be restarted
*		for parsing, validation, matching -- always start with a reset, so can move forward from there
*		for actual database update, not a reset, but a restart/rerun 
*			final_result counts will be correct on a restart/rerun
*	
*
*	STAGE NOTES		
*		-- copy to server 
*			+	status: initialized (when send first chunk) -- cannot return to this status
*				-- interrupt at this stage leaves chunks in temp file and upload stub
*			+	status: copied (when all chunks complete) -- show WIC_Form_Upload (as if from a top button press in upload-upload.js)
*				can return to this status -- parse process does reset before continuing -- so can cancel or return to set parameters screen
*			+   note that at this stage, charset of incoming file is irrelevant since stored in binary field and reconstituted as flat file before further processing
*		-- parse file into staging table of records
*			+   status: staged	( return to this stage is return to mapping screen with no fields mapped) 
*			+ 	character decoding is applied to values within records successfully by fgetcsv, so delimiter/enclosure/escape have to be ASCII, but values can be a supported charset 	
*		-- map fields (fully flexible -- the mapping is redoable and can return to this tage)
*			+   status: mapped (valid mapping, but individual record validation not yet passed)
*		-- validate data
*			+ status: validated -- validation is reset before proceeding from map screen to validation popup so can cancel process or return to map screen
*			+ IF the field is mapped, all form edits are applied -- sanitization and validation and individual required check; 
*			+ If mapped, a select field must have good values (validation implicit in form context)  
*			+ Additionally, IF the constituent identity field is mapped it is validated as a good ID
*		-- matching
*			+ status: matched -- mapping is reset before proceeding so can cancel process or return to match screen
*			+ user can select match of input to existing records from valid identity-specifying combinations or custom fields
*			+ hierarchy of matching order is suggested to user, but user can override
*			+ apart from constituent ID and any custom fields, all permitted matching modes include at least one of identity fields ( fn/ln/email ) 
*			+ this step builds a preupdate constituent stub table (which is dropped on reset)
*			+ MUST EITHER MATCH OR FAIL A MATCH ALTHOUGH HAVING THE NECESSARY FIELDS TO TRY THE MATCH TO GET UPDATED OR INSERTED IN FINAL UPLOAD
*		-- set defaults for constituent	
*			+ status: defaulted -- updated dynamically on and off according to user selections on this screen -- can return here
*			+ Determine basic add/update behavior for matched records
*			+ Allow option to not overwrite good names and addresses with possibly sloppy names and addresses -- set default do not overwrite
*			+ also set defaults for activities ( in same tab as constituents )
*			+ Only allow defaulting of unmapped fields -- if a column can be replaced in part with defaults, user should unmap it and replace it entirely
*			+ If issue number is mapped or is defaulted to non-blank, it controls; otherwise look to title
*		-- actual update
*			+ status is set to started on start -- cannot return earlier stages, but can pick up updates from that status 
*			+ on complete, get status completed 
*			+ if have matched OLD record, must make decision about what to update
*				* for fn/ln and other constituent record information, go by "protect primary identity" indicator
*				* for address, if new type add, if existing type, go by "protect identity" indicator
*				* for email/phone, if new type add, if old type, update if non-blank 
*				* use defaults consistent with these rules where fields unmapped, as if they were the original values
*				* note that can set (and default setting is) to prevent overwrite of data with blank data
*			+ for new records, EZ -- add all; use supplied default
*		-- express update: goes straight from mapped to upload without building intermediate tables has own restart status -- started_express, completed_express
*		
*	Enforcement of proper staging sequence by access sequence.
*
*   Enforcement of Validation and Field Required rules in the upload is explained at:https://github.com/WillBrownsberger/LegCRM/wiki/Validation-Rules-in-the-Upload-Process-(Technical)
*
*
*
*/



class WIC_Entity_Upload extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) {
		$this->entity = 'upload';
		$this->entity_instance = '';
	} 
	
	public static function load_form ( $id, $action ) {
		$upload_entity = new WIC_Entity_Upload ( 'nothing', array());
		$success_form = $action? 'WIC_Form_Upload_' . $action : 'WIC_Form_Upload';
		return array ( 	
			'response_code' => true, 
			'output' => $upload_entity->id_search (  array( 'id_requested' => $id, 'success_form' => $success_form, 'return_object' => true ) )
		);
	}
	// supports creation of this entity without taking an action in construct
	protected function nothing(){}

	
	protected function go_to_current_upload_stage( $args ) { 
	
		// get upload status
		$id = $args['id_requested']; 
		$status = self::get_upload_status($id)['output'];
		// translate status to correct form
		$status_to_form_array = array (
			'initiated'		 	=>	'_Inaccessible',
			'copied' 			=>	'',
			'staged'			=>	'_Map',
			'mapped'			=>	'_Map',
			'validated'			=>	'_Match',
			'matched'			=>	'_Set_Defaults',
			'defaulted' 		=>	'_Set_Defaults',
			'started'			=>	'_Download',
			'started_express' 	=>	'_Download',
			'completed'			=>	'_Download',
			'completed_express' =>	'_Download',
			'reversed'			=>	'_Download',
		);
		
		$success_form = 'WIC_Form_Upload' . $status_to_form_array[$status];
		
		$this->id_search (  array( 'id_requested' => $id, 'success_form' => $success_form ) ); // nb, return_object is treated as true by id_search if set
	}

	// quick update
	public static function update_upload_status ( $upload_id, $upload_status ) {
		global $sqlsrv;  
		$sql = "UPDATE upload set upload_status = '$upload_status' WHERE ID = ? and office =? ";
		$result = $sqlsrv->query( $sql, array( $upload_id, get_office()) );
		return array( 'response_code' => false !== $result, 'output' => false !== $result ? 
			'Status update OK.' 
			: 
			'Error setting upload status.' 
		);
	}

	// quick status 
	public static function get_upload_status ( $upload_id ) {
		global $sqlsrv;  
		$sql = "SELECT upload_status from upload WHERE ID = ? AND OFFICE = ?";
		$result = $sqlsrv->query( $sql, array(  $upload_id, get_office() ) );
		return array( 'response_code' => isset ( $result[0] ), 'output' => isset ( $result[0] ) ? 
			$result[0]->upload_status
			: 
			'Error getting upload status.' 
		);
	}

	/**
	* get staging table for column validation and matching
	*/	
	public static function get_staging_table_records( $staging_table, $offset, $limit, $field_list ) {
		global $sqlsrv;
		$sql = "SELECT STAGING_TABLE_ID, VALIDATION_STATUS, VALIDATION_ERRORS, 
				MATCHED_CONSTITUENT_ID, MATCH_PASS, 
 				FIRST_NOT_FOUND_MATCH_PASS, NOT_FOUND_VALUES,				
				$field_list FROM $staging_table s ORDER BY s.STAGING_TABLE_ID OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
		$result = $sqlsrv->query( $sql, array() );
		return ( $result ); 
	}

   	// pass through from original entity, consistent with option value process -- supports set_defaults phase of upload
	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}

    protected static $entity_dictionary = array(


		'activity_date'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Activity Date',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'activity_type'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Activity Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Type',
			'option_group' =>  'activity_type_options',),
		'add_unmatched'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Add unmatched constituents (uncheck to skip unmatched):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'address_type'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Address type ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'address_type_options',),
		'charset'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Character set (try changing this if characters look wrong or you see stray question marks in your upload text)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => 'UTF-8',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'charset_options',),
		'city'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'City/town ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'create_issues'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Create new issues from unmatched non-blank titles (check to accept or go back and unmap titles):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'delimiter'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'radio',
			'field_label' =>  'Delimiter (character between fields)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => 'comma',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'delimiter_options',),
		'email_type'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Email type ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'email_type_options',),
		'enclosure'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'radio',
			'field_label' =>  'Enclosure (character enclosing fields that might include the delimiter)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '2',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'enclosure_options',),
		'escape'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Escape (character indicating that next character should be read literally, not as enclosure or delimiter)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'ID'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Internal Id for Upload',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'includes_column_headers'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Has column headers',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '1',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'issue'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu_issue',
			'field_label' =>  'Activity Issue',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Issue',
			'option_group' =>  'get_issue_options',),
		'max_execution_time'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Max execution time for verification',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '300',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'max_line_length'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Max line length (sum of lengths of data in all fields in the input file row)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '2000',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'phone_type'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Phone type ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'phone_type_options',),
		'pro_con'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Activity Pro/Con',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Pro/Con',
			'option_group' =>  'pro_con_options',),
		'protect_blank_overwrite'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Protect all fields from being overwritten by blank input (leave checked for most uploads):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'protect_identity'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'For matched constituents, only update phone, email or activity (protect name, custom data and address):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_column_map'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_default_decisions'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_final_results'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_match_results'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'serialized_upload_parameters'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'textarea',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'state'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'State ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  'state_options',),
		'update_matched'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Update matched constituents (uncheck to skip matched):',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'upload_by'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Upload User',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_administrator_array',),
		'upload_file'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'upload_status'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '1',
			'field_type' =>  'selectmenu',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'upload_status',),
		'upload_time'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Upload Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet uploaded',
			'option_group' =>  '',),
		'zip'=> array(
			'entity_slug' =>  'upload',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Postal code ',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),

	 );

	 public static $option_groups = array(
		'activity_type_options'=> array(
			array('value'=>'','label'=>'Type?',),
			array('value'=>'0','label'=>'eMail',),
			array('value'=>'1','label'=>'Call',),
			array('value'=>'2','label'=>'Petition',),
			array('value'=>'3','label'=>'Meeting',),
			array('value'=>'4','label'=>'Letter In',),
			array('value'=>'LO','label'=>'Letter Out',),
			array('value'=>'5','label'=>'Social Media Contact',),
			array('value'=>'6','label'=>'Conversion',),
			array('value'=>'7','label'=>'Case Closure',),
			array('value'=>'MO','label'=>'Member Of',),
			array('value'=>'wic_reserved_77777777','label'=>'Document',),
			array('value'=>'wic_reserved_00000000','label'=>'Email In',),
			array('value'=>'wic_reserved_99999998','label'=>'Queued email',),
			array('value'=>'wic_reserved_99999999','label'=>'Email Out',)),
		'address_type_options'=> array(
		  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Home',),
			  array('value'=>'1','label'=>'Work',),
			  array('value'=>'2','label'=>'Mail',),
			  array('value'=>'3','label'=>'Other',),
			  array('value'=>'wic_reserved_4','label'=>'Registered',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
		'charset_options'=> array(
		  array('value'=>'UTF-8','label'=>'ASCII, UTF-8 or Unknown',),
			  array('value'=>'ISO-8859-1','label'=>'Western European (ISO-8859-1)',),
			  array('value'=>'WINDOWS-1252','label'=>'ANSI (WINDOWS-1252)',),
			  array('value'=>'MAC','label'=>'MAC',)),
		'delimiter_options'=> array(
		  array('value'=>'comma','label'=>'Comma (common in .csv files)',),
			  array('value'=>'semi','label'=>'Semi-Colon (sometimes used in .csv files)',),
			  array('value'=>'tab','label'=>'Tab (common in .txt files)',),
			  array('value'=>'space','label'=>'Space',),
			  array('value'=>'colon','label'=>'Colon',),
			  array('value'=>'hyphen','label'=>'Hyphen (-)',),
			  array('value'=>'pipe','label'=>'Pipe (|)',)),
		'email_type_options'=> array(
		  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Personal',),
			  array('value'=>'1','label'=>'Work',),
			  array('value'=>'2','label'=>'Other',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
		'enclosure_options'=> array(
		  array('value'=>'2','label'=>'Double Quote',),
			  array('value'=>'1','label'=>'Single Quote',),
			  array('value'=>'b','label'=>'Back Tick ()',)),
		'phone_type_options'=> array(
		  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Home',),
			  array('value'=>'1','label'=>'Cell',),
			  array('value'=>'2','label'=>'Work',),
			  array('value'=>'3','label'=>'Fax',),
			  array('value'=>'4','label'=>'Other',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
		'pro_con_options'=> array(
		  array('value'=>'0','label'=>'Pro',),
			  array('value'=>'1','label'=>'Con',),
			  array('value'=>'','label'=>'Pro/Con?',)),
		'state_options'=> array(
		  array('value'=>'','label'=>'',),
			  array('value'=>'AL','label'=>'AL',),
			  array('value'=>'AK','label'=>'AK',),
			  array('value'=>'AZ','label'=>'AZ',),
			  array('value'=>'AR','label'=>'AR',),
			  array('value'=>'CA','label'=>'CA',),
			  array('value'=>'CO','label'=>'CO',),
			  array('value'=>'CT','label'=>'CT',),
			  array('value'=>'DE','label'=>'DE',),
			  array('value'=>'FL','label'=>'FL',),
			  array('value'=>'GA','label'=>'GA',),
			  array('value'=>'HI','label'=>'HI',),
			  array('value'=>'ID','label'=>'ID',),
			  array('value'=>'IL','label'=>'IL',),
			  array('value'=>'IN','label'=>'IN',),
			  array('value'=>'IA','label'=>'IA',),
			  array('value'=>'KS','label'=>'KS',),
			  array('value'=>'KY','label'=>'KY',),
			  array('value'=>'LA','label'=>'LA',),
			  array('value'=>'ME','label'=>'ME',),
			  array('value'=>'MD','label'=>'MD',),
			  array('value'=>'MA','label'=>'MA',),
			  array('value'=>'MI','label'=>'MI',),
			  array('value'=>'MN','label'=>'MN',),
			  array('value'=>'MS','label'=>'MS',),
			  array('value'=>'MO','label'=>'MO',),
			  array('value'=>'MT','label'=>'MT',),
			  array('value'=>'NE','label'=>'NE',),
			  array('value'=>'NV','label'=>'NV',),
			  array('value'=>'NH','label'=>'NH',),
			  array('value'=>'NJ','label'=>'NJ',),
			  array('value'=>'NM','label'=>'NM',),
			  array('value'=>'NY','label'=>'NY',),
			  array('value'=>'NC','label'=>'NC',),
			  array('value'=>'ND','label'=>'ND',),
			  array('value'=>'OH','label'=>'OH',),
			  array('value'=>'OK','label'=>'OK',),
			  array('value'=>'OR','label'=>'OR',),
			  array('value'=>'PA','label'=>'PA',),
			  array('value'=>'RI','label'=>'RI',),
			  array('value'=>'SC','label'=>'SC',),
			  array('value'=>'SD','label'=>'SD',),
			  array('value'=>'TN','label'=>'TN',),
			  array('value'=>'TX','label'=>'TX',),
			  array('value'=>'UT','label'=>'UT',),
			  array('value'=>'VT','label'=>'VT',),
			  array('value'=>'VA','label'=>'VA',),
			  array('value'=>'WA','label'=>'WA',),
			  array('value'=>'WV','label'=>'WV',),
			  array('value'=>'WI','label'=>'WI',),
			  array('value'=>'WY','label'=>'WY',)),
		'upload_status'=> array(
		  array('value'=>'staged','label'=>'Staging Table Loaded',),
			  array('value'=>'mapped','label'=>'Fields Mapped',),
			  array('value'=>'validated','label'=>'Data Validated',),
			  array('value'=>'matched','label'=>'Records Matched',),
			  array('value'=>'defaulted','label'=>'Valid default decisions',),
			  array('value'=>'started','label'=>'Upload Started, not completed',),
			  array('value'=>'completed','label'=>'Upload Completed',),
			  array('value'=>'reversed','label'=>'Upload Backed Out',)), 
	  
	  );


}
