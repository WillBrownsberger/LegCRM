<?php
/*
*
*  class-wic-form-address.php
*
*
*/

class WIC_Form_Address extends WIC_Form_Email  {
	
	// all form specific functions are the same, just called with different values

	public static $form_groups = array(

		'address_line_1'=> array( 
			'group_label' => 'Address Line 1', 
			'group_legend' => '', 
			'initial_open' => '1', 
			'sidebar_location' => '0', 
			'fields' => array('ID','screen_deleted','is_changed','constituent_id','address_type','address_line','OFFICE')),
		'address_line_2'=> array( 
			'group_label' => 'Address Line 2', 
			'group_legend' => '', 
			'initial_open' => '0', 
			'sidebar_location' => '0', 
			'fields' => array('city','state','zip','lat','lon')),
	);

}

