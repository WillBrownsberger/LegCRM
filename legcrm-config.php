<?php
/*
*
* define version
*
*/
define('LEGCRM_VERSION','0.0.1');
/*
*
* ENVIRONMENT SWITCH TO BE SET AT DEPLOYMENT
*
*/
define('CRM_ENVIRONMENT', 'PROD'); // valid values are LOCAL, TEST, PROD
if (extension_loaded('xdebug')) {
	// comment out the following line for maximual debugging information
	xdebug_disable();
}
/*
*
* LOCAL TESTING CONFIG
*
* using windows local authentication
*/
if ( 'LOCAL' == CRM_ENVIRONMENT ) {
	define('OVERRIDE_AZURE_SECURITY_FOR_TESTING', 'tester@yourdomain.org');
	define( 'SITE_DOMAIN', '192.168.1.11'); 
	define( 'SITE_USING_SSL', false );
	define( 'SQL_ENCRYPT', 0 ); // communication btw app and database server
	define( 'APP_SQLSRV_NAME', 'legcrm1' );
	define( 'APP_SQLSRV_HOST', 'localhost' );
	define( 'APP_SQLSRV_UID', '' ); 
	define( 'APP_SQLSRV_PSWD', '');	
	define( 'ERROR_LOG_QUERIES', false );
	define( 'WP_ISSUES_CRM_MAP_DATA_LAYERS',
		array (
			array( 'layerId' =>'senate', 'layerTitle' => 'Senate Districts', 'layerURL' => 'https://your_senatedistricts.geojson', 'link' => 'URL', 'featureTitle' => 'SENATOR', 'legend' => 'SEN_DIST', 'strokeColor' => '#0000ff', 'strokeWeight' => 3, 'strokeOpacity' => .2),
			array( 'layerId' =>'house', 'layerTitle' => 'House Districts', 'layerURL' => 'https://your_housedistrict.geojson',  'link' => 'URL', 'featureTitle' => 'REP', 'legend' => 'REP_DIST', 'strokeColor' => '#ff0000', 'strokeWeight' => 2, 'strokeOpacity' => .5),
			array( 'layerId' =>'muni', 'layerTitle' => 'Municipalities', 'layerURL' => 'https://your_municipalities.geojson', 'link' => false, 'featureTitle' => 'TOWN', 'legend' => 'POP2010', 'strokeColor' => '#444', 'strokeWeight' => 4, 'strokeOpacity' => .2),
		)
	); 
	define( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE', 'xxxxxxxxx');
	define( 'WIC_GOOGLE_MAPS_API_KEY', 'xxxxxxxxxx');
	define( 'WIC_GEOCODIO_API_KEY', 'xxxxxxxxx');
/*
*
* AZURE TESTING CONFIG 
*
* using sql uid/psw authentication
*/
} elseif ( 'TEST' == CRM_ENVIRONMENT )  {
	define( 'OVERRIDE_AZURE_SECURITY_FOR_TESTING', false);
	define( 'SITE_DOMAIN', 'your_project.azurewebsites.net'); 
	define( 'SITE_USING_SSL', true );
	define( 'SQL_ENCRYPT', 1 ); // communication btw app and database server
	define( 'APP_SQLSRV_NAME', 'name' );
	define( 'APP_SQLSRV_HOST', 'tcp:host,1433' );
	define( 'APP_SQLSRV_UID', 'uid' ); 
	define( 'APP_SQLSRV_PSWD', 'password');
	define( 'ERROR_LOG_QUERIES', false );
	define( 'WP_ISSUES_CRM_MAP_DATA_LAYERS',
		array (
			array( 'layerId' =>'senate', 'layerTitle' => 'Senate Districts', 'layerURL' => 'https://your_senatedistricts.geojson', 'link' => 'URL', 'featureTitle' => 'SENATOR', 'legend' => 'SEN_DIST', 'strokeColor' => '#0000ff', 'strokeWeight' => 3, 'strokeOpacity' => .2),
			array( 'layerId' =>'house', 'layerTitle' => 'House Districts', 'layerURL' => 'https://your_housedistrict.geojson',  'link' => 'URL', 'featureTitle' => 'REP', 'legend' => 'REP_DIST', 'strokeColor' => '#ff0000', 'strokeWeight' => 2, 'strokeOpacity' => .5),
			array( 'layerId' =>'muni', 'layerTitle' => 'Municipalities', 'layerURL' => 'https://your_municipalities.geojson', 'link' => false, 'featureTitle' => 'TOWN', 'legend' => 'POP2010', 'strokeColor' => '#444', 'strokeWeight' => 4, 'strokeOpacity' => .2),
		)
	); 
	define( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE', 'xxxxxxxxx');
	define( 'WIC_GOOGLE_MAPS_API_KEY', 'xxxxxxxxxx');
	define( 'WIC_GEOCODIO_API_KEY', 'xxxxxxxxx');
} elseif ( 'PROD' == CRM_ENVIRONMENT )  {
/* 
* 
* DEFINE PRODUCTION PARAMETERS HERE 
*
* NOTE SHOULD CONSIDER SWITCHING TO ACTIVE DIRECTORY AUTHENTICATION FOR DATABASE ACCESS
* ON THE OTHER HAND, FIREWALL LIMITS TO DESIGNATED IP ADDRESSES . . . SO BELT AND SUSPENDERS?
*/
	define( 'OVERRIDE_AZURE_SECURITY_FOR_TESTING', false);
	define( 'SITE_DOMAIN', 'your_project.azurewebsites.net'); 
	define( 'SITE_USING_SSL', true );
	define( 'SQL_ENCRYPT', 1 ); // communication btw app and database server
	define( 'APP_SQLSRV_NAME', 'name' );
	define( 'APP_SQLSRV_HOST', 'tcp:host,1433' );
	define( 'APP_SQLSRV_UID', 'uid' ); 
	define( 'APP_SQLSRV_PSWD', 'password');
	define( 'ERROR_LOG_QUERIES', false );
	define( 'WP_ISSUES_CRM_MAP_DATA_LAYERS',
		array (
			array( 'layerId' =>'senate', 'layerTitle' => 'Senate Districts', 'layerURL' => 'https://your_senatedistricts.geojson', 'link' => 'URL', 'featureTitle' => 'SENATOR', 'legend' => 'SEN_DIST', 'strokeColor' => '#0000ff', 'strokeWeight' => 3, 'strokeOpacity' => .2),
			array( 'layerId' =>'house', 'layerTitle' => 'House Districts', 'layerURL' => 'https://your_housedistrict.geojson',  'link' => 'URL', 'featureTitle' => 'REP', 'legend' => 'REP_DIST', 'strokeColor' => '#ff0000', 'strokeWeight' => 2, 'strokeOpacity' => .5),
			array( 'layerId' =>'muni', 'layerTitle' => 'Municipalities', 'layerURL' => 'https://your_municipalities.geojson', 'link' => false, 'featureTitle' => 'TOWN', 'legend' => 'POP2010', 'strokeColor' => '#444', 'strokeWeight' => 4, 'strokeOpacity' => .2),
		)
	); 
	define( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE', 'xxxxxxxxx');
	define( 'WIC_GOOGLE_MAPS_API_KEY', 'xxxxxxxxxx');
	define( 'WIC_GEOCODIO_API_KEY', 'xxxxxxxxx');
}


/*
* use wordpress salt generator to generate a key for use in nonce generation
* https://api.wordpress.org/secret-key/1.1/salt/ 
*
* or generate any other long strings  (Nonce is used in nonce hash; auth_key and auth_salt used in psw saves)
*
* this can be changed any time, which have the effect of invalidating session cookies and stored passwords
*/
define('NONCE_KEY','insert long string');
/* include as long using geojson files */
define( 'WP_ISSUES_CRM_MAP_DATA_CREDIT', 'For example:  Boundary Layers from <a href="https://www.mass.gov/orgs/massgis-bureau-of-geographic-information" target = "_blank">MassGIS</a> converted using <a href="https://www.macgis.com/" target="_blank">Cartographica</a> and <a href="https://mygeodata.cloud" target="_blank">mygeodata.cloud</a>.');
/*
* resource limits
*/
define('MAX_MESSAGE_SIZE',20000000 ); // OUTLOOK/EXCHANGE LIMIT ATTACHMENTS TO 20MG use this as a limit to processing incoming messages
define('MAX_FILE_SIZE',40000000 ); // well below batch size limit [right measure?] https://docs.microsoft.com/en-us/sql/sql-server/maximum-capacity-specifications-for-sql-server?view=sql-server-ver15
ini_set('memory_limit', '128M'); // Still tuning this
/*
*
* set default time zone for date time functions
* https://www.php.net/manual/en/function.date-default-timezone-set.php
* NOTE THAT IN SQL, always using time zone converted functions easternDate and convertUTCStringToEasternString
* TO CHANGE TIME ZONE, change here and in those functions
* 
* in this app, all dates and datetimes are stored and presented in local time 
* 	-- exception: in parsed_message_json, original email_date_time UTC is preserved
*	-- exception: some utc stamps used as seconds
*/
date_default_timezone_set( 'America/New_York');
// 

// This setting should be in php.ini: default_charset = "utf-8";
/* 
* mail config for wp-issues-crm
*
* note that Office max rate is 30 per minute and we enforce that with delay time
* this config is for continuous web job, but uses some parms from rotation model
*/
define( 'WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND', 1000 ); // define max sends -- a little arbitrary -- office max be 10,000/day
/*
*
* AUTOLOADER AND STACK TRACE 
*
*/
if ( ! spl_autoload_register('wp_issues_crm_autoloader' ) ) {
	die ( '<h3>Fatal Error: Unable to register wp_issues_crm_autoloader in wp-issues-crm.php</h3>' );	
};

// class autoloader is case insensitive, except that it requires WIC_ (sic) as a prefix.
// always register to support not only in admin, but on front facing forms and in cron runs
function wp_issues_crm_autoloader( $class ) {
	if ( 'WIC_' == substr ($class, 0, 4 ) ) {
		$subdirectory = 'php'. DIRECTORY_SEPARATOR . strtolower( substr( $class, 4, ( strpos ( $class, '_', 4  ) - 4 )  ) ) . DIRECTORY_SEPARATOR ;
		$class = strtolower( str_replace( '_', '-', $class ) );
		$class_file = WWWROOT . $subdirectory .  'class-' . str_replace ( '_', '-', $class ) . '.php';
		if ( file_exists ( $class_file ) ) {  
   			require_once $class_file;
   		} else {
	   		wic_generate_call_trace();
			die ( '<h3>' . sprintf(  'Fatal configuration error -- missing file %s; failed in autoload.' , $class_file ) . '</h3>' );   
	   } 
	}	
}

// stack trace function for locating bad class definitions and also sqlsrv queries; 
function wic_generate_call_trace($pop_count = 0) { // from http://php.net/manual/en/function.debug-backtrace.php

	$e = new Exception();
	$trace = explode("\n", $e->getTraceAsString());
	// reverse array to make steps line up chronologically
	$trace = array_reverse($trace);
	array_shift($trace); // remove {main}
	for ( $j = 0; $j < $pop_count + 1; $j++) {
		array_pop($trace); // remove call to this method
	}
	$length = count($trace);
	$result = array();
	for ($i = 0; $i < $length; $i++) {
		$result[] = ($i + 1) . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
	}
	return "\t" . implode("<br/>\n\t", $result);
}
