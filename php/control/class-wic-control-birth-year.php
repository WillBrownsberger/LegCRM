<?php
/*
* class-wic-control-birth-year.php
*
*/ 

class WIC_Control_Birth_Year extends WIC_Control_Parent {

	public function sanitize() {  
		$this->value 		= $this->value 	> '' ? utf8_string_no_tags( $this->value ) 	: '';
	}
	/*
	* no error message for bad date, but will fail a required test 
	*/   
	public function validate () {
		if ( $this->value && ! is_numeric ( $this->value ) ) {
			return "Birth year should be a number.";
		}
		if ( floatval ( $this->value ) != intval ( $this->value ) ) {
			return "Birth year cannot be a fraction.";
		}
		$year = date('Y') + 1; 
		if ( $this->value  > '' && ( $this->value  < 1890 || $this->value > $year ) ) {
			return "Birth year, if supplied, must be greater than 1890 and less than $year.";
		}
		
		return '';
	}

}

