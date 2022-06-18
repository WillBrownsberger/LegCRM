<?php
/**
*
* class-wic-admin-settings.php
*
* only string values accepted for settings
*
*/


class WIC_Admin_Settings {
	/* 
	* previously handled a broader set  of configuration settings
	* those settings are obsolete and this only supports email control options and geocoding options
	*/
	// settings names are enforced as unique across office regardless of group
	public static function get_setting ( $setting_name ) {
		global $sqlsrv;
		$sqlsrv->query ( "SELECT setting_value 
			FROM core_settings
			WHERE setting_name = ? AND OFFICE = ?
			", 
			array($setting_name, get_office() ) 
		);

		if ( ! $sqlsrv->last_result ) {
			return '';
		} else {
			return $sqlsrv->last_result[0]->setting_value;
		}
	}

	//  always returns an array even if empty when no settings
	public static function get_settings_group ( $group_name ) {
		
		global $sqlsrv;
		$sqlsrv->query ( "SELECT setting_name, setting_value 
			FROM core_settings
			WHERE setting_group = ? AND OFFICE = ?
			", 
			array($group_name, get_office() ) 
		);

		$group_vals = array();
		// always return at least an empty array
		if ( ! $sqlsrv->last_result ) {
			return $group_vals;
		}
		
		foreach (  $sqlsrv->last_result as $result ) { 
			$group_vals[$result->setting_name] = $result->setting_value;
		}
		return $group_vals;
	}


	// does not sanitize, but is parametrized;
	// email settings are sanitized in save_processing_options
	// geocode settings are
	public static function update_settings_group ( $group_name, $data_array ) {
		global $sqlsrv;

		if ( !$data_array || !$group_name ) {
			return false;
		}
		
		$query_array = array();
		foreach ( $data_array as $setting_name => $setting_value ) {
			// don't save the nonce!
			if ( 'wic_nonce' ==  $setting_name || 'undefined' == $setting_name ) continue;
			// will convert straight numbers and booleans to strings
			if( ! is_string( $setting_value )  ) {
				if ( is_numeric ( $setting_value ) || is_bool( $setting_value )
					) {
					$setting_value = strval ( $setting_value );
				// if not convertable, no save
				} else {
					Throw new Exception( 'Attempted to set forbidden non-string value.');
					return false;
				}
			}
			// empty string is allowed value
			$setting_value = trim( $setting_value );
			// proc will save, insert or do nothing depending on whether setting name exists and value changed
			$sqlsrv->query ( "EXECUTE [saveSetting] ?,?,?,?", array( get_office(), $group_name, $setting_name, $setting_value ));
			if ( false === $sqlsrv->success ) {
				return false;
			}
		}
		return true;
	} // function 

}  // class