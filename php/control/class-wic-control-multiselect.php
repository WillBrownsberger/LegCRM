<?php
/*
* wic-control-multiselect.php
*
*/
class WIC_Control_Multiselect extends WIC_Control_Select {
	
	public function reset_value() {
		$this->value = array();	
	}
	
	public static function create_control ( $control_args ) {
		
		// expects multivalue $value in form of array with $value1 => 1, $value2 => 1 . . . as will come back from form

		extract ( $control_args, EXTR_OVERWRITE ); 

		$control = ( $field_label > '' ) ? '<label class="' . $label_class . '" for="' . safe_html( $field_slug ) . '">' . safe_html( $field_label ) . '</label>' : '' ;

		$control .= '<div class = "wic_multi_select" id="' . $field_slug . '">';
		$unselected = '';
		
		foreach ( $option_array as $option ) {
			if ( ! ( '' === $option['value'] ) ) { // discard the blank option embedded for the select control 
				$args = array(
					'field_slug' 			=> $field_slug . '[' . $option['value'] . ']',
					'field_label'			=>	$option['label'],
					'field_slug_css'		=> $field_slug_css,
					'label_class'			=> 'wic-multi-select-label '  . ( isset ( $option ['class'] ) ? $option ['class'] : '' ),
					'input_class'			=> 'wic-multi-select-checkbox ', 
					'value'					=> isset ( $value[$option['value']] ), 	
					'readonly'				=>	false,
				);	
				if ( isset ( $value[$option['value']] ) ) {
					$control .= '<p class = "wic_multi_select_item" >' . WIC_Control_Checked::create_control($args) . '</p>';
				} else {
					$unselected .= '<p class = "wic_multi_select_item" >' . WIC_Control_Checked::create_control($args) . '</p>';				
				}
			}
		}
		$control .= $unselected . '</div>';
		return ( $control );
	
	}	
	
	public function sanitize () {
		foreach ( $this->value as $key => $value ) {
			if ( !is_int( $value ) ) {
				Throw new Exception( sprintf ( 'Invalid value for multiselect field %s', $this->field_slug ));
			}	
		}			
	}
}


