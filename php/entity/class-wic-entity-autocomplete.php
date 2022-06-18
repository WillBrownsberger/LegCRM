<?php
/*
*	wic-entity-autocomplete.php 
*	psuedo entity for fast lookups
* 
*   this function mostly pushed to db server -- one db round trip
*/

class WIC_Entity_Autocomplete  { 

	// note that look_up_mode is being passed as "id requested" in class-wic-admin-ajax.php -- term preserved
	public static function db_pass_through( $look_up_mode, $term ) { 
		
		// strip look_up_mode out of $look_up_mode if encumbered by indexing
		$look_up_mode = strrchr ( $look_up_mode , '[') === false ? $look_up_mode : ltrim( rtrim( strrchr ( $look_up_mode , '['), ']' ), '[' ); 
		return WIC_Entity_Search_Box::search( $look_up_mode, $term, true);
	}

}