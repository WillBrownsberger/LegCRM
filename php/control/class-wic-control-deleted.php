<?php
/*
* wic-control-deleted.php
*
* supports fields of type deleted, which should be configured in entity dictionary with transient = true (1) 
*		-- every repeating group should be configured with a deleted type field with field_slug = screen_deleted
*				( if it is desired to be able to delete rows from the form )
*		-- the screen_deleted field should be assigned to a group within the entity which will determine its positioning
*				( it can have any field_label, x is just one idea, and can be styled further in css  
*					class = wic-input-deleted-label ) 
*
*/
class WIC_Control_Deleted extends WIC_Control_Parent {
	
	// hidden control do nothing on save or update	
	public function create_search_clause () {}
	public function create_update_clause () {}	
	
	//  note that onclick is added in utilities js
	protected static function create_control ( $control_args ) {

		$input_class = 'wic-input-deleted'; // jQuery listener selector
		$label_class = 'wic-input-deleted-label';
		extract ( $control_args, EXTR_SKIP ); 
	
		$readonly = $readonly ? ' disabled="disabled" ' : '';
		 
		$control = ( $field_label > '' ) ?  '<label title = "' . 'Permanently remove this row.' . '" class="' . $label_class . '" for="' . 
				safe_html( $field_slug ) . '"><span class="dashicons dashicons-dismiss"></span></span></label>' : '';
		$control .= '<input class="' . $input_class . '"  id="' . safe_html( $field_slug ) . '" name="' . safe_html( $field_slug ) . 
			'" type="checkbox"  value="1"' . checked( $value, 1, false) . $readonly  . '"/>' ;
		return ( $control );

	}	

}	
