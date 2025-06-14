<?php
/*
* This version number has no code consequences; except that if not defined (as in the middle of testing), styles and scripts carry a time stamp as version number
*/
define('LEGCRM_VERSION','1.0.0'); // renumbered for PHP 8.4
/*
*
*
* Review this configuration file and make changes to reflect your deployment
*
* It is designed to support three sets of environment parameters (domain and database) for different deployments -- LOCAL, TEST, PROD
*
* Further below, it also defines additional parameters that may be constant across deployments
*
**************
* ENVIRONMENT SWITCH TO BE SET AT DEPLOYMENT
*
*/
define('CRM_ENVIRONMENT', 'PROD'); // valid values are LOCAL, TEST, PROD (these select alternative domain and database configurations as implemented below)
/*
*
*/
if (extension_loaded('xdebug')) {
	// comment out the following line for maximual debugging information
	xdebug_disable();
}
/*
* NOTES ABOUT VALUES OF PARAMETERS
*
* OVERRIDE_AZURE_SECURITY_FOR_TESTING -- value should be false or the email ID of a user configured in the database (likely the initial user); 
* Should be set to false in production as this waives all signin authentication
*
* SITE_DOMAIN is the front end php web app domain name
* SITE_USING_SSL should generally be set to true (create SSL certificate and bind to domain)
* REGARDING CONNECTION OPTIONS, SEE https://learn.microsoft.com/en-us/sql/connect/php/connection-options
* APP_SQLSRV_NAME -- the database name -- default is legcrm1
* APP_SQLSRV_HOST -- the data base host; if running production azure, this will be of the form ''tcp:voteb.database.windows.net,1433' (1433 is the default port) )
* 
* APP_SQLSRV_UID, APP_SQLSRV_PSWD can be the server administrator or a database user, using sql uid/psw authentication
*
* LOCAL TESTING CONFIG
*
* using windows local authentication
*/
if ( 'PROD' == CRM_ENVIRONMENT ) {
/* 
* 
* DEFINE PRODUCTION PARAMETERS AS ENVIRONMENT VARIABLES IN AZURE WEB APP
* https://learn.microsoft.com/en-us/azure/app-service/configure-common?tabs=portal
* Everything other than letters, numbers, periods and underscores need to be escaped with back slash
* 
*
* CAN ALTERNATIVELY HARD CODE WHEN TESTING
*
*/
	define( 'OVERRIDE_AZURE_SECURITY_FOR_TESTING', false ); 
	define( 'SITE_DOMAIN', getenv('SITE_DOMAIN')); // change this to your domain
	define( 'SITE_USING_SSL', true );
	define( 'SQL_ENCRYPT', 1 ); // communication btw app and database server
	define( 'APP_SQLSRV_NAME', getenv('APP_SQLSRV_NAME') ); // this is the name of the database can use different name if set up different database
	define( 'APP_SQLSRV_HOST', getenv('APP_SQLSRV_HOST') ); // this is the name of database server -- get this from the connection string for the server
	define( 'APP_SQLSRV_UID', getenv('APP_SQLSRV_UID')); 
	define( 'APP_SQLSRV_PSWD', getenv('APP_SQLSRV_PSWD'));
	define( 'ERROR_LOG_QUERIES', false );
	define( 'WP_ISSUES_CRM_MAP_DATA_LAYERS',json_decode(getenv('WP_ISSUES_CRM_MAP_DATA_LAYERS',true));
	/*  Create a JSON string for your map layers variable which is an array:
		
		The following string encodes the array below:
		[{"layerId":"senate","layerTitle":"Senate Districts","layerURL":"https:\/\/your_senatedistricts.geojson","link":"URL","featureTitle":"SENATOR","legend":"SEN_DIST","strokeColor":"#0000ff","strokeWeight":3,"strokeOpacity":0.2},{"layerId":"house","layerTitle":"House Districts","layerURL":"https:\/\/your_housedistrict.geojson","link":"URL","featureTitle":"REP","legend":"REP_DIST","strokeColor":"#ff0000","strokeWeight":2,"strokeOpacity":0.5},{"layerId":"muni","layerTitle":"Municipalities","layerURL":"https:\/\/your_municipalities.geojson","link":false,"featureTitle":"TOWN","legend":"POP2010","strokeColor":"#444","strokeWeight":4,"strokeOpacity":0.2}]
		In environment setting, need to escape further: \[\{\"layerId\"\:\"senate\"\,\"layerTitle\"\:\"Senate Districts\"\,\"layerURL\"\:\"https\:\\\/\\\/your_senatedistricts.geojson\"\,\"link\"\:\"URL\"\,\"featureTitle\"\:\"SENATOR\"\,\"legend\"\:\"SEN_DIST\"\,\"strokeColor\"\:\"#0000ff\"\,\"strokeWeight\"\:3\,\"strokeOpacity\"\:0.2\}\,\{\"layerId\"\:\"house\"\,\"layerTitle\"\:\"House Districts\"\,\"layerURL\"\:\"https\:\\\/\\\/your_housedistrict.geojson\"\,\"link\"\:\"URL\"\,\"featureTitle\"\:\"REP\"\,\"legend\"\:\"REP_DIST\"\,\"strokeColor\"\:\"#ff0000\"\,\"strokeWeight\"\:2\,\"strokeOpacity\"\:0.5\}\,\{\"layerId\"\:\"muni\"\,\"layerTitle\"\:\"Municipalities\"\,\"layerURL\"\:\"https\:\\\/\\\/your_municipalities.geojson\"\,\"link\"\:false\,\"featureTitle\"\:\"TOWN\"\,\"legend\"\:\"POP2010\"\,\"strokeColor\"\:\"#444\"\,\"strokeWeight\"\:4\,\"strokeOpacity\"\:0.2\}\]
		array (
			array( 'layerId' =>'senate', 'layerTitle' => 'Senate Districts', 'layerURL' => 'https://your_senatedistricts.geojson', 'link' => 'URL', 'featureTitle' => 'SENATOR', 'legend' => 'SEN_DIST', 'strokeColor' => '#0000ff', 'strokeWeight' => 3, 'strokeOpacity' => .2),
			array( 'layerId' =>'house', 'layerTitle' => 'House Districts', 'layerURL' => 'https://your_housedistrict.geojson',  'link' => 'URL', 'featureTitle' => 'REP', 'legend' => 'REP_DIST', 'strokeColor' => '#ff0000', 'strokeWeight' => 2, 'strokeOpacity' => .5),
			array( 'layerId' =>'muni', 'layerTitle' => 'Municipalities', 'layerURL' => 'https://your_municipalities.geojson', 'link' => false, 'featureTitle' => 'TOWN', 'legend' => 'POP2010', 'strokeColor' => '#444', 'strokeWeight' => 4, 'strokeOpacity' => .2),
		)
	);
	*/ 
	define( 'WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE', getenv('WIC_USER_NAME_FOR_POSTAL_ADDRESS_INTERFACE'));
	define( 'WIC_GOOGLE_MAPS_API_KEY', getenv('WIC_GOOGLE_MAPS_API_KEY'));
	define( 'WIC_GEOCODIO_API_KEY', getenv('WIC_GEOCODIO_API_KEY'));
/* 
*
* At set up continue review below for additional parameters 
*
*/
} elseif ( 'LOCAL' == CRM_ENVIRONMENT )  {
/*
* Insert local parameter set here (copy from prod branch)
*/
} elseif ( 'TEST' == CRM_ENVIRONMENT )  {
/*
* Insert azure test slot parameter set here (copy from prod branch)
*/
}


/*
* use wordpress salt generator to generate a key for use in nonce generation
* https://api.wordpress.org/secret-key/1.1/salt/ 
*
* or generate any other long strings  (Nonce is used in nonce hash; auth_key and auth_salt used in psw saves)
*
* this can be changed any time, which have the effect of invalidating session cookies and stored passwords
*/
define('NONCE_KEY',getenv('NONCE_KEY'); // USE YOUR FAVORITE RANDOM LONG STRING GENERATOR 
/* include as long using geojson files */
define( 'WP_ISSUES_CRM_MAP_DATA_CREDIT', getenv('WP_ISSUES_CRM_MAP_DATA_CREDIT'); /*For example:  'Boundary Layers from \<a href=\"https\:\/\/www.mass.gov\/orgs\/massgis-bureau-of-geographic-information\" target = \"_blank\"\>MassGIS\<\/a\> converted using \<a href=\"https:\/\/www.macgis.com\/\" target=\"_blank\"\>Cartographica\<\/a\> and \<a href=\"https\:\/\/mygeodata.cloud\" target=\"_blank\"\>mygeodata.cloud\<\/\a\>.'
/*
* resource limits
*/
define('MAX_MESSAGE_SIZE',getenv('define('MAX_MESSAGE_SIZE',getenv('E_KEY') ); // 20000000 OUTLOOK/EXCHANGE LIMIT ATTACHMENTS TO 20MG use this as a limit to processing incoming messages
') ); // 20000000 OUTLOOK/EXCHANGE LIMIT ATTACHMENTS TO 20MG use this as a limit to processing incoming messages
define('MAX_FILE_SIZE', getenv('MAX_FILE_SIZE') ); // 40000000 well below batch size limit [right measure?] https://docs.microsoft.com/en-us/sql/sql-server/maximum-capacity-specifications-for-sql-server?view=sql-server-ver15
ini_set('memory_limit', getenv('memory_limit'); // 128M Still tuning this
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
date_default_timezone_set( getenv('TIME_ZONE')); // 'America/New_York'
// 

// This setting should be in php.ini: default_charset = "utf-8";
/* 
* mail config for wp-issues-crm
*
* note that Office max rate is 30 per minute and we enforce that with delay time
* this config is for continuous web job, but uses some parms from rotation model
*/
define( 'WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND', getenv('WP_ISSUES_CRM_MESSAGE_MAX_SINGLE_SEND') ); // define max sends -- a little arbitrary -- office max be 10,000/day
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
