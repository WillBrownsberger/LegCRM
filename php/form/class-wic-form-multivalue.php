<?php
/*
*
*  class-wic-form-multivalue.php
*
*	this form is for an instance of a multivalue field -- a row (or rows, if multiple groups) of controls, not a full form
*	  
*	entity in this context is the entity that the multivalue field may contain several instances of 
*   -- this form generator doesn't need to know the instance value; 
* 	 -- the control objects within each row know which row they are implementing
*
*/

class WIC_Form_Multivalue extends WIC_Form_Parent  {


	// no header tabs
	
	
	protected $entity = '';
	protected $entity_instance='';
	
	public function __construct ( $entity, $instance ) {
		$this->entity = $entity;
		$this->entity_instance = $instance;
	}

	// multivalue form can carry multiple entities	
	protected function get_the_entity() {
		return ( $this->entity );	
	}
		
	protected function get_the_legends( $sql = '' ) {}	
	
	public function layout_form ( &$data_array, $message, $message_level, $sql = '' ) { 
	
		

		$groups =  $this->get_the_groups($this->get_the_entity());
		$class = ( 'row-template' == $this->entity_instance ) ? 'hidden-template' : 'visible-templated-row';
		$update_row = '<div class = "'. $class . '" id="' . $this->entity . '[' . $this->entity_instance . ']">';
		$update_row .= '<div class="wic-multivalue-block ' . $this->entity . '">';
			foreach ( $groups as $group ) { 
				$update_row .= '<div class = "wic-multivalue-field-subgroup wic-field-subgroup-' . safe_html( $group->group_slug ) . '">';
				$update_row .= $this->the_controls ( $group->fields, $data_array );
				$update_row .= '</div>';
			} 
		$update_row .= '</div></div>';
		return $update_row;
	}
	
	protected function the_controls ( $fields, &$data_array ) {
		$controls = '';
		foreach ( $fields as $field ) {
			$controls .= $this->get_the_formatted_control ( $data_array[$field] );
		}
		return $controls;
	}
	
	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_buttons( &$data_array ){}
	protected function format_message ( &$data_array, $message ) {}	
	protected function group_special( $group ) {}
	protected function group_screen ( $group ) {}
	protected function pre_button_messaging ( &$data_array ){}
    protected function post_form_hook ( &$data_array ) {} 
}