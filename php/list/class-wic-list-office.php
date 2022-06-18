<?php
/*
* class-wic-list-option-group.php
*
*
*/ 

class WIC_List_Office extends WIC_List_Constituent {
	/*
	*
	*/
	public static $sort_string = ' office_label ';

	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Office Number','listing_order'=>'25','list_formatter'=>'',), 
		array('field_slug' =>'office_label','field_label'=>'Office Holder','listing_order'=>'20','list_formatter'=>'',), 
		array('field_slug' =>'office_enabled','field_label'=>'Enabled','listing_order'=>'30','list_formatter'=>'enabled_options',), 
		array('field_slug' =>'user','field_label'=>'Users','listing_order'=>'40','list_formatter'=>'user_list_formatter',)
		);
	public static $group_by_string = 'office.office_enabled,office.ID,office.office_label';

	protected function format_rows( &$wic_query, &$fields ) {
		$output = '';
		$line_count = 1;
  	
	   	// expand found id's into full list
  		$wic_query->list(); 
		// loop through the rows and output a list item for each
		foreach ( $wic_query->list_result as $row_array ) { 

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			// add special row class to reflect case assigned status
			if ( ! $row_array->office_enabled ) {
				$row_class .= " option-group-disabled ";
			}			
			
			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} );
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'		=> $row_array->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		
		return ( $output );		
	}

	protected function get_the_buttons ( &$wic_query ) {

		$buttons =  '<div id = "wic-list-button-row">'; 
		
			$button_args_main = array(
				'entity_requested'			=> 'office', // entity_requested is not processed, since whole page is for office
				'action_requested'			=> 'new_blank_form',
				'button_label'				=> 'Add New',
			);	
			$buttons .= WIC_Form_Parent::create_wic_form_button ( $button_args_main );

		$buttons .= '</div>';

		return $buttons;
		
    }
    
    protected function format_message( &$wic_query, $header = '' ) {
		$header_message = sprintf ( 'Found %1$s Offices.', $wic_query->found_count );		
		return $header_message;
	}

 }	

