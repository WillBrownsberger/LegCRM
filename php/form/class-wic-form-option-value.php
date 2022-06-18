<?php
/*
*
*  class-wic-form-option-group.php
*
*/

class WIC_Form_Option_Group extends WIC_Form_Parent  {
	
	// no header tabs
	

	// define the top row of buttons (return a row of wic_form_button s)
	protected function get_the_buttons ( &$data_array ){}
	
	// define the form message (return a message)
    // hooks not implemented
    protected function format_message ( &$data_array, $message ){}
	protected function group_special( $group_slug ) { 	}
	protected function supplemental_attributes() {}
	protected function get_the_legends( $sql = '' ) {}	
	protected function pre_button_messaging ( &$data_array ){}
	protected function post_form_hook ( &$data_array ) {} 

	public static $form_groups = array(
        'option_value'=> array(
               'group_label' => 'Option Values',
               'group_legend' => '',
               'initial_open' => '0',
               'sidebar_location' => '0',
               'fields' => array('screen_deleted','is_changed','option_group_id','parent_option_group_slug','option_value','option_label','value_order','enabled','ID','OFFICE')),
        );
}