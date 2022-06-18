<?php
/*
* class-wic-list-address.php
*
*/ 

class WIC_List_Address {
    public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal ID for Address','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'address_line','field_label'=>'Street Address','listing_order'=>'90','list_formatter'=>'',), 
		array('field_slug' =>'city','field_label'=>'City','listing_order'=>'100','list_formatter'=>'',)
		);

	public static $group_by_string = ' address.address_line,address.ID,address.city ';

	public static $sort_string =  ' state, city, zip, address_line ';

}	

