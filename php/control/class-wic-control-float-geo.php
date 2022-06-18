<?php
/*
* 
* class-wic-control-float-geo.php
*
* always saves zero on update -- to force recalculation of lat/lon
*
*/

class WIC_Control_Float_Geo extends WIC_Control_Float {
    // in case an entity specific validator overrides $this->validate, assure that only save float
	public function create_update_clause () { 
		// exclude transient and readonly fields.   ID as readonly ( to allow search by ID), but need to pass it anyway.
		// ID is a where condition on an update in WIC_DB_Access_WIC::db_update
		$update_clause = array (
			'key' 	=> $this->field_slug_update,
			'value'	=> 0
		);
		return ( $update_clause );
	}
}

