<?php
/*
*
*  class-wic-form-user-update.php
*
*/

class WIC_Form_User extends WIC_Form_Multivalue {
	 
	public static $form_groups = array(
		'signature'=> array(
		   'group_label' => 'User',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('ID','screen_deleted','is_changed',
		   	'office_id', 'user_email','user_max_capability','user_name')), 

	);
}