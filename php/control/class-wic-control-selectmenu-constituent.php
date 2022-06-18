<?php
/*
* wic-control-selectmenu-constituent.php
*
*/
class WIC_Control_Selectmenu_Constituent extends WIC_Control_Selectmenu {

	public function create_options_array () {
		return array();
	}

	protected static function identify_additional_values_source() {
		return 'constituent';
	}

	public function sanitize() {
		global $current_user; 
		// should be either empty or a an integer string		
		if ( ( $this->value != strval(intval($this->value) ) && $this->value )) {
			// sanitize and log attempt
			$log_value = $this->value;
			$this->value = 0; 
			// silently log event if the field was offering a non-empty set of valid values
			if ( $log_value ) {
				error_log ( "!!!!!!!!!!!!******************User {$current_user->get_id()} attempted to inject invalid select value to " . show_value($this->field_label) . ":" . show_value($log_value) );
			} 
		}
	}

}


