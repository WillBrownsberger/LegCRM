<?php
/*
*
* class-wic-control-factory.php
*		makes a control of the appropriate type
*
* 
*/

class WIC_Control_Factory {

	public static function make_a_control ( $field_type ) {
		$class_name  = 'WIC_Control_' . $field_type ;
		$new_control = new $class_name; 
		return ( $new_control );	
	}
	
	
}

