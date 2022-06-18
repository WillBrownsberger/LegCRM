<?php
/*
* class-wic-list-phone.php
*
*/ 

class WIC_List_Phone {

    public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal ID for Phone','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'phone_number','field_label'=>'Phone Number','listing_order'=>'100','list_formatter'=>'phone_number_formatter',)
		);

	public static $group_by_string = ' phone.ID, phone_number ';
	
	public static $sort_string = ' phone_number ';

}	

