<?php
/*
* 
* class-wic-control-float-geo.php
*
* always saves zero on update -- to force recalculation of lat/lon
*
*/

class WIC_Control_Float_Geo extends WIC_Control_Float {

	public function create_update_clause () { 

		$update_clause = array (
			'key' 	=> $this->field_slug_update,
			'value'	=> 0
		);
		return ( $update_clause );
	}
}

