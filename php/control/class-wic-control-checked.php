<?php
/*
* wic-control-checked.php
*
*/
class WIC_Control_Checked extends WIC_Control_Parent {
	
	public static function create_control ( $control_args ) {
		
		$input_class = 'wic-input-checked';

		extract ( $control_args, EXTR_SKIP ); 

		$readonly = $readonly ? ' disabled="disabled" ' : '';

		// allow extensions to set field type, but if hidden, is hidden		
		$hidden_class = isset ( $hidden ) ? (  ( 1 == $hidden ) ? ' hidden-element ' : '' ) : '' ;
				 
		$control =  ( $field_label > '' && ! ( 'hidden-element' == $hidden_class ) ) ? '<label class="' . $label_class .  ' ' . safe_html( $field_slug_css ) . '" for="' . 
				safe_html( $field_slug ) . '">' . safe_html( $field_label ) . ' ' . '</label>' : '';
		$control .= '<input class="' . $input_class . $hidden_class . '"  id="' . safe_html( $field_slug ) . '" name="' . safe_html( $field_slug ) . 
			'" type="checkbox"  value="1"' . checked( $value, 1, false) . $readonly  .'/>';	

		return ( $control );

	}	

	// note that setting this to zero causes the checked value to be included in search clauses with value 0
	// currently this behavior supports use of the flag for "is deceased"
	public function reset_value() {
		$this->value = 0;	
	}

	// 
	public function validate() { 
		$validation_error = '';
		$class_name = 'WIC_Entity_' . $this->entity_slug;
		$validator = $this->field_slug_base . '_validator';
		if ( method_exists ( $class_name, $validator ) ) { 
			$validation_error = $class_name::$validator ( $this->value );
		} else { 
			if ( '0' != $this->value && '1' != $this->value ) {
				$validation_error = 'Invalid value for checked field.';
			}		
		}
		return $validation_error;
	}


}	
