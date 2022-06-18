<?php
/*
* class-wic-control-textarea.php
*
*
*/

class WIC_Control_Textarea extends WIC_Control_Parent {

	public static function create_control ( $control_args ) {
		
		extract ( $control_args, EXTR_SKIP ); 
	
		$readonly = $readonly ? ' readonly ' : '';
		$hidden	 = $hidden ? 'hidden' : ''; 
		 
		$control = ( $field_label > '' ) ? '<label class="' . $label_class . ' ' . safe_html( $field_slug_css ) . '" ' .
			 'for="' . safe_html( $field_slug ) . '">' . safe_html( $field_label ) . '</label>' : '' ;
		$control .= '<textarea ' .  $hidden . ' class="' . $input_class . ' ' .  safe_html( $field_slug_css ) . '" id="' . safe_html( $field_slug ) . '" name="' . safe_html( $field_slug ) . '" type="text" placeholder = "' . 
			safe_html( $placeholder ) . '" ' . $readonly  . '>' . safe_html( $value ) . '</textarea>';
			
		return ( $control );

	}	

	// text area cannot be sanitized with sanitize_text -- loses formatting
	// kses_post strips scripts; preg_replace strips high plane not usable utf8
	public function sanitize () { 
		$this->value = utf8_string_no_tags(  preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD",  $this->value ) );
	}	
	
}

