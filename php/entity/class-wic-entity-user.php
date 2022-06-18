<?php
/*
*
*	wic-entity-user.php
*
*/
class WIC_Entity_User extends WIC_Entity_Multivalue {

	// current user properties
	private $id;
	private $name;  
	private $email = false; 		 
	private $office_number; 
	private $max_capability; 
	private $preferences = array();
	// other user list cached for selectmenus
	private $office_user_array;

	// serves as both standard entity for CRUD support and as reference entity, but does not need special construct
	// take action_requested on 'load'; do form with instance if new form, but in the form case won't have have "instance"
	protected function set_entity_parms( $args ) { // 
		if( is_array ( $args ) ) extract ( $args );
		$this->entity = 'user';
		$this->entity_instance = $instance ?? '';
	} 

	// first group of functions support the user identity object role

	// populate current_user object
	protected function load( $args ) { // $args dummy parameter

		$this->email = get_azure_user_direct(); // $_SERVER['REMOTE_USER']
	
		// load office list
		global $sqlsrv;
		$this->user_array = $sqlsrv->query ( 
			"
			SELECT 
				u1.ID as user_id, 
				u1.user_email, 
				u1.user_name,
				u1.office_id as user_office_number, 
				u1.user_max_capability,
				iif( u1.user_email = ?, u1.user_preferences, '') as user_preferences
			FROM [user] u1 INNER JOIN [user] u2 on u1.office_id = u2.office_id
			INNER JOIN office on office.ID = u1.office_id
			WHERE u2.user_email = ? and u1.user_enabled = 1 and office_enabled = 1
			", 
			array ( 
				$this->email, 
				$this->email
				) 
		);
		
		// exit on not found
		if ( !$this->user_array ) {
			error_log ( 'Apparent unauthorized access.  Incoming email per azure was:'  . $this->email . '-- no entry in CRM user table.' );
			return false;
		}

		// extract current_user properties
		foreach ( $this->user_array as $user) {
			if ( strtolower($user->user_email) != strtolower($this->email )) {
				continue;
			} else {
				$this->id = $user->user_id;
				$this->name = $user->user_name;
				$this->office_number = $user->user_office_number;
				$this->max_capability = $user->user_max_capability;
				$this->preferences = unserialize( $user->user_preferences );
				break;
			}
		}
	}

	public function get_user_by_id ( $id ) {
		foreach ( $this->user_array as $user) {
			if ( $user->user_id != $id ) {
				continue;
			} else {
				return (object) array(
					'user_name' => $user->user_name,
					'user_email'=> $user->user_email
				);
			}
		}
		// false if user not found
		return false;
	}


	public function current_user_authorized_to( $required_capability ) {

		// duplicates user_max_capability_options
		$role_hierarchy = array(
			'assigned_only',
			'all_crm',
			'all_email', 
			'super',
		);

		$required_index = array_search ( $required_capability, $role_hierarchy );

		$has_capability = array_search ( $this->max_capability, $role_hierarchy );

		return $has_capability >= $required_index;

	}

	public function get_office_number() {
		return $this->office_number;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_display_name() {
		return  ( isset ( $this->name ) && $this->name > '' ) ? $this->name : $this->email ;
	}


	public function office_user_list() {
		$user_list = array();
		foreach ( $this->user_array as $user) {
			$user_list[$user->user_id] = (object) array(
					'user_name' => $user->user_name,
					'user_email'=> $user->user_email
				);
		}
		return ( $user_list );
	}


	/*
	* following functions support user preference settin
	*/

	// return preference value for specified user preference string
	public function get_preference ( $preference ) {
		return ( isset ( $this->preferences[$preference] ) ?  $this->preferences[$preference] : false );
	}

	public static function set_wic_user_preference_wrap( $preference, $value ) {
		global $current_user;
		return $current_user->set_wic_user_preference ( $preference, $value );
	}

	// return preference value for specified user preference string
	public function set_wic_user_preference ( $preference, $set_value ) {
		
		if ( '' == $this->preferences ) {
			$this->preferences = array();
		}

		// sanitize html (signature)
		if ( 'signature' == $preference )  {
			$set_value = WIC_DB_Email_Message_Object::strip_html_head( $set_value ); 
		} elseif ( 'wic_dashboard_config' != $preference ) { // no sanitization of dashboard config -- parametrized here and sanitized on output html in dashboard
			// only two kinds of preferences to save
			return array ( 'response_code' => false, 'output' => 'Unexpected user preference setting' );
		}

		// is there a change?
		if ( isset( $this->preferences[$preference] ) &&  $this->preferences[$preference] == $set_value  ) {
			return array ( 'response_code' => true, 'output' => '' );
		}


			// inject new val into existing value array
		$this->preferences[$preference] = $set_value;

		// set for future transactions
		global $sqlsrv;
		$sql = 	$sqlsrv->query( "UPDATE [user] SET  
			user_preferences = ?
			WHERE user_email = ?
			",
			array(
				serialize ( $this->preferences ),
				$this->email
			)
		);	
		return array ( 'response_code' => $sqlsrv->success, 'output' => !$sqlsrv->success ? 'Method wic_entity_user::set_wic_user_preference failed.  Database error was logged.' : '' );
	}

	public function get_signature() { 
		return isset( $this->preferences['signature'] ) ? $this->preferences['signature'] : '';
	}

	protected static $entity_dictionary = array(
		'ID'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Internal ID for User',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'user_email'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '0',
			'field_type' =>  'email',
			'field_label' =>  'User Email',
			'required' =>  'individual',
			'dedup' =>  '1',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 200,
			'option_group' =>  '',),
		'user_name'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'User Name',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Name',
			'length' => 100,
			'option_group' =>  '',),
		'office_id' => array(
			'entity_slug' =>  'user',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'user_max_capability'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Capability Level',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'user_max_capability_options',),
		// not including user preferences in array since not handled by user entity form
		'is_changed'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'length' => 1,
			'option_group' =>  '',),
		'last_updated_by'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'User Updated By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'user_last_updated_by',),
		'last_updated_time'=> array(
			'entity_slug' =>  'user',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'User Updated Time',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'screen_deleted'=> array(
			'entity_slug' =>  'address',
			'hidden' =>  '0',
			'field_type' =>  'deleted',
			'field_label' =>  'x',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
	);

	public static $option_groups = array(
		'user_max_capability_options'=> array(
		  	array('value'=>'','label'=>'Level?',),
			  array('value'=>'assigned','label'=>'Only as assigned.',),
			  array('value'=>'all_crm','label'=>'All non-email functions.',),
			  array('value'=>'all_email','label'=>'All CRM Functions',),
			  array('value'=>'super','label'=>'Create Users (LIS)',),
		),
	);

}	