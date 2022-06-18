<?php
/*
* class-wic-list-upload.php
*
*
*/ 

class WIC_List_Upload extends WIC_List_Parent {
	/*
	*
	*
	*
	*/
	public static $sort_string = ' upload_time desc ';

	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'Internal Id for Upload','listing_order'=>'0','list_formatter'=>'',), 
		array('field_slug' =>'upload_time','field_label'=>'Upload Time','listing_order'=>'10','list_formatter'=>'',), 
		array('field_slug' =>'upload_by','field_label'=>'Upload User','listing_order'=>'20','list_formatter'=>'issue_staff_formatter',), 
		array('field_slug' =>'upload_file','field_label'=>'','listing_order'=>'25','list_formatter'=>'',), 
		array('field_slug' =>'upload_status','field_label'=>'','listing_order'=>'35','list_formatter'=>'',)
		);

	public static $group_by_string = 'upload.upload_by,upload.upload_time,upload.ID,upload.upload_file,upload.upload_status';


	public function format_entity_list( &$wic_query, $header ) { 
	// set up slimmer form with no headers
		$output = '<div id="wic-post-list"><form id="wic_constituent_list_form" method="POST">';
			$output .= $this->set_up_rows ( $wic_query, true ); 
			$output .= 	WIC_Admin_Setup::wic_nonce_field() .
		'</form></div>'; 
		return $output;
   } // close function


	protected function format_message( &$wic_query, $header = '' ) {}

	protected function get_the_buttons ( &$wic_query ) {}
	
	protected function format_rows( &$wic_query, &$fields ) {
		$output = '';
		$line_count = 1;
  	
	   	// expand the query_results list
  		$wic_query->list(); 
		
		// loop through the rows and output a list item for each
		foreach ( $wic_query->list_result as $row_array ) { 

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			// $control_array['id_requested'] =  $wic_query->post->ID;
			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && 'purged' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				

			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'go_to_current_upload_stage',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	}
	


	
 }	

