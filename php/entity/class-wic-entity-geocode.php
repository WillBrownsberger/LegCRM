<?php
/*
*
*	class-wic-entity-geocode.php
*
*/

class WIC_Entity_Geocode {

	const WIC_GEOCODE_OPTION_ARRAY = 'wic-geocode-option-array';

	public static function get_google_maps_api_key() {

		if ( defined( 'WIC_GOOGLE_MAPS_API_KEY' ) && WIC_GOOGLE_MAPS_API_KEY ) {
			return WIC_GOOGLE_MAPS_API_KEY; 
		}

		return false;		
	}
	
	public static function get_geocodio_api_key() {

		if ( defined( 'WIC_GEOCODIO_API_KEY' ) && WIC_GEOCODIO_API_KEY ) {
			return WIC_GEOCODIO_API_KEY; 
		}
	
		return false;		
	}




	// have gutted this function, replaced with .NET, but consider reinstating the map center computation
	public static function update_geocode_address_cache() { 
	
		/* deleted cache updates :*/

		global $sqlsrv;

		// update midpoint for maps IF NECESSARY
		$computed_center = self::get_geocode_option ( 'computed-map-midpoints' );
		if ( !isset( $computed_center ) || !isset( $computed_center[0] ) ) {
			$sql_midpoints = "SELECT AVG(lat) as mid_lat, AVG(lon) as mid_lon FROM address WHERE lat != 0 and lat !=99";
			$midpoints = $sqlsrv->query ( $sql_midpoints );
			if ( $midpoints ) {
				self::set_geocode_option ( 'computed-map-midpoints', array ( $midpoints[0]->mid_lat, $midpoints[0]->mid_lon ) );
			}
		}
		
	
	}

	// create log mail entry in the content directory ( one above plugin directory)
	private static function log_geo( $message ) {

		$log_directory = WWWROOT . 'logs/';
		$message_wrap = "\n" . '[' . date ( DATE_RSS ) . '] ' . $message;
		
		if ( ! file_put_contents ( $log_directory . 'wp_issues_crm_geocoding_log', $message_wrap, FILE_APPEND ) ) {
			error_log ( "WIC_Entity_Email_Cron::log_geo attempted to write to geo log this message: $message ");
			error_log ( 'Location of geo log should be: ' . $log_directory . DIRECTORY_SEPARATOR . 'wp_issues_crm_geocoding_log -- check permissions.' );	
		};
	
	}

	public static function set_geocode_option ( $option_name, $option_value ) {
		
		$array = WIC_Admin_Settings::get_settings_group( self::WIC_GEOCODE_OPTION_ARRAY );
		if ( ! is_array ( $array ) ) {
			$array = array();
		}

		if (  'set-map-midpoints' == $option_name || 'computed-map-midpoints' == $option_name ) {
			$array[$option_name . '-lat'] = $option_value[0];
			$array[$option_name . '-lon'] = $option_value[1];
		} else {
			$array[$option_name] = $option_value;
		}

		WIC_Admin_Settings::update_settings_group( self::WIC_GEOCODE_OPTION_ARRAY, $array );

		return array ( 'response_code' => true, 'output' => ''  );
	}

	public static function get_map_parameters( $dummy1, $dummy2 ) {
		// add API key and starting map midpoints to variables array
		if ( self::get_google_maps_api_key() ) {
			$set_midpoints = self::get_geocode_option( 'set-map-midpoints' )['output'];
			$computed_midpoints = self::get_geocode_option( 'computed-map-midpoints' )['output'];
			$midpoints = $set_midpoints ? $set_midpoints : $computed_midpoints;
			if ( ! $midpoints ) {
				$midpoints = array ( 42.353, -71.1 ); // arbitrary
			}
			$parms = array( 
					'apiKey' =>  WIC_Entity_Geocode::get_google_maps_api_key(),
					'latCenter' => $midpoints[0],
					'lngCenter' => $midpoints[1],
					'localLayers' => defined('WP_ISSUES_CRM_MAP_DATA_LAYERS') ? WP_ISSUES_CRM_MAP_DATA_LAYERS : false,
					'localCredit' => defined('WP_ISSUES_CRM_MAP_DATA_CREDIT') ? WP_ISSUES_CRM_MAP_DATA_CREDIT : false
				); 
			return array ( 'response_code' => true, 'output' => $parms );
		} else {
			return array ( 'response_code' => false, 'output' => 'Google Maps API Key missing');
		}

	}

