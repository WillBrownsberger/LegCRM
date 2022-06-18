<?php
/*
*
*  class-wic-form-activity.php
*
*  a replacement for multivalue model -- list with pop-up form
*
*/

class WIC_Form_Activity extends WIC_Form_Parent  {

	public function __construct () {
		$this->entity = 'activity';
	}
	
	protected function group_screen ( $group ) {
		return true;
	}

	public static function create_wic_activity_area () {
		return '<div id="activity-area-ajax-loader"> ' . 'Loading constituent activities ' .
					'<img src="' .WIC_Admin_Setup::root_url() . 'img/ajax-loader.gif' . '">' .
				'</div>' .
				'<div id="wic-activity-area"></div>';	 
	}

	// hooks not implemented
	protected function supplemental_attributes() {}
	protected function get_the_buttons( &$data_array ){}
	protected function format_message ( &$data_array, $message ) {}	
	protected function group_special( $group ) {}
	protected function pre_button_messaging ( &$data_array ){}
    protected function post_form_hook ( &$data_array ) {}  

	public static $form_groups = array(
		'activity'=> array(
			'group_label' => '',
			'group_legend' => '',
			'initial_open' => '1',
			'sidebar_location' => '0',
			'fields' => array('is_changed','constituent_id','activity_type','activity_date','activity_amount','issue','pro_con','activity_note','ID','OFFICE','last_updated_time','last_updated_by')),
		);

	
}