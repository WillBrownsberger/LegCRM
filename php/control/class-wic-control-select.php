<?php
/*
* wic-control-select.php
*
*/
class WIC_Control_Select extends WIC_Control_Parent {

	public function form_control () {
		$final_control_args = get_object_vars ( $this ) ;
		if ( $final_control_args['readonly'] ) {	
			$final_control_args['readonly_update'] = 1 ; // lets control know to only show the already set value if readonly
																		// (readonly control will not show at all on save, so need not cover that case)
		} 
		$final_control_args['option_array'] =  $this->create_options_array ( $final_control_args );
		$control =  $this->create_control( $final_control_args ) ;
		return ( $control );
	}	
	
	public function set_options ( $option_group ) {
		$this->option_group = $option_group;
	}	
	
	protected function create_options_array () {
	
		$entity_class = 'WIC_Entity_' . $this->entity_slug;
		// if available, take from control arguments which may be  modified by set options
		$getter = $this->option_group; 
		// look for option array in a sequence of possible sources
		// look first for getter as entity $option_groups
		if( !$option_array = $entity_class::get_option_group( $getter ) ) {
			if ( method_exists ( $entity_class, $getter ) ) { 
				// look second for getter as a static function built in to the current entity
				$option_array = $entity_class::$getter ( $this->value );
				// note: including the value parameter to allow the getter to inject the value into the array if needed			
			} elseif ( function_exists ( $getter ) ) {
				// look finally for getter as a function in the global name space
				$option_array = $getter( $this->value );			
			} else {
				$option_array = array( array ( 
					'value' => '',				
					'label' => 'No options defined or field pointed to non-existent or disabled option group.',
					)
				);
			}
		}
		if ( isset ( $readonly_update ) ) { 
			// if readonly on update, extract just the already set option if a readonly field, but in update mode 
			// (if were to show as a readonly text, would lose the variable for later use)
			$option_array = array( array ( 
				'value' => $value,				
				'label' => value_label_lookup ( $value,  $option_array ),
				)
			);
		} 	
		return ( $option_array );	
	}	
	
	public static function create_control ( $control_args ) { 

		extract ( $control_args, EXTR_SKIP ); 

		$control = '';
		
		// $hidden_class = 1 == $hidden ? 'hidden-template' : '';
		$hidden = 1 == $hidden ? "hidden" : '';		
		
		$control = ( $field_label > '' ) ? '<label ' . $hidden . ' class="' . $label_class . ' ' .  safe_html( $field_slug_css ) . '" for="' . safe_html( $field_slug ) . '">' . 
				safe_html( $field_label ) . '</label>' : '';
		$control .= '<select  ' . $hidden . ' class="' . safe_html( $input_class ) . ' '  .  safe_html( $field_slug_css ) .'" id="' . safe_html( $field_slug ) . '" name="' . safe_html( $field_slug ) 
				. '" >' ;
		$p = '';
		$r = '';
		foreach ( $option_array as $option ) {
			$label = $option['label'];
			if ( $value == $option['value'] ) { // Make selected first in list
				$p = '<option selected="selected" value="' . safe_html( $option['value'] ) . '">' . safe_html ( $label ) . '</option>';
			} else { // in this not selected branch, do not include system reserved values
				if ( isset ( $option['is_system_reserved'] ) ) {  
					if ( 1 == $option['is_system_reserved'] ) {
						continue;
					}
				}
				$r .= '<option value="' . safe_html( $option['value'] ) . '">' . safe_html( $label ) . '</option>';
			}
		}
		$control .=	$p . $r .	'</select>';
		return ( $control );
	
	}
	/*************************
	* HARD VALIDATE RETURNING VALUES AGAINST OPTION LIST
	*
	* SANITIZE AWAY INVALID VALUES
	*
	*/
	public function sanitize() {
		global $current_user;  
		if ( ! in_array( $this->value, $this->valid_values())) {
			$log_value = $this->value;
			$this->value = 0; // use zero in case underlying field is numeric
		}
	}


	// public for use in upload
	public function valid_values() {
		$options_array = $this->create_options_array( );
		$valid_values = array();
		foreach ( $options_array as $option ) {
			$valid_values[] = $option['value'];		
		}
		return ( $valid_values );
	}	
		
}


