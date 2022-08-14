<?php
/*
* wic-control-multivalue.php
*
* NOTE: CANNOT HAVE MULTIVALUE WITH IN MULTIVALUE -- SET VALUE FOR MULTIVALUE BYPASSES ARRAYS
*
*/
class WIC_Control_Multivalue extends WIC_Control_Parent {
	
	/*************************************************************************************
	*
	*	Multi value control needs special methods for initialization and population, 
	*		since its value is not a scalar but an array of objects.
	*	
	*
	**************************************************************************************/

	public function reset_value() {  
		$this->value = array();
	}		
	

	/*
	* In WIC_Control_Parent, set_value just passes the value through, but in this multivalue context have to create the whole array of objects
	* 	 based on the array of post values -- set value is called by populate_data_object_array_from_submitted_form()
	*   in WIC_Parent_Entity.  At that stage, the multivalue control has been created and initiated with an empty array.
	*	 Need to fill that array with objects by parsing the form
	*
	*	If the form includes deleted rows, get rid of them at this stage: Discard if not from db or do the delete.  
	*
	*/
	public function set_value ( $value ) { // value is an array created by multi-value field coming back from $_Post

		$this->value = array();
		$class_name = 'WIC_Entity_' . $this->field_slug;
		$instance_counter = 0;
		foreach ( $value as $key=>$form_row_array ) {
			$args = array (
				'instance' => strval( $instance_counter ), // note that strval is especially critical in 8.0 to avoid loose equality of 0 to empty string
				'form_row_array' => $form_row_array, // have to pass whole row, since can't assume $_POST numbering is the same							
			);
			if ( strval($key) != 'row-template' ) { // skip the template row created by all multivalue fields
				if ( isset ( $form_row_array['screen_deleted'] ) && $form_row_array['screen_deleted'] > 0 ) { // in ajax posts, including check boxes as 0 vals
					// delete screen deleted items if they came from db, otherwise, they only existed on screen, so do nothing
					if ( isset ( $form_row_array['ID'] ) ) { // no ID's in multivalue control for advanced search			
						if ( $form_row_array['ID'] > 0 ) {
							$wic_access_object = WIC_DB_Access_Factory::make_a_db_access_object( $this->field_slug );
							$wic_access_object->delete_by_id( $form_row_array['ID'] ); 
						}
					}
				} else { // not deleted rows -- may be blank
					// need to test whether row coming back has anything in it.
					$values_set = false;
					// test each value in the row
					foreach ( $form_row_array as $column_value ){ // corrected iterator name from $value to $column_value (was harmlessly overwriting the function input $value)
						if ( is_array ( $column_value ) ) { // if value is array, need to see if array has anything in it ( blank array is not an empty string )
							if ( count ( $column_value  ) > 0 ) {  // theoretically?, it could be a zero length array, in which case, ignore 
								foreach ( $column_value as $array_item ) { // could definitely have blank values, so need to see at least one
									if ( '' < $array_item ) {
										$values_set = true;			
									}								
								}							
							}							
						} else { // if value is scalar, then nonblank is enough
							if ( '' < $column_value ) {
								$values_set = true;							
							}						
						} 
					}
					if ( $values_set ) {	
						$this->value[$instance_counter] = new $class_name( 'populate_from_form', $args );
						$instance_counter++;
					}
				}
			}
		}
	}

	/*
	*	Called in lieu of set_value when value is in the form a query return which does not include a reference to 
	*    the summary name of the multivalue control -- e.g., it will have ID, constituent ID, email_address, 
	*    but not the column named email.  Also, it may have multiple rows per email if, for example, there are also multiple phones
	*    So, when retrieved a top level entity like constituent, will work from the ID of that constituent to construct the possible array
	*    of emails, phones or other multivalues for that entity.
	*/
	public function set_value_by_parent_pointer ( $pointer ) { // pointer is the ID of the top-level entity -- constituent
		$wic_query = WIC_DB_Access_Factory::make_a_db_access_object( $this->field_slug );
		$search_parameters = array(  
			'select_mode' => '*',
			'sort_order' => true,
			'retrieve_limit' => 999999999, // show all
			); 
		$wic_query->search ( 		
			array ( // double layer array to standardize a return that allows multivalue fields
					array (
						'table'	=> $this->field_slug,
						'key' 	=> $this->entity_slug . '_id',
						'value'	=> $pointer,
						'compare'=> '=',
					)
				),   // get child records
				$search_parameters
			);

		$this->value = array();
		$class_name = 'WIC_Entity_' . $this->field_slug;
		$instance_counter = 0;
		foreach ( $wic_query->result as $form_row_object ) {
			$args = array (
				'instance' => strval( $instance_counter ),
				'form_row_object' => $form_row_object, // have to pass whole row, since can't assume $_POST numbering is the same							
			);
			$this->value[$instance_counter] = new $class_name( 'populate_from_object', $args );
			$instance_counter++;
		}
	}

	/*
	*  Multivalue control passes requests to its components instead of actually doing the action as scalar controls do.
	*  Depends on the components reporting back to it, so it can report back as if it were a scalar control
	*
	*/

	// sanitize -- each row object has its own sanitize function, so this is easy
	public function sanitize() {
		foreach ( $this->value as $row_object ) {
			$row_object->sanitize_values();		
		}	
	}

