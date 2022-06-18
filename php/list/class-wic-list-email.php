<?php
/*
* class-wic-list-email.php
*
*/ 

class WIC_List_Email {

	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal ID for Email','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'email_address','field_label'=>'Email Address','listing_order'=>'100','list_formatter'=>'',)
		);

	public static $group_by_string = ' email_address,email.ID ';

	public static $sort_string = ' email_address ';


}	

