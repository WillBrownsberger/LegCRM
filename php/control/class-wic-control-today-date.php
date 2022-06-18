<?php
/*
* class-wic-control-today-date.php
*
* date control converting blank or 1900-01-01 to today
*
* use where date is required anyway and today makes sense (new activity . . . )
*/ 

class WIC_Control_Today_Date extends WIC_Control_Date {

	protected static function create_control ( $control_args ) { 
		if ( '1900-01-01' == substr( $control_args['value'], 0, 10) || '' == $control_args['value'] ) {
			$today = new DateTime('now');
			$control_args['value'] = date_format( $today, 'Y-m-d' );
		} else {
			// never showing time values in date field
			$control_args['value'] = substr( $control_args['value'], 0, 10);
		}
		$control_args['type'] = 'date';
		$control = parent::create_control( $control_args); 
		return ( $control );
	}
	
	
}

