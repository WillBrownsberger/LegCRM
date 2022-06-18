<?php
/*
* wic-control-select.php
*
*/
class WIC_Control_Selectmenu extends WIC_Control_Select {

	const BLANK_REPLACEMENT_STRING = '--';
	
	public function form_control () {
		$final_control_args = get_object_vars ( $this ) ;
		$final_control_args['value'] = $this->value;
		if ( $final_control_args['readonly'] ) {	
			$final_control_args['readonly_update'] = 1 ; // lets control know to only show the already set value if readonly
			// (readonly control will not show at all on save, so need not cover that case)
		} 
		$final_control_args['option_array'] =  $this->create_options_array ( $final_control_args );
		$control =  $this->create_control( $final_control_args ) ;
		return ( $control );
	}	
	

	
	public static function create_control ( $control_args ) { 

		extract ( $control_args, EXTR_SKIP ); 

		$control = '';
		
		// note -- this control type does not support the hidden or placeholder dictionary settings 	
		
		$control = ( $field_label > '' ) ? '<label class="' . $label_class . ' ' .  safe_html( $field_slug_css ) . '" for="' . safe_html( $field_slug ) . '">' . 
				safe_html( $field_label ) . '</label>' : '';
		/* 
		* field components are:
		*	a div wrapper for the whole unit -- .wic-selectmenu-wrapper
		*		a div wrapper for the input unit -- .wic-selectmenu-input-wrapper
		*			the hidden actual value field which gets processed on submit -- .wic-selectmenu-input
		*			the display input field which receives keyboard input when hidden -- .wic-selectmenu-input-display
		*			the drop down icon borrowed from jquery ui stylesheet 
		*		the high z-index div -- .wic-selectmenu-options-layer
		*			the drop down values as a ul -- .wic-selectmenu-dropdown-values
		*				wic-selectmenu-list-entry
		*					wic-selectmenu-list-entry-label
		*					wic-selectmenu-list-entry-value
		*			wrapper for additional drop down values (etc.) .wic-selectmenu-additional-down-values
		*
		*/	
		// generate the li entries putting selected value first (will be over the input when the list is surfaced)
		// repeat in list so list is intact and don't need to resort it
		// 
		$label = '';
		$p = '';
		$r = '';
		foreach ( $option_array as $option ) {
			if ( $value == $option['value'] ) { // Make selected first in list
				$p = self::format_list_entry( $option );
				$label = $option['label'];
			} else { // in this not selected branch, do not include system reserved values
				if ( isset ( $option['is_system_reserved'] ) ) {  
					if ( 1 == $option['is_system_reserved'] &&  $value != $option['value']) {
						continue;
					}
				}
				$r .= self::format_list_entry( $option );
			}
		}
		
		// note that the value of the wic-selectmenu-input-display element is not accessed through $_POST
		// . . . also never accessed by name or id in js, but good practice to preserve name/id uniqueness and must replace array brackets to avoid overlaying the true variable in $_POST
		$control .= '<div class="wic-selectmenu-wrapper ' . safe_html( $input_class ) . ' ' . safe_html( $field_slug_css ) . '">' .
						'<div class="wic-selectmenu-input-wrapper">' .
							'<input 							
								class="wic-selectmenu-input" 
								id="' . safe_html( $field_slug )  . '" 
								name="' . safe_html( $field_slug ) . '" 
								hidden 
								type="text" 
								tabindex = "-1"
								value="' . safe_html ( $value ) . 
							'" />' .
							'<input 							
								class = "wic-selectmenu-input-display' . ($readonly ? '-readonly' : '' ) .'" 
								id="' . safe_html( preg_replace( '#[\[\]]+#', '-', $field_slug ) )  . '-selectmenu-display" 
								name="' . safe_html( preg_replace( '#[\[\]]+#', '-', $field_slug ) ) . '-selectmenu-display"
								autocomplete="off"
								type="text" 
								value="' . safe_html ( '' == trim( $label ) ? static::BLANK_REPLACEMENT_STRING : $label ) . '" ' .
								( $readonly ? ' readonly ' : '' ) . 
							'/>' .
							( $readonly ? '' : '<span class="dashicons dashicons-arrow-down"></span>' ) .
						'</div>' .
						( $readonly ? '' : 
						(
						'<div class="wic-selectmenu-options-layer ">
							<p class ="wic-selectmenu-search-legend"></p>
							<ul class="wic-selectmenu-dropdown-values "> ' .
								$p . $r . 
							'</ul>
							<div class="wic-selectmenu-additional-values-source">' . static::identify_additional_values_source() . // note -- must keep this div empty if not autocomplete
							'</div>
						</div>'
						) 
						) . 
					'</div>'; 
				
		return $control;
	}
	
	public static function format_list_entry( $option ) {
		return '<li class="wic-selectmenu-list-entry">
					<ul>
						<li class="wic-selectmenu-list-entry-value">' . safe_html( $option['value'] ) . '</li>
						<li class="wic-selectmenu-list-entry-label">' . ( '' == trim( $option['label'] ) ? static::BLANK_REPLACEMENT_STRING : safe_html( $option['label'] ) ) . '</li>
					</ul>
				</li>';
	}
	
	protected static function identify_additional_values_source() {
		return '';
	}
		
}


