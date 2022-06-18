<?php
/*
* 
* class-wic-control-email.php
*
*
*/

class WIC_Control_Email extends WIC_Control_Text {


	protected static function create_control ( $control_args ) { 
		$control_args['type'] = 'email';
		$control = parent::create_control( $control_args); 
		return ( $control );
	}

	public function sanitize() {

		$this->value = strtolower(preg_replace( '#a-zA-Z0-9._%+-@#', '', $this->value ));	
		
	}

	public function validate () {
		$error = '';
		$class_name = 'WIC_Entity_' . $this->entity_slug;
		$validator = $this->field_slug_base . '_validator';

		if ( $this->value > '' ) {	
			$error = filter_var(  $this->value, FILTER_VALIDATE_EMAIL ) ? '' : 'Email address appears to be not valid. ';
			if ( ! $error &&  method_exists ( $class_name, $validator ) ) { // hook to allow additional validation by entity
				$error = $class_name::$validator ( $this->value );
			}
		}
		return $error;
	
	}	

}

