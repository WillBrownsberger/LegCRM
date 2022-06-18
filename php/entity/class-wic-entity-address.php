<?php
/*
*
*	wic-entity-address.php
*
*/



class WIC_Entity_Address extends WIC_Entity_Multivalue {

	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'address';
		$this->entity_instance = $instance;
	} 

	public static function zip_validator ( $zip ) { 
		if ( '' < $zip ) {
			if ( ! preg_match ( "/^\d{5}([\-]?\d{4})?$/i", $zip ) ) {
				return ( 'Invalid USPS Zip Code supplied.' ); 			
			}
		}	
		return ( '' );
	}


	public function row_form() {
	
		$address_line_composed = $this->data_object_array['address_line']->get_value() . ' -- ' . $this->data_object_array['city']->get_value() ; 
	
		// include send email button 
		$button_args_main = array(
			'button_label'				=> '<span class="dashicons dashicons-location-alt"></span>',
			'type'						=> 'button',
			'id'						=> '',			
			'name'						=> '',
			'title'						=> 'Map Address',
			'button_class'				=> 'wic-form-button map-individual-address-button',
			'value'						=> 'show_point,' . $this->get_lat() . ',' . $this->get_lon() . ',' . $address_line_composed,
		);	
		
		
		
		$message = WIC_Form_Parent::create_wic_form_button ( $button_args_main );
		$new_update_row_object = new WIC_Form_Address ( $this->entity, $this->entity_instance );
		$new_update_row = $new_update_row_object->layout_form( $this->data_object_array, $message, 'address_line_2' );
		return $new_update_row;
	}

	public function get_lat() {
		return ( $this->data_object_array['lat']->get_value() );
	}

	public function get_lon() {
		return ( $this->data_object_array['lon']->get_value() );
	}

	protected static $entity_dictionary = array(
		'address_line'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'Street Address',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '123R Main St Apt 1',
			'length' => 100,
			'option_group' =>  '',),
		'address_line_part_1'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 1 (no space to part 2)',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_2'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 2',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_3'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 3',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_4'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 4',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_5'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 5',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_6'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 6',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_7'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 7, Apt. word ( e.g., \"Apt\" )',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'address_line_part_8'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  'Part 8, Apartment #',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '1A',
			'length' => 50,
			'option_group' =>  '',),
		'address_type'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Address Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Type',
			'length' => 30,
			'option_group' =>  'address_type_options',),
		'city'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'City',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'City',
			'length' => 50,
			'option_group' =>  '',),
		'constituent_id'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Constituent ID for Address',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'ID'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Internal ID for Address',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'is_changed'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'length' => 1,
			'option_group' =>  '',),
		'last_updated_by'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Address Updated By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'constituent_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Address Updated Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'lat'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'float_geo', // always saves as zero to force batch check lat lon
			'field_label' =>  'Lat',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'lon'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '1',
			'field_type' =>  'float_geo', // always saves as zero to force batch check lat lon
			'field_label' =>  'Lon',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'address',
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
		'screen_deleted'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'deleted',
			'field_label' =>  'x',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'state'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'State',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'State',
			'length' => 2,
			'option_group' =>  'state_options',),
		'zip'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'Postal Code',
			'required' =>  'group',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Postal Code',
			'length' => 10,
			'option_group' =>  '',),	
	);


	public static $option_groups = array(
		'address_type_options'=> array(
		  	  array('value'=>'','label'=>'Type?',),
			  array('value'=>'0','label'=>'Home',),
			  array('value'=>'1','label'=>'Work',),
			  array('value'=>'2','label'=>'Mail',),
			  array('value'=>'3','label'=>'Other',),
			  array('value'=>'wic_reserved_4','label'=>'Registered',),
			  array('value'=>'incoming_email_parsed','label'=>'Parsed from Email',)),
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
	   
	  );

}