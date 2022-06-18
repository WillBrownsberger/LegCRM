<?php
/*
* class-wic-form-upload-map.php
*
*
*/

class WIC_Form_Upload_Map extends WIC_Form_Upload  {

	protected function format_tab_titles( &$data_array ) {
		return ( WIC_Entity_Upload::format_tab_titles( $data_array['ID']->get_value() ) );	
	}


	// associate form with entity 
	protected function get_the_entity() {
		return ( 'upload' );	
	}

	// no buttons in this form, ajax loader comes up as display: none -- available to display in an appropriate place
	protected function get_the_buttons ( &$data_array ) { 
		$buttons =  '<span id = "ajax-loader">' .
			'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' .
			'"></span>'; 
			// define the top row of buttons (return a row of wic_form_button s)
		$button_args_main = array(
			'name'						=> 'wic-upload-validate-button',
			'id'						=> 'wic-upload-validate-button',
			'type'						=> 'button',
			'button_label'				=> '<span class="button-highlight">Next:  </span>Validate Map',
		);	

		$buttons = $this->create_wic_form_button ( $button_args_main );

		// see upload-match.js for the on click for this button!
		$button_args_main = array(
			'button_class'				=> 'wic-form-button second-position',
			'name'						=> 'wic-upload-express-button',
			'button_label'				=> '<span class="button-highlight">Alt:  </span><em>Run Express!</em>',
			'title'						=> 'Load all records as new without dup checking, validation or setting of default values -- fully reversible; disabled if activities/issues mapped.',
			'type'						=> 'button',
			'id'						=> 'wic-upload-express-button',
		);	
		$buttons .= $this->create_wic_form_button ( $button_args_main );

	
		$button_args_main = array(
			'name'						=> 'wic_back_to_parse_button',
			'id'						=> 'wic_back_to_parse_button',
			'type'						=> 'button',
			'button_class'				=> 'wic-form-button second-position',
			'button_label'				=> 'Back:  Redo Parse',
		);	

		$buttons .= $this->create_wic_form_button ( $button_args_main );
	
	
		return ( $buttons ); 
	}
	
	// define the form message (return a message)
	protected function format_message ( &$data_array, $message ) {
		$formatted_message =  sprintf ( 'Map fields from %s to WP Issues CRM fields. ', $data_array['upload_file']->get_value() )  . $message;
		return ( $formatted_message );
	}

	// legends
	protected function get_the_legends( $sql = '' ) {}
	// group screen
	protected function group_screen( $group ) { 
		return ( 'upload_parameters' == $group->group_slug  || 'mappable' == $group->group_slug ) ;	
	}
	
	// special use existing groups as special within this form
	protected function group_special ( $group ) { 
			return ( 'upload_parameters' == $group || 'mappable' == $group );
	}
	
	// function to be called for special group -- brute force layout of droppables for map
	protected function group_special_upload_parameters ( &$doa ) { 

		$output					 = '';
		$constituent_identifiers = '' ;
		$address_parts 			 = '';
		$address_main			 = '' ;
		$contact_info			 = '' ;			
		$demographic_info  		 = '' ;
		$type_codes				 = '' ;
		$status_info			 = '' ;
		$registration_info		 = '' ;
		$custom_info			 = '' ;
		$activity_info 			 = '' ;
		// get uploadable fields		
		
		$fields_array = $this->uploadable_fields ; 

		// assumes fields by entity 
				
		foreach ( $fields_array as $field ) {
			$show_field = $field['label'] > '' ? $field['label'] : $field['field'];
			// hard-coding better labels for uploading activities
			$relabeling_array = array ( 
				'Issue' => 'Issue Number ( Post ID )',
				'Title' => 'Issue Title ( Post Title )',
				'Text'  => 'Issue Text ( Post Content )'
			);
			$show_field = isset ( $relabeling_array[$show_field] ) ? $relabeling_array[$show_field] : $show_field ;
			
			$unique_identifier = '___' . $field['entity'] . '___' . $field['field']; // three underscore characters before each slug
			$droppable_div = '<div class="wic-droppable" id = "wic-droppable' . $unique_identifier  . '">' 	. $show_field . '</div>';
			if ( $field['order'] < 44 ) {
				$constituent_identifiers .= $droppable_div;	
			} elseif ( $field['order'] < 52 ) {
				$address_parts .= $droppable_div;
			} elseif ( $field['order'] < 90 ) {
				$address_main .= $droppable_div;
			} elseif ( $field['order'] < 110 ) {	
				$contact_info .= $droppable_div;
			} elseif ( $field['order'] < 140 ) {
				$demographic_info .= $droppable_div;			
			} elseif ( $field['order'] < 160 ) {
				$type_codes .= $droppable_div;
			} elseif ( $field['order'] < 200 ) {
				$status_info .= $droppable_div;
			} elseif ( $field['order'] < 400 ) {
				$registration_info .= $droppable_div;
			} elseif ( $field['order'] < 1000 ) { // custom fields will be numbered as 800 + custom field number for upload display
				$custom_info .= $droppable_div;
			} else {
				$activity_info .= $droppable_div;		
			}
		}
		
		// assemble output
		$output .= '<div id = "wic-droppable-column">';
		$output .= '<h3>Drag upload fields into WP Issues CRM fields to map them <a href="http://wp-issues-crm.com/?page_id=213" target = "_blank">(see tips)</a>' . '</h3>';
		$output .= '<div id = "constituent-targets"><h4>' . 'Identity' . '</h4>' . $constituent_identifiers . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "address-targets"><h4>' . 'Address' . '</h4>' . $address_main . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "address-part-targets"><h4>' . 'Street Address parts (if supplied will be combined to form Street Address -- <a href="http://wp-issues-crm.com/?page_id=213" target = "_blank">see tips)</a>' . '</h4>' . $address_parts . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "contact-targets"><h4>' . 'Contact info' . '</h4>' . $contact_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "demo-targets"><h4>' . 'Personal info' . '</h4>' . $demographic_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "type-targets"><h4>' . 'Case status' . '</h4>' . $status_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "case-targets"><h4>' . 'Type codes' . '</h4>' . $type_codes . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= '<div id = "registration-targets"><h4>' . 'Registration codes' . '</h4>' . $registration_info . '</div>';		
		$output .= '<div class = "horbar-clear-fix"></div>';
		if ( $custom_info > '' ) {
			$output .= '<div id = "custom-targets"><h4>' . 'Custom fields' . '</h4>' . $custom_info . '</div>';		
			$output .= '<div class = "horbar-clear-fix"></div>';
		}
		$output .= '<div id = "activity-targets"><h4>' . 'Activity fields' . '</h4>'. $activity_info . '</div>';
		$output .= '</div>';

		$output .= '<div class = "horbar-clear-fix"></div>';
		$output .= $doa['ID']->form_control();
		$output .= $doa['serialized_upload_parameters']->form_control();		


		return $output; 					
	}
	
	protected function group_special_mappable ( &$doa ) {
		
		$output = ''; 
		
				// list fields from upload file to be matched
		$output .= '<div id = "wic-draggable-column-wrapper">';
		$output .= '<h3>Upload fields to map.</h3>';
		$output .= '<div id = "wic-draggable-column">';
		
		// get the column map array				
		$column_map = json_decode ( $doa['serialized_column_map']->get_value() );
 
		// get an array of sample data to use as titles for the column divs
		$upload_parameters = json_decode ( $doa['serialized_upload_parameters']->get_value() );

		$staging_table_name = $upload_parameters->staging_table_name;
		$column_titles_as_samples = WIC_Entity_Upload_Map::get_sample_data ( $staging_table_name ); 

		foreach ( $column_map as $key=>$value ) {
			if ( $key != 'CONSTRUCTED_STREET_ADDRESS' ) {
				$output .= '<div id = "wic-draggable___' . $key . '" class="wic-draggable" title = "' . $column_titles_as_samples[$key] . '">' . $key . '</div>'; // column names are already unique
			}
		}
		$output .= '</div></div>';
		
		// put the upload status in here for reference (could be anywhere)
		$output .= '<div id="initial-upload-status">' . $doa['upload_status']->get_value() . '</div>';
		
		return $output;
		
	}
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {}
	
	public $uploadable_fields = array(
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'ID',
				'type'=>'text',
				'order'=>'10',	
				'label'=>'Internal Id'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'contact',
				'field'=>'first_name',
				'type'=>'autocomplete',
				'order'=>'20',	
				'label'=>'First Name'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'contact',
				'field'=>'middle_name',
				'type'=>'autocomplete',
				'order'=>'30',	
				'label'=>'Middle Name'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'contact',
				'field'=>'last_name',
				'type'=>'autocomplete',
				'order'=>'40',	
				'label'=>'Last Name'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_1',
				'type'=>'text',
				'order'=>'44',	
				'label'=>'Part 1 (no space to part 2)'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_2',
				'type'=>'text',
				'order'=>'45',	
				'label'=>'Part 2'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_3',
				'type'=>'text',
				'order'=>'46',	
				'label'=>'Part 3'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_4',
				'type'=>'text',
				'order'=>'47',	
				'label'=>'Part 4'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_5',
				'type'=>'text',
				'order'=>'48',	
				'label'=>'Part 5'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_6',
				'type'=>'text',
				'order'=>'49',	
				'label'=>'Part 6'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_7',
				'type'=>'text',
				'order'=>'50',	
				'label'=>'Part 7,  Apt. word ( e.g., "Apt" )'),	
		array( 
				'entity'=>'address', 
				'group'=>'',
				'field'=>'address_line_part_8',
				'type'=>'text',
				'order'=>'51',	
				'label'=>'Part 8, Apartment #'),	
		array( 
				'entity'=>'address', 
				'group'=>'address_line_1',
				'field'=>'address_line',
				'type'=>'autocomplete',
				'order'=>'52',	
				'label'=>'Street Address'),	
		array( 
				'entity'=>'address', 
				'group'=>'address_line_2',
				'field'=>'city',
				'type'=>'autocomplete',
				'order'=>'60',	
				'label'=>'City'),	
		array( 
				'entity'=>'address', 
				'group'=>'address_line_2',
				'field'=>'state',
				'type'=>'selectmenu',
				'order'=>'70',	
				'label'=>'State'),	
		array( 
				'entity'=>'address', 
				'group'=>'address_line_2',
				'field'=>'zip',
				'type'=>'autocomplete',
				'order'=>'80',	
				'label'=>'Postal Code'),	
		array( 
				'entity'=>'email', 
				'group'=>'email_row',
				'field'=>'email_address',
				'type'=>'autocomplete',
				'order'=>'90',	
				'label'=>'Email Address'),	
		array( 
				'entity'=>'phone', 
				'group'=>'phone_row',
				'field'=>'phone_number',
				'type'=>'text',
				'order'=>'100',	
				'label'=>'Phone Number'),	
		array( 
				'entity'=>'phone', 
				'group'=>'phone_row',
				'field'=>'extension',
				'type'=>'text',
				'order'=>'105',	
				'label'=>'Phone Extension'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'contact',
				'field'=>'salutation',
				'type'=>'text',
				'order'=>'107',	
				'label'=>'Salutation'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'date_of_birth',
				'type'=>'date',
				'order'=>'110',	
				'label'=>'Date of Birth'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'year_of_birth',
				'type'=>'birth_year',
				'order'=>'111',	
				'label'=>'Year of Birth'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'gender',
				'type'=>'selectmenu',
				'order'=>'120',	
				'label'=>'Gender'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'occupation',
				'type'=>'text',
				'order'=>'121',	
				'label'=>'Occupation'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'employer',
				'type'=>'text',
				'order'=>'122',	
				'label'=>'Employer'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'is_deceased',
				'type'=>'checked',
				'order'=>'130',	
				'label'=>'Deceased?'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'is_my_constituent',
				'type'=>'selectmenu',
				'order'=>'131',	
				'label'=>'Mine?'),
		array( 
				'entity'=>'constituent', 
				'group'=>'personal',
				'field'=>'consented_to_email_list',
				'type'=>'selectmenu',
				'order'=>'132',	
				'label'=>'Consent?'),	
		array( 
				'entity'=>'address', 
				'group'=>'address_line_1',
				'field'=>'address_type',
				'type'=>'selectmenu',
				'order'=>'140',	
				'label'=>'Address Type'),	
		array( 
				'entity'=>'email', 
				'group'=>'email_row',
				'field'=>'email_type',
				'type'=>'selectmenu',
				'order'=>'145',	
				'label'=>'Email Type'),	
		array( 
				'entity'=>'phone', 
				'group'=>'phone_row',
				'field'=>'phone_type',
				'type'=>'selectmenu',
				'order'=>'150',	
				'label'=>'Phone Type'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'case',
				'field'=>'case_status',
				'type'=>'selectmenu',
				'order'=>'160',	
				'label'=>'Status'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'case',
				'field'=>'case_review_date',
				'type'=>'date',
				'order'=>'170',	
				'label'=>'Review Date'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'case',
				'field'=>'case_assigned',
				'type'=>'selectmenu',
				'order'=>'180',	
				'label'=>'Staff'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'registration_id',
				'type'=>'text',
				'order'=>'220',	
				'label'=>'Reg. ID'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'registration_date',
				'type'=>'date',
				'order'=>'240',	
				'label'=>'Reg. Date'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'registration_status',
				'type'=>'text',
				'order'=>'250',	
				'label'=>'Reg. Status'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'party',
				'type'=>'text',
				'order'=>'260',	
				'label'=>'Reg. Party'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'ward',
				'type'=>'text',
				'order'=>'270',	
				'label'=>'Ward'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'precinct',
				'type'=>'text',
				'order'=>'280',	
				'label'=>'Precinct'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'council_district',
				'type'=>'text',
				'order'=>'290',	
				'label'=>'Council'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'state_rep_district',
				'type'=>'text',
				'order'=>'300',	
				'label'=>'Rep'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'state_senate_district',
				'type'=>'text',
				'order'=>'310',	
				'label'=>'Senate'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'congressional_district',
				'type'=>'text',
				'order'=>'320',	
				'label'=>'Congress'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'county',
				'type'=>'text',
				'order'=>'330',	
				'label'=>'County'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'other_district_1',
				'type'=>'text',
				'order'=>'340',	
				'label'=>'Other 1'),	
		array( 
				'entity'=>'constituent', 
				'group'=>'registration',
				'field'=>'other_district_2',
				'type'=>'text',
				'order'=>'350',	
				'label'=>'Other 2'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'activity_type',
				'type'=>'selectmenu',
				'order'=>'1000',	
				'label'=>'Type'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'activity_amount',
				'type'=>'text',
				'order'=>'1002',	
				'label'=>'Amount'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'activity_date',
				'type'=>'date',
				'order'=>'1005',	
				'label'=>'Date'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'activity_note',
				'type'=>'activity_note',
				'order'=>'1010',	
				'label'=>'Note'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'issue',
				'type'=>'selectmenu_issue',
				'order'=>'1020',	
				'label'=>'Issue'),	
		array( 
				'entity'=>'issue', 
				'group'=>'issue_content',
				'field'=>'post_title',
				'type'=>'text',
				'order'=>'1030',	
				'label'=>'Title'),	
		array( 
				'entity'=>'issue', 
				'group'=>'issue_content',
				'field'=>'post_content',
				'type'=>'textarea',
				'order'=>'1040',	
				'label'=>'Text'),	
		array( 
				'entity'=>'activity', 
				'group'=>'activity',
				'field'=>'pro_con',
				'type'=>'selectmenu',
				'order'=>'1070',	
				'label'=>'Pro or Con'),	

	); 	
}