<?php
/*
*
*	wic-entity-multivalue.php
*
*/



abstract class WIC_Entity_Multivalue extends WIC_Entity_Parent {

	protected function initialize() {
		
		$this->initialize_data_object_array();
		$this->do_field_interaction_rules();
	}

	// used in populate_from_form so that do interaction after populate
	protected function initialize_no_interact() {
		
		$this->initialize_data_object_array();	
	}

	
	// note also used in population basic set_value from multivalue
	protected function populate_from_form( $args ) { 
		extract( $args );
		// expects form_row_array among args; 
		// instance also present, but has already been processed in __construct
		// here, just getting values from form array 
		// note that row numbering may not synch between $_POST and the multivalue array 
		$this->initialize_no_interact();
		foreach ($this->fields as $field ) {
			if ( isset ( $form_row_array[$field->field_slug] ) ) {
				$this->data_object_array[$field->field_slug]->set_value( $form_row_array[$field->field_slug] );
			}
		}
		$this->do_field_interaction_rules();
	}

	// slot used to implement field interaction rules (for example, advanced_search_constituent) -- fires on initialization or after populate
	protected function do_field_interaction_rules(){}


	protected function populate_from_object( $args ) {
		extract( $args );
		$this->initialize();
		foreach ( $this->fields as $field ) {
			if ( ! $this->data_object_array[$field->field_slug]->is_transient() ) {
				$this->data_object_array[$field->field_slug]->set_value( $form_row_object->{$field->field_slug} );
			}
		}
	}

	public function row_form() {
		$row_form_object = new WIC_Form_Multivalue ( $this->entity, $this->entity_instance );
		$row_form = $row_form_object->layout_form( $this->data_object_array, null, null );
		return $row_form;
	}

	public function do_save_update( $parent_slug, $id ) {
		$parent_link_field = $parent_slug . '_' . 'id';
		$this->data_object_array[$parent_link_field]->set_value( $id );
		$wic_access_object = WIC_DB_Access_Factory::make_a_db_access_object( $this->entity );
		$wic_access_object->save_update( $this->data_object_array ); 
		if ( false === $wic_access_object->outcome ) {
			$error =  $wic_access_object->explanation . ' -- is there a duplicate key in multivalue row? ';
		} else {
			$error = '';
			// line below updated to add 0 test because 0 != '' in PHP 8.0 https://www.php.net/manual/en/migration80.incompatible.php
			if ( '' == $this->data_object_array['ID']->get_value() || 0 == $this->data_object_array['ID']->get_value() ) { // then just did a save, so . . .
				$this->data_object_array['ID']->set_value( $wic_access_object->insert_id );
			}
		}		
		return ( $error );
	}
}