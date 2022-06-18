<?php
/*
*
*	class-wic-entity-constituent.php
*
*
*/

class WIC_Entity_Constituent extends WIC_Entity_Parent {
	
	/*
	*
	* Request handlers
	*
	* 
	*/

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'constituent';
		$this->show_dup_list = true;
	} 
	
	// set values from update process to be visible on form after save or update
	protected function special_entity_value_hook ( &$wic_access_object ) { 
			$time_stamp = $wic_access_object->db_get_time_stamp( $this->data_object_array['ID']->get_value() );
			$this->data_object_array['last_updated_time']->set_value( $time_stamp->last_updated_time );
			$this->data_object_array['last_updated_by']->set_value( $time_stamp->last_updated_by );
	}

	/***************************************************************************
	*
	* Constituent -- special formatters and validators
	*
	****************************************************************************/ 	


	// note: since phone is multivalue, and formatter is not invoked in the 
	// WIC_Control_Multivalue class (rather at the child entity level), 
	// this function is only invoked in the list context
	public static function phone_formatter ( $phone_list ) {
		$phone_array_with_dups = explode ( ',', $phone_list );
		$phone_array = array_unique( $phone_array_with_dups );
		$formatted_phone_array = array();
		foreach ( $phone_array as $phone ) {
			if ( 0 == $phone ) continue;
			$formatted_phone_array[] = WIC_Entity_Phone::phone_number_formatter ( $phone );		
		}
		return ( implode ( '<br />', $formatted_phone_array ) );
	}
	
	public static function email_formatter ( $email_list ) {
		$email_array_with_dups = explode ( ',', $email_list );
		$email_array = array_unique( $email_array_with_dups);
		$clean_email_array = array();
		foreach ( $email_array as $email ) {
			if ( preg_match( '#^[ |.,]*$#', $email ) ) continue;
			$clean_email_array[] = safe_html ( $email );		
		}
		return ( implode ( '<br />', $clean_email_array ) );
	}		

	public static function address_formatter ( $address_list ) {
		$address_array_with_dups = explode ( ',', $address_list );
		$address_array = array_unique( $address_array_with_dups);
		$clean_address_array = array();
		foreach ( $address_array as $address) {
			if ( preg_match( '#^[ |,.]*$#', $address ) ) continue;
			$clean_address_array[] = safe_html ( $address );		
		}
		return ( implode ( '<br />', $clean_address_array ) );
	}	


	public static function hard_delete ( $id, $second_constituent ) { 
		// test if have activities
		global $sqlsrv;

		if ( $id == $second_constituent ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'reason' => 'Looks like you selected the same constituent as a dup when you attempted to delete/dedup.  Try again.' ) );   
		} else {
			if ( $second_constituent ) {
				// prepare to do deduping
				

				// get data for comparison
				$delete_constituent = $sqlsrv->query ( "SELECT * from constituent WHERE ID = ?", array($id) );
				$move_to_constituent = $sqlsrv->query ( "SELECT * from constituent WHERE ID = ?", array($second_constituent) );
				// fields to consolidate -- basic, personal, registration; no multivalue or management
				$fields = array(
					'congressional_district',
					'council_district',
					'councilor_district',
					'county',
					'date_of_birth',
					'employer',
					'first_name',
					'gender',
					'is_deceased',
					'last_name',
					'middle_name',
					'occupation',
					'other_district_1',
					'other_district_2',
					'party',
					'precinct',
					'registration_date',
					'registration_id',
					'registration_status',
					'salutation',
					'state_rep_district',
					'state_senate_district',
					'ward',
					'year_of_birth'
				);
				// open a transaction and update second constituent with deleted data
				$set_clause = 'SET last_updated_time = ?,  last_updated_by = ?' ;
				$values = array ( current_time( 'YmdHis' ), get_current_user_id() );
				foreach ( $fields as $field ) {
					if ( '' < $delete_constituent[0]->$field && '' == $move_to_constituent[0]->$field ) {
						$set_clause .= ", $field = ? "; 
						$values[] = $delete_constituent[0]->$field;					
					}
				}
				$values[] = $second_constituent;
				$sqlsrv->query ( "BEGIN TRANSACTION", array());
				$sqlsrv->query ( "UPDATE constituent $set_clause WHERE ID = ?", $values );
				$result_update = $sqlsrv->success;

				// transfer subsidiary records over
				$result_transfer = true;
				foreach ( array ( 'activity', 'address', 'email', 'phone' ) as $table ) {
					$sqlsrv->query (
						"UPDATE $table SET constituent_id = ? WHERE constituent_id = ?", 
						array( $second_constituent, $id )
					);
					$result_transfer = $sqlsrv->success && $result_transfer;
				}			
			
				// delete primary constituent amd commit the transaction 
				$sqlsrv->query ( " DELETE from constituent WHERE ID = ?", array($id) );
				$result_delete = $sqlsrv->success;

				// check for full success and commit or rollback
				$transaction_complete = $result_update && $result_transfer && $result_delete;
				$action = $transaction_complete ? "COMMIT TRANSACTION" : "ROLLBACK TRANSACTION";
				$sqlsrv->query ( $action, array());

				$deleted = $transaction_complete && $sqlsrv->success;
				return array ( 'response_code' => true, 'output' => (object) array ( 
					'deleted' 			 => $deleted, 
					'second_constituent' => $second_constituent,
					'reason' 			 => $deleted ? 
						'This constituent has been deleted and data transferred -- will redirect to the other constituent momentarily.' : 
						'Database error on attempted delete.' 
					) 
				);
				
			} else {
				$sqlsrv->query( "EXECUTE [dbo].fullDeleteConstituent ?", array($id) );
				$deleted = $sqlsrv->success;
			}
		}
		return array ( 'response_code' => true, 'output' => (object) array ( 
			'deleted' => $deleted, 
			'second_constituent'=>false, 
			'reason' => $deleted ? 
				'This constituent has been deleted.' : 
				'Database error on attempted delete.' 
			) 
		);
	}
	
	/* utility to check if assigned and open with a staff member */
	public static function get_assigned_staff( $constituent_id ) {
		// return assigned staff if open and assigned; return 0 if not found or not open or not assigned;
		global $sqlsrv;
		$results = $sqlsrv->query("SELECT case_assigned, case_status FROM constituent WHERE ID = ?", array( $constituent_id) );
		if ( ! $results ) {
			return '';
		} else { 
			if ( 0 < $results[0]->case_assigned && 0 < $results[0]->case_status ) {
				return $results[0]->case_assigned;
			} else {
				return 0;
			}
		}
	}

	/* utility to check is_my_constituent flag */
	public static function is_my_constituent ( $constituent_id ) {
		// return is_my_constituent value or '' if not found
		global $sqlsrv;
		$constituent_table = 'constituent';
		$results = $sqlsrv->query ( "SELECT is_my_constituent FROM $constituent_table WHERE ID = ?", array($constituent_id) );
		if ( ! $results ) {
			return '';
		} else { 
			return $results[0]->is_my_constituent;
		}
	}

	public static function list_delete_constituents( $dummy, $search_id ) {

		// set up global and table names for database access	
		global $sqlsrv;

		$temp_constituent_table = WIC_List_Constituent_Export::temporary_id_list_table_name();

		// create the temporary table
		WIC_List_Constituent_Export::create_temporary_id_table ( 'constituent_delete', $search_id );		

		// check that some constituents still exist
		if ( !$sqlsrv->query( "SELECT TOP 1 ID from $temp_constituent_table ", array()) ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'message' => "Database error or constituents changed or deleted by another user.") );
		}
		
		// do the deletes
		if ( $result = $sqlsrv->query( "execute deleteFullListConstituents ?",  array( $temp_constituent_table ) ) ) {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => true, 'message' => "Deleted selected constituents and any associated activity records.") );
		} else {
			return array ( 'response_code' => true, 'output' => (object) array ( 'deleted' => false, 'message' => "Database error or constituents changed or deleted by another user.") );
		}
	}

	/*
	*
	* ENTITY DICTIONARY
	*
	*/
	protected static $entity_dictionary = array(

		'address'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'multivalue',
			'field_label' =>  'Address',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'case_assigned'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Staff',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_user_array',),
		'case_review_date'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Review Date',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'case_status'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Status',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'case_status_options',),
		'congressional_district'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Congress',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'consented_to_email_list'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Consent?',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'consented_to_email_list_options',),
		'council_district'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Council',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'councilor_district'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Councilor',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'county'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'County',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'date_of_birth'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Date of Birth',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'duplicate_constituent'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '1',
			'field_type' =>  'selectmenu_constituent',
			'field_label' =>  'Constituent',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  'Search . . .',
			'option_group' =>  '',),
		'email'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'multivalue',
			'field_label' =>  'Email',
			'required' =>  'group',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'employer'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Employer',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'first_name'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'First Name',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'First Name',
			'length' => 50,
			'option_group' =>  '',),
		'gender'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Gender',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'gender_options',),
		'ID'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'integer',
			'field_label' =>  'Internal Id',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_changed'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_deceased'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'checked',
			'field_label' =>  'Deceased?',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_my_constituent'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Mine?',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'is_my_constituent_options',),
		'last_name'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'Last Name',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Last Name',
			'length' => 50,
			'option_group' =>  '',),
		'last_updated_by'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Updated',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet saved',
			'option_group' =>  '',),
		'middle_name'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'Middle Name',
			'required' =>  '',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Middle Name',
			'length' => 50,
			'option_group' =>  '',),
		'no_dupcheck'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '1',
			'field_type' =>  'checked',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'occupation'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Occupation',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'constituent',
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
		'other_district_1'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Other 1',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'other_district_2'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Other 2',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'party'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Reg. Party',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'phone'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'multivalue',
			'field_label' =>  'Phone',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'precinct'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Precinct',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'registration_date'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Reg. Date',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'registration_id'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Reg. ID',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 20,
			'option_group' =>  '',),
		'registration_status'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Reg. Status',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 1,
			'option_group' =>  '',),
		'salutation'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Salutation',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Betsy or Dr. Smith -- for email merge',
			'length' => 50,
			'option_group' =>  '',),
		'state_rep_district'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Rep',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'state_senate_district'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Senate',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'ward'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Ward',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 5,
			'option_group' =>  '',),
		'voter_file_refresh_date'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Refreshed',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Never refreshed',
			'option_group' =>  '',),			
		'year_of_birth'=> array(
			'entity_slug' =>  'constituent',
			'hidden' =>  '0',
			'field_type' =>  'birth_year',
			'field_label' =>  'Year of Birth',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'YYYY',
			'option_group' =>  '',),
	);	
	
	
	
	public static $option_groups = array(
	'case_status_options'=> array(
	  	array('value'=>'0','label'=>'Closed',),
		array('value'=>'1','label'=>'Open',),
		array('value'=>'','label'=>'',)),
	'consented_to_email_list_options' =>  array(
		array('value'=>'Y','label'=>'Subscribed',),
		array('value'=>'N','label'=>'Unsubscribed',),
		array('value'=>'','label'=>'Not Yet',)),
	'gender_options'=> array(
		  array('value'=>'M','label'=>'Male',),
		  array('value'=>'F','label'=>'Female',),
		  array('value'=>'X','label'=>'Non-Binary',),
		  array('value'=>'','label'=>'',)),
	'is_my_constituent_options'=> array(
		  array('value'=>'Y','label'=>'Yes',),
		  array('value'=>'N','label'=>'No',),
		  array('value'=>'','label'=>'',),
		),
	  );
	  
}

