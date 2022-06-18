<?php
/*
* wic-control-selectmenu-issue.php
*
*/
class WIC_Control_Selectmenu_Issue extends WIC_Control_Selectmenu {

	public function form_control () {
		$final_control_args = get_object_vars ( $this ) ;
		$final_control_args['value'] = $this->value ? $this->value : WIC_Entity_Activity::get_unclassified_post_array()['value'] ; // replace empty/0 option with unclassified
		if ( $final_control_args['readonly'] ) {	
			$final_control_args['readonly_update'] = 1 ; // lets control know to only show the already set value if readonly
			// (readonly control will not show at all on save, so need not cover that case)
		} 
		$final_control_args['option_array'] =  $this->create_options_array ( $final_control_args );
		$control =  $this->create_control( $final_control_args ) ;
		return ( $control );
	}	
	
	
	// value set unknown, so just check to integer; will catch unauthorized integer value at auth
	public function sanitize() {
		global $current_user; 
		// should be either empty or a an integer string		
		if ( ( $this->value != strval(intval($this->value) ) && $this->value )) {
			// sanitize and log attempt
			$log_value = $this->value;
			$this->value = 0; 
			// silently log event 
			if ( $log_value ) {
				error_log ( "!!!!!!!!!!!!******************User {$current_user->get_id()} attempted to inject invalid select value to " . show_value($this->field_label) . ":" . show_value($log_value) );
			} 
		}
	}

}


