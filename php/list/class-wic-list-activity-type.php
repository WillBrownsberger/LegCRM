<?php
/*
* class-wic-list-activity-type.php
* 
*/ 

class WIC_List_Activity_Type extends WIC_List_Trend{
	
	protected function get_list_fields ( &$wic_query ) {
		return $wic_query->fields;
	} 	
}	