	// validate  each row object has its own validation function with return, so this is easy
	public function validate() { 
		$error_message = '';
		foreach ( $this->value as $row_object ) { 
			$error_message .= $row_object->validate_values();
		}
		// treat required checks for sub rows of entity as a validation issue -- 
		// should always be done even if row not required -- otherwise, end up with garbage rows.
		$required_notice = '';		
		foreach ( $this->value as $row_object ) {	
			$required_notice .= $row_object->required_check (); 
		} 
		if ( $required_notice > '' ) {
			$error_message .= sprintf ( ' %s row has missing elements: ', $this->field_label ) . $required_notice  ; 		
		}
		return ( $error_message );	
	}

	// report whether control value is present -- i.e., as at least one valid row
	public function is_present() {
	/********************************************************************************
	* if at least one row of multi-value passes its own set of required checks 
	* for example, -- to require one email address for a constituent
	* (a) set value of email group as required and (b) define email address as required 
	* -- doing only the second step will serve to prevent population of db with blank addresses,
	* but will not force an email for each constituent.
	*********************************************************************************/
		$is_present = false;		
		if ( count ( $this->value ) > 0  ) {
			foreach ( $this->value as $row_object ) {	
				$error_message = $row_object->required_check ();
				if ( '' == $error_message ) {
					$is_present = true;
					break;				
				} 
			}			
		}
		return ( $is_present ); // true or false		
	}

	//report whether field fails individual requirement -- not a case that exists in 3.0
	public function required_check() {
		if ( "individual" == $this->required ) {
			if ( ! $this->is_present() ) { 
				return sprintf ( ' %s is a required field group. ', $this->field_label ) ;		
			}
		} else {
			return '';	// if has non-empty value, then fails check -- consistent with scalar, but here compiled across rows. 	
		}	
	}

	// reset changed flags for all rows
	public function reset_changed_flags() {
		foreach  ( $this->value as $row_object ) {
			$row_object->reset_changed_flags();  // calls the reset function in the parent class (of which the rows are instances);
		}
	}

	/*************************************************************************************
	*
	*	Multi value controls have to generate a control set for each row. 
	*	
	*
	**************************************************************************************/
	public function form_control () {

	
		$final_control_args = get_object_vars ( $this ) ;
		extract ( $final_control_args );
		 
		$control_set = ( $field_label > '' && ! ( 1 == $hidden ) ) ? '<label class="' . safe_html ( $label_class ) .
				 ' ' . safe_html( $field_slug_css ) . '" for="' . safe_html( $field_slug ) . '">' . safe_html( $field_label ) . '</label>' : '' ;
		// create division opening tag 		
		$control_set .= '<div id = "' . $this->field_slug . '-control-set' . '" class = "wic-multivalue-control-set">';
		$control_set .= $this->create_add_button ( $this->field_slug, sprintf ( 'Add %s ', $this->field_label )  ) ;
		// create a hidden template row for adding rows in wic-utilities.js through moreFields() 
		// moreFields will replace the string 'row-template' with row-counter index value after creating the new row
		
		$class_name = 'WIC_Entity_' . $this->field_slug; 
		$args = array(
			'instance' => 'row-template'		
			);
		$template = new $class_name( 'initialize', $args );
		// always initialize a save_row for the template, because will be saving that row new regardless of
		// whether main update is a save or an update
		$control_set .= $template->row_form();

		// now proceed to add rows for any existing records from database or previous form
		// this looks like it could be wrong if there were a difference between save and update -- i.e., had readonly fields in subrow
		// each row in $this->value is an entity object
		if ( count ( $this->value ) > 0 ) {
			foreach ( $this->value as $value_row ) {
				$control_set .= $value_row->row_form();
			}
		}		

		$control_set .= '<div class = "hidden-template" id = "' . $this->field_slug . '-row-counter">' . count( $this->value ) . '</div>';		

		$control_set .= '</div>';

		return ($control_set);	
	}
	
	// the function called by this button will create a new instance of the templated base paragraph (repeater row) 
	// and insert it above related counter in the DOM
	private function create_add_button ( $base, $button_label ) {
		$button =  
			'<button ' . 
			' class = "row-add-button" ' .
			' id = "' . safe_html( $base ) . '-add-button" ' .
			' title ="' . safe_html(  $button_label ) . '"' .
			' type = "button" ' .
			' ><span class="dashicons dashicons-plus-alt"></span></button>'; 

		return ( $button );
	}

	/*************************************************************************************
	*
	*  DB ACTION REQUEST HANDLERS: 
	*
	**************************************************************************************/

	/* 
	*	for search, control is compiling and passing values only from FIRST row upwards to parent entity
	*	ONLY used for dup checking since no simple form search, only advanced, and elsewhere handling the multivalue entities individually (e.g. upload-complete.php)
	*
	*/
	public function create_search_clause () {
		if ( count ( $this->value ) > 0 ) {
			// reset returns pointer to first element
			$query_clause = reset( $this->value )->assemble_meta_query_array( true ); // dupcheck is true always for multivalue
			return ( $query_clause );
		} else {
			return ( '' );		
		} 	
	}
	
	// in advanced search, actually want search from each row (in dup_check search, only taking first)	
	public function create_advanced_search_clause () {
		if ( count ( $this->value ) > 0 ) {
			$query_clause = array();
			foreach ( $this->value as $row ) {
				$query_clause = array_merge( $query_clause, array( array( 'row', $row->assemble_meta_query_array( false ) ) ) ); // false means not doing a dup_check
			}
			return ( $query_clause );
		} else {
			return ( '' );		
		} 	
	}	
	
	// for update control is passing request downwards to the rows and asking them to do the updates	
	public function do_save_updates ( $id  ) {
		$errors = '';
		foreach ( $this->value as $child_entity ) {
			$errors .= $child_entity->do_save_update ( $this->entity_slug, $id );
			// if any child row has changes, mark this control has having changes
		}
		return $errors;
	}

}	
