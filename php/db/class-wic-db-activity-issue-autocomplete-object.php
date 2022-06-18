<?php
/*
* class-wic-db-activity-issue-autocomplete-object.php
*	interface object
*
*/
class WIC_DB_Activity_Issue_Autocomplete_Object {
	
	public $label;
	public $value;
	public $entity_type;
	public $email_name;
	public $latest_email_address;
	
	// note -- order of parameters inconsistent withorder in selectmenu presentation -- value, label, entity_type
	public function __construct ( $label, $value, $entity_type = 'activity', $email_name = '', $latest_email_address = '' ) {
		$this->label = htmlspecialchars ( $label );
		$this->value = htmlspecialchars($value);
		$this->entity_type	 = htmlspecialchars($entity_type);
		$this->email_name = htmlspecialchars($email_name);
		$this->latest_email_address = htmlspecialchars($latest_email_address);
	}

}