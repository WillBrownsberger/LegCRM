<?php
/*
* class-wic-list-user.php
*
*/ 

class WIC_List_User {
    public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal ID for User','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'user_email',  'field_label'=>'Email', 'listing_order'=>'90','list_formatter'=>'',), 
	//	array('field_slug' =>'user_name',   'field_label'=>'Name',  'listing_order'=>'100','list_formatter'=>'',)
		);

	public static $group_by_string = ' office_id, user_email ';

	public static $sort_string =  ' office_id, user_email ';

}	