	public static function get_geocode_option ( $option_name ) { 
		$array = WIC_Admin_Settings::get_settings_group( self::WIC_GEOCODE_OPTION_ARRAY );
		if ( ! is_array ( $array ) ) {
			$return_val = false;
		}  else {
			// this is conversion syntax -- eliminating array values in options tables
			if (  'set-map-midpoints' == $option_name || 'computed-map-midpoints' == $option_name ) {
				if ( ! isset ( $array[$option_name . '-lat'] ) || ! isset ( $array[$option_name . '-lon'] ) ){
					$return_val = false;
				} else {
					$return_val = array( $array[$option_name . '-lat'] , $array[$option_name . '-lon'] );
				}
			} else {
				$return_val = isset ( $array[$option_name] ) ? $array[$option_name] : false;
			}
		}	
		return array ( 'response_code' => true, 'output' =>  $return_val );

	}

	// called once table already defined in WIC_List_Constituent_Export::do_constituent_download() -- list of selected constituent ids
	public static function filter_temp_table ( $download_type, $search_id ) {
	
		// get shape_array
		$shape_array = self::get_shape_array ( $download_type, $search_id );	
		// shape exclusion sql
		$not_in_shapes = '';
		if ( $shape_array && count( $shape_array ) ) {

			// presumes longitudes on same side of date line
			foreach ( $shape_array as $shape ) {
				$not_in_shapes .= " AND NOT ( ";
				switch ($shape->type) {
					case 'circle':
						
						/*
						* ALT 1using haversine formula to get great circle degrees and then multiplying by meters per degree of latitude (or degrees on any great circle)  
						*	https://en.wikipedia.org/wiki/Haversine_formula
						*	https://stackoverflow.com/questions/24370975/find-distance-between-two-points-using-latitude-and-longitude-in-mysql
						*	goal is not perfect accuracy, rather good consistency with google; not perfect
						*/
						$not_in_shapes .=
							" {$shape->geometry->radius}  > 
							111111.11111111111 * 
							DEGREES(
								ACOS(
									IIF(
										(
										COS(
											RADIANS({$shape->geometry->center->lat})
										)
										* COS(
											RADIANS( lat )
										)
										* COS(
											RADIANS({$shape->geometry->center->lng} - lon)
										)
										+ SIN(RADIANS({$shape->geometry->center->lat}))
										* SIN(RADIANS(lat))
										) < 1
										,
										COS(
												RADIANS({$shape->geometry->center->lat})
											)
										* COS(
												RADIANS( lat )
											)
										* COS(
												RADIANS({$shape->geometry->center->lng} - lon)
											)
											+ SIN(RADIANS({$shape->geometry->center->lat}))
											* SIN(RADIANS(lat))
										, 
										1.0
									)
								)
							)
							";
						/*
						*
						* https://www.govinfo.gov/content/pkg/CFR-2016-title47-vol4/pdf/CFR-2016-title47-vol4-sec73-208.pdf
						* ALT 2 -- approach makes good local adjustments for elliptical
						*
						*
						$lat = " ( if( lat = 0 or lat = 99, 42.5, lat ) ) ";
						$ml = " ( RADIANS( ( {$shape->geometry->center->lat} + $lat )/2 ) )";
						$kpd_lat = " ( 111.13209 - 0.56605 * cos( 2 * $ml ) )";
						$kpd_lon = " ( 111.41513 * cos( $ml ) - 0.094455 * cos( 3 * $ml ) + 0.00120 * cos ( 4*$ml) ) ";
						$nsd =  " ( $kpd_lat * ( {$shape->geometry->center->lat} - $lat ) )";
						$ewd =  " ( $kpd_lon * ( {$shape->geometry->center->lng} - lon ) )";
						$dist = " ( pow( pow($nsd,2) + pow($ewd,2), 0.5 ) ) ";
						$not_in_shapes .= "  {$shape->geometry->radius}  > 1000 * $dist ";
						*/	
						break;
					case 'rectangle':
						$not_in_shapes .= "
							lat > {$shape->geometry->south } AND 
							lat < {$shape->geometry->north } AND 
							lon > {$shape->geometry->west } AND 
							lon < {$shape->geometry->east }  
						";
						break;
					case 'polygon':
						$not_in_shapes .= 
						"
						1 = geometry::STPolyFromText('POLYGON((    
						";
							$first_point = true;
							foreach ( $shape->geometry->path as $point ){ 
								if ( !$first_point ) {
									$not_in_shapes .= ',';
								}
								$not_in_shapes .= "{$point->lat} {$point->lng}";
								$first_point = false;
							} 
							$not_in_shapes .= ", {$shape->geometry->path[0]->lat} {$shape->geometry->path[0]->lng}"; // google path self close, but not wkt, must repeat last
						$not_in_shapes .= 
						"
						))', 3857).STContains(geometry::STGeomFromText(CONCAT('POINT (', lat,' ' , lon, ')'), 3857))
						";
						break;
				}
				$not_in_shapes .= " ) ";		
			}
		}
		/*
		*
		* delete from temp table those entries that have no address, have ungeocoded or not geocodable address, or do not meet the geo screen
		*
		*/
		$temp_table =  WIC_List_Constituent_Export::temporary_id_list_table_name();
		global $sqlsrv;
		// have to do two layers because sql server will throw error on null values in the shape tests 
		$sql =
		"
		DELETE t from $temp_table t 
			LEFT JOIN 
				( 
				SELECT t.id as tid, lat, lon 
				FROM $temp_table t INNER JOIN address a on a.constituent_id = t.id 
				WHERE lat = 0 OR lat = 99 OR ( 1=1 $not_in_shapes )
				) test_geo
				ON tid = t.id 
			LEFT JOIN address a on a.constituent_id = t.id
			WHERE a.constituent_id IS NULL OR tid IS NOT NULL
		"; 
		// do the deletes
		$sqlsrv->query ( $sql, array());
	
	}

	private static function get_shape_array ( $type, $search_id ) {  

		if ( WIC_List_Constituent_Export::download_rule( $type, 'is_issue_only') ) {
			$shape_array = unserialize( WIC_DB_Access_Issue::get_post_details( (int) $search_id )->serialized_shape_array );
		} else {
			global $sqlsrv;
			$result = $sqlsrv->query ( "SELECT serialized_shape_array FROM search_log WHERE ID = ?", array ( $search_id ) );
			if ( is_array ( $result ) ) {
				$shape_array = json_decode ( $result[0]->serialized_shape_array );
			} else {
				$shape_array = false;
			}	
		} 
		
		return $shape_array;
	}


	// takes sql that will generate points list from sql based on current list 
	public static function prepare_list_points ( $type, $search_id ) { 

		// get shape_array
		$shape_array = self::get_shape_array ( $type, $search_id );
		
		// get points
		global $sqlsrv;
		$sql = WIC_List_Constituent_Export::do_constituent_download( $type, $search_id );

		$points = $sqlsrv->query ( $sql, array() );

		// return the array of objects or an error
		if ( $points ) {
			return array ( 'response_code' => true, 
				'output' => array( 
					'points' => $points, 
					'countPoints' => count ( $points ), 
					'constituentSearchUrl' => WIC_Admin_Setup::root_url() . '/?page=wp-issues-crm-main&entity=constituent&action=id_search&id_requested=', 
					'shapeArray' => $shape_array
				)
			); 		
		} elseif ( $shape_array ) {
			return array ( 'response_code' => true, 
				'output' => array( 
					'search_id' => $search_id, 
					'points' => false, 
					'countPoints' => 0, 
					'constituentSearchUrl' => '', 
					'shapeArray' => $shape_array,
				)
			); 		
		} else {
			return array ( 'response_code' => false, 'output' => 'None of selected points had geocode coordinates or there was a database error.'  );
		}
	}
	
	public static function save_shapes ( $map_request, $shape_array ) { 
		// for no good reason, save shape array as PHP serialized for issues but JSON for advanced searches
		if ( 'show_issue_map' == $map_request['context'] ) {
			$result = WIC_DB_Access_Issue::save_issue_serialized_shape_array ( $map_request['id'], serialize( $shape_array ) );
		} elseif ( 'show_map' == $map_request['context'] ) {
			global $sqlsrv;
			$serialized_shape_array = json_encode ( $shape_array );
			$result = $sqlsrv->query ( "UPDATE search_log SET serialized_shape_array = ? WHERE ID = ?", array ( $serialized_shape_array, $map_request['id'] ));
		}

		return array ( 'response_code' => true, 'output' => $result ? 'Shape save successful.' : 'Shape save was unsuccessful or unnecessary.' ); 
	}
}