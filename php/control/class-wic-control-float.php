<?php
/*
* 
* class-wic-control-float.php
*
* validates and emits float update values
*/

class WIC_Control_Float extends WIC_Control_Parent {

    public function validate()  {
        $temp_val = $this->value;
        // sanitized as well as validated.
        $this->value = floatval( $this->value );
        if($temp_val > '' && $temp_val != strval($this->value)) {
            return "{$this->field_label} must be a number.";
        } else {
            return '';
        }
    }

	protected static function create_control ( $control_args ) { 
		$control_args['type'] = 'number';
		$control = parent::create_control( $control_args); 
		return ( $control );
	}

    // in case an entity specific validator overrides $this->validate, assure that only save float
	public function create_update_clause () { 
		if ( 
			( ( ! $this->transient ) && ( ! $this->readonly ) ) 
			|| 'ID' == $this->field_slug 
			 ) {
			// exclude transient and readonly fields.   ID as readonly ( to allow search by ID), but need to pass it anyway.
			// ID is a where condition on an update in WIC_DB_Access_WIC::db_update
			$update_clause = array (
				'key' 	=> $this->field_slug_update,
				'value'	=> floatval( $this->value )
			);
			
			return ( $update_clause );
		}
	}

}

