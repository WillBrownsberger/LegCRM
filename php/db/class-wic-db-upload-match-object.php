<?php
/*
* class-wic-db-upload-match-object.php
*	
*
*/

class WIC_DB_Upload_Match_Object {
	
	public $label;
	public $link_fields;
	public $order;
	public $total_count;
	public $have_components_count;
	public $have_components_and_valid_count;
	public $have_components_not_previously_matched;
	public $matched_with_these_components;
	public $not_found;
	public $not_unique;						
	public $unmatched_unique_values_of_components;
	public $unmatched_records_with_valid_components;
	
	public function __construct (
		$label,
		$link_fields,
		$order = 0, // 0 is not in the used set (order immaterial)
		$total_count = 0,
	 	$have_components_count = 0,
	 	$have_components_and_valid_count = 0,
	 	$have_components_not_previously_matched = 0,
	 	$matched_with_these_components = 0,
	 	$not_found = 0,
	 	$not_unique = 0,						
	 	$unmatched_unique_values_of_components = '?',
	 	$unmatched_records_with_valid_components = 0
	) 
	 {
		$this->label = $label;
		$this->link_fields = $link_fields;
		$this->order = $order;
		$this->total_count = $total_count;
	 	$this->have_components_count = $have_components_count;
	 	$this->have_components_and_valid_count = $have_components_and_valid_count;
	 	$this->have_components_not_previously_matched = $have_components_not_previously_matched; //only valid
	 	$this->matched_with_these_components = $matched_with_these_components;
	 	$this->not_found = $not_found;
	 	$this->not_unique = $not_unique;						
	 	$this->unmatched_unique_values_of_components = $unmatched_unique_values_of_components;
	 	$this->unmatched_records_with_valid_components = $unmatched_records_with_valid_components;
	}

}