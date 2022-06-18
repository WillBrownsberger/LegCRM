<?php
/*
*
*	wic-entity-advanced-search-row.php
*
*/



class WIC_Entity_Advanced_Search_Row extends WIC_Entity_Multivalue {

	// always further overriden by child class
	protected function set_entity_parms( $args ) {
		extract ( $args );
		$this->entity = 'advanced_search_row';
		$this->entity_instance = $instance;
	} 

	// put the row together -- written to accomodate use in class extensions
	// note that this includes setting default values (add set default value for constituent_having_aggregator in 4.3.2.3)
	protected function do_field_interaction_rules(){
			
		$entity_base = substr ( $this->entity, 16 ); // 'constituent' -- 'activity' or 'constituent_having'  in extended use of this method
		$field = $this->data_object_array[$entity_base . '_field']->get_value();

		// if constituent_having, set default constituent_having_aggregator to 'MAX'
		if ( isset ( $this->data_object_array['constituent_having_aggregator'] ) && ! $this->data_object_array['constituent_having_aggregator']->get_value() ) {
			$this->data_object_array['constituent_having_aggregator']->set_value( 'MAX' );
		}

		// if don't have a field value, set it to the first option
		if ( '' == $field ) {
			$field_options = static::{ $entity_base . '_fields' }();
			$field  = $field_options[0]['value'];
			$this->data_object_array[$entity_base . '_field']->set_value( $field );
		}
		// set parameters from field
		$field_data = WIC_Entity_Advanced_Search::get_field_rules_by_id( $field );

		// slot for constituent type logic
		$this->type_interaction ( $field_data['entity_slug'] );
		
		// set aggregator test value outside conditions for later use
		$aggregatorIsCount =  isset ( $this->data_object_array['constituent_having_aggregator'] ) ? ( 'COUNT' == $this->data_object_array['constituent_having_aggregator']->get_value() ) : false;

		// if choosing a type field, comparison and value are irrelevant/redundant, so hide them (will not be needed before a field change and row recreation); 
		if ( in_array ( $field_data['field_slug'], array (  "activity_type", "address_type", "email_type", "phone_type" ) ) ) {
			$this->data_object_array[$entity_base . '_comparison']->set_input_class_to_hide_element();
			$this->data_object_array[$entity_base . '_value']->set_input_class_to_hide_element();
		// if anything other than entity-type, must set up comparison and value fields
		} else { 
			// swap in the correct control for _value field (preserving value, which may be unset)
			$save_value = $this->data_object_array[$entity_base . '_value']->get_value();
			$this->data_object_array[$entity_base . '_value'] = WIC_Entity_Advanced_Search::make_blank_control_object(
				$field,
				(object) array (
					'comparison' => 	$this->data_object_array[$entity_base . '_comparison']->get_value(),
					'entity'	 =>		$this->entity,
					'field_slug' =>		$entity_base . '_value',
					'instance'	 =>		$this->entity_instance,
					'aggregatorIsCount' => $aggregatorIsCount,
				)
			);
			$this->data_object_array[$entity_base . '_value']->set_value( $save_value );

			// hide value field if not needed for known comparison
			if ( in_array ( $this->data_object_array[$entity_base . '_comparison']->get_value(), array ( 'BLANK', 'IS_NULL', 'NOT_BLANK' ) ) ){
				$this->data_object_array[$entity_base . '_value']->set_input_class_to_hide_element();
			}
				
			// finally, set up comparison -- 
			if ( 'checked' == $field_data['field_type'] ) { // hide if a check substitute or define options (will not be needed before field change)
				$this->data_object_array[$entity_base . '_comparison']->set_input_class_to_hide_element();
			} else { 
				// define option set
				$quantitative_compare_fields = array ( "activity_amount", "activity_date", "last_updated_time", "date_of_birth" ); 
				$full_text_fields = array( 'post_title', 'post_content', 'activity_note');
				if ( 'ID' == $field_data['field_slug'] ) { // only now permit equality for this field ()
					$option_group = 'advanced_search_id_comparisons';
				} elseif ( in_array ( $field_data['field_slug'], $quantitative_compare_fields ) ) {
					$option_group = 'advanced_search_quantitative_comparisons';
				} elseif ( in_array ( $field_data['field_slug'], $full_text_fields ) ) {
					$option_group = 'advanced_search_fulltext_comparisons';					
				} elseif ( 'selectmenu' == $field_data['field_type'] ) {  
					$option_group = 'advanced_search_select_comparisons';
				} else {  
					$option_group = 'advanced_search_general_comparisons';
				}
				// point comparison field to option set
				$this->data_object_array[$entity_base . '_comparison']->set_options ( $option_group  ); // note -- this does not change options for constituent_having -- all are quantitative
				// set_value to first if don't have it
				if ( '' ==  $this->data_object_array[$entity_base . '_comparison']->get_value() ) {
					$class = 'WIC_Entity_Advanced_Search_' . $entity_base;
					$field_options = $class::get_option_group( $option_group );
					$this->data_object_array[$entity_base . '_comparison']->set_value ( $field_options[0]['value'] );
				} // blank comparison value
			} // not a checked field
		} // not a type field
		$this->manage_count_field ( $aggregatorIsCount );
	}

	// slot for constituent child class
	protected function type_interaction( $field_entity ){}
	
	// slot for having child class
	protected function manage_count_field ( $aggregatorIsCount ) {} 
}