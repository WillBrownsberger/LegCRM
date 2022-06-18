<?php
/*
*
*	wic-entity-issue.php
*
*
*/

class WIC_Entity_Issue extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a top level entity does not process them -- no instance arg
		$this->entity = 'issue';
	} 
	

	public function get_the_title () {
		if ( isset ( $this->data_object_array['post_title'] ) ) { 
			return ( $this->data_object_array['post_title']->get_value() );	
		}
		return ( '' );
	}	
	
	// for user search, user drop down 
	public static function get_last_updated_by_options () {
	
		global $current_user;
		$users = $current_user->office_user_list();
		foreach ( $users as $user_id => $details ) {
			$user_options[] = array (
				'value' => $user_id,
				'label' => $details->user_name ?  $details->user_name :  $details->user_email,			
			); 
		}
		return ( $user_options ) ;
	}

	public static function category_formatter ($category) {
		return ucfirst( $category );

	}

	// for advanced search
	public static function get_issue_options( $value ) {
		return ( WIC_Entity_Activity::get_issue_options( $value ) );
	}

	public static function review_formatter( $value ) {
		return substr(WIC_Entity_Search_Log::download_time_formatter($value),0,10);
	}


	public static function hard_delete ( $id, $dummy ) { 
		// test if have activities
		global $sqlsrv;

		$sqlsrv->query( "DELETE FROM issue WHERE ID = ? AND OFFICE = ?", array($id, get_office()) );
		$deleted = $sqlsrv->success;
		return array ( 'response_code' => true, 'output' => (object) array ( 
			'deleted' => $deleted, 
			'reason' => $deleted ? 
				'This issue has been deleted.' : 
				'Database error on attempted delete.' 
			) 
		);
	}

	protected static $entity_dictionary = array(


		'follow_up_status'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Status',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'follow_up_status_options',),
		'ID'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'integer',
			'field_label' =>  'ID',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Issue',
			'option_group' =>  'get_issue_options',),
		'is_changed'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '0',
			'transient' =>  '1',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'issue_staff'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Staff',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'get_user_array',),
		'last_updated_by'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'By',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet created',
			'option_group' =>  'get_last_updated_by_options',),
		'last_updated_time'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'On',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Not yet created',
			'option_group' =>  '',),
		'OFFICE'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '1',
			'field_type' =>  'integer',
			'field_label' =>  'Office',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'post_category'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  'Category',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 50,
			'option_group' =>  '',),
		'post_content'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'textarea',
			'field_label' =>  'Text',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  ' . . . issue content . . .',
			'length' => 50000,
			'option_group' =>  '',),
		'post_title'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  'Title',
			'required' =>  'individual',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'length' => 400,
			'option_group' =>  '',),
		'review_date'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'date',
			'field_label' =>  'Review',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Date',
			'option_group' =>  '',),
		'wic_live_issue'=> array(
			'entity_slug' =>  'issue',
			'hidden' =>  '0',
			'field_type' =>  'selectmenu',
			'field_label' =>  'Email Rule',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => 'open',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'wic_live_issue_options',),

	);

	public static $option_groups = array(
		'follow_up_status_options'=> array(
		   	array('value'=>'','label'=>'',),
			array('value'=>'closed','label'=>'Closed',),
			array('value'=>'open','label'=>'Open',)),
		 'wic_live_issue_options'=> array(
		   	array('value'=>'','label'=>'Not Assignable',),
			array('value'=>'open','label'=>'Assignable',)),
	);
}