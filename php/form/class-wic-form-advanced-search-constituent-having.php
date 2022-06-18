<?php
/*
*
*  class-wic-form-advanced-search-constituent-having.php
*
*/

class WIC_Form_Advanced_Search_Constituent_Having extends WIC_Form_Parent  {

    // hooks not implemented
    protected function get_the_buttons ( &$data_array ) {}
    protected function format_message ( &$data_array, $message ){}
	protected function post_form_hook( &$data_array ) {}
	public static function format_name_for_title ( &$data_array ) {}
	protected function supplemental_attributes() {}
	protected function pre_button_messaging ( &$data_array ){}
	public static $form_groups = array(

		'constituent_having_row'=> array(
		   'group_label' => '',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('screen_deleted','constituent_having_aggregator','constituent_having_field','constituent_having_comparison','constituent_having_value')),
	);
}