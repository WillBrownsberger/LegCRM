<?php
/*
* class-wic-control-date.php
*
*/ 

class WIC_Control_Date extends WIC_Control_Parent {
	/* 
	* see create_control  for date handling
	*/
	public function sanitize() {  
		$this->value 		= $this->value 	> '' ? self::sanitize_date ( $this->value ) 	: '';
	}

	/*
	* no error message for bad date, but will fail a required test 
	*/   
	public static function sanitize_date ( $possible_date ) {
		try {
			$test = new DateTime( $possible_date );
		}	catch ( Exception $e ) {
			return ( '' );
		}	   			
 		return ( date_format( $test, 'Y-m-d' ) );
	}
	
	protected static function create_control ( $control_args ) { 
		if ( '1900-01-01' == substr( $control_args['value'], 0, 10) ) {
			// the 1900-01-01 value is effectively blank 
			// if saved as blank, sql server will translate that to 1900-01-01 
			$control_args['value'] = '';
		} else {
			// never showing time values in date field
			$control_args['value'] = substr( $control_args['value'], 0, 10);
		}
		$control_args['type'] = 'date';
		$control = parent::create_control( $control_args); 
		return ( $control );
	}
	
	
}

