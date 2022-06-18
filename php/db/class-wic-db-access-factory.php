<?php
/*
*
* class-wic-db-access-factory.php
*
* supports multiple approaches to data access to be further implemented in extensions of WP_DB_Access
*
*/

class WIC_DB_Access_Factory {

	static private $entity_model_array = array (
		'activity' 			=> 'WIC_DB_Access_WIC', 		// multivalue save update activity
		'address'			=> 'WIC_DB_Access_WIC',
		'advanced_search'	=> 'WIC_DB_Access_Advanced_Search',
		'constituent' 		=> 'WIC_DB_Access_WIC',	
		'email'				=> 'WIC_DB_Access_WIC',
		'issue' 			=> 'WIC_DB_Access_WIC',
		'office'			=> 'WIC_DB_Access_WIC',
		'phone'				=> 'WIC_DB_Access_WIC',
		'search_log'		=> 'WIC_DB_Access_WIC',	
		'subject'			=> 'WIC_DB_Access_Subject',
		'upload'			=> 'WIC_DB_Access_WIC',
		'user'				=> 'WIC_DB_Access_WIC',
		)
	;

	// available to support up reach to parent entity where necessary 
	// initially implemented to support time_stamping in multi-value control set_value function --
	//   when doing deletes of child entity (e.g. email) need to timestamp parent (constituent)
	static private $entity_parent_array = array (
		'activity' 		=> 'constituent', 
		'address'		=> 'constituent',
		'advanced_search' => '', 
		'constituent' 	=> '',	
		'email'			=> 'constituent',
		'issue' 		=> '',
		'office'		=> '',
		'phone'			=> 'constituent',
		'search_log'	=> '',	
		'subject'		=> '',
		'upload'		=> '',
		'user'			=> 'office',

	);

	static public $office_specific_entities = array( 
		'activity','address','constituent','email','issue','option_value','phone','search_log','subject','upload', // 'user' is office specific, but comes in to search/save as multivalue linked on office
	);


	public static function make_a_db_access_object ( $entity ) { 
		$right_db_class = self::$entity_model_array[$entity];
		$new_db_access_object = new $right_db_class ( $entity );
		if ( self::$entity_parent_array[$entity] > '' ) {
			$new_db_access_object->parent = self::$entity_parent_array[$entity]; // set entity_parent for new entity if exists
		}
		return ( $new_db_access_object );	
	}

	public static function is_entity_instantiated ( $entity ) {
		return isset ( self::$entity_model_array[$entity] );
	}

	public static function validate_table_name ( $table_name ) {
		return isset(self::$entity_parent_array[$table_name]);

	}
	
}

