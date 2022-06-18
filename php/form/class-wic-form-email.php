<?php
/*
*
*  class-wic-form-email.php
*
*
*/

class WIC_Form_Email extends WIC_Form_Multivalue  {
	
	public function layout_form ( &$data_array, $message, $message_level, $sql = '' ) { 
	
		

		$groups = $this->get_the_groups($this->get_the_entity());
		$class = ( 'row-template' == $this->entity_instance ) ? 'hidden-template' : 'visible-templated-row';
		$search_row = '<div class = "'. $class . '" id="' . $this->entity . '[' . $this->entity_instance . ']">';
		$search_row .= '<div class="wic-multivalue-block ' .  $this->entity . '">';
			foreach ( $groups as $group ) { 
				 $search_row .= '<div class = "wic-multivalue-field-subgroup wic-field-subgroup-' . safe_html( $group->group_slug ) . '">';
						$search_row .= $this->the_controls ( $group->fields, $data_array );
						if ( $message_level == $group->group_slug ) { // using message_level to identify the privileged row
							$search_row .= $message; // here message is icon with email address in it, coming from WIC_Entity_Email	
						}
				$search_row .= '</div>';
			} 
		$search_row .= '</div></div>';
		return $search_row;
	}

	public static $form_groups = array(

		'email_row'=> array(
		   'group_label' => 'Email Row',
		   'group_legend' => '',
		   'initial_open' => '1',
		   'sidebar_location' => '0',
		   'fields' => array('ID','screen_deleted','is_changed','constituent_id','email_type','email_address','OFFICE')),

	);

}