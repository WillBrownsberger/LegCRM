<?php
/*
* class-wic-list-search-log.php
*
*
*/ 

class WIC_List_Search_Log extends WIC_List_Parent {
	/*
	*
	*
	*/
	public static $sort_string = ' is_named desc, favorite desc, search_time desc';
	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'ID','listing_order'=>'-1','list_formatter'=>'',), 
		array('field_slug' =>'favorite','field_label'=>'Favorite','listing_order'=>'10','list_formatter'=>'favorite_formatter',), 
		array('field_slug' =>'search_time','field_label'=>'Search Time','listing_order'=>'20','list_formatter'=>'time_formatter',), 
		array('field_slug' =>'is_named','field_label'=>'','listing_order'=>'24','list_formatter'=>'',), 
		array('field_slug' =>'entity','field_label'=>'Entity','listing_order'=>'30','list_formatter'=>'',), 
		array('field_slug' =>'serialized_search_array','field_label'=>'Search Details','listing_order'=>'40','list_formatter'=>'serialized_search_array_formatter',), 
		array('field_slug' =>'share_name','field_label'=>'Share Name','listing_order'=>'43','list_formatter'=>'share_name_formatter',), 
		array('field_slug' =>'result_count','field_label'=>'Count','listing_order'=>'45','list_formatter'=>'',), 
		array('field_slug' =>'download_time','field_label'=>'Last Download','listing_order'=>'50','list_formatter'=>'download_time_formatter',), 
		array('field_slug' =>'serialized_search_parameters','field_label'=>'Serialized Search Parameters','listing_order'=>'100','list_formatter'=>'',)
		);
	public static $group_by_string = 'search_log.download_time,search_log.entity,search_log.favorite,search_log.ID,search_log.is_named,search_log.result_count,search_log.search_time,search_log.serialized_search_array,search_log.serialized_search_parameters,search_log.share_name';

		
	public function format_entity_list( &$wic_query, $header ) { 
	// set up slimmer form with no headers
		$output = '<div id="wic-post-list"><form id="wic_constituent_list_form" method="POST">';
			$output .= $this->set_up_rows ( $wic_query, true );
			$output .= WIC_Admin_Setup::wic_nonce_field() .
		'</form></div>'; 
		return $output;
   } // close function


	protected function format_rows( &$wic_query, &$fields ) {
		$output = '';
		$line_count = 1;
  	
	   	// use the existing object to generate the list
  		$wic_query->list(); 
		
		// loop through the rows and output a list item for each
		foreach ( $wic_query->list_result as $row_array ) { 

			$row= '';
			$line_count++;
			
			// get row class alternating color marker
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) {
					// showing fields other than ID with positive listing order ( in left to right listing order )
					if ( 'ID' != $field->field_slug && $field->listing_order > 0 ) {
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' ">';
							$row .=  $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$favorite_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'toggle_favorite',
					'title'				=> 1 == $row_array->is_named ? 
						'Cannot unfavorite non-private searches ( those with a Share Name ).' :
						'Click to mark/unmark private favorite searches.',
					'button_class' 		=> 'wic-favorite-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> WIC_Entity_Search_Log::favorite_formatter( $row_array->favorite ),
					'name'					=> 'wic-favorite-button',
					'type'					=>	'button',
			);						
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'title'				=> 'Right click to name search and share it with other users; click to return to search results.',
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button wic-search-log-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' .
				WIC_Form_Parent::create_wic_form_button( $favorite_button_args ) . 
				WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	}
	
	protected function format_message( &$wic_query, $header='' ) {
	
		$header_message = $header . sprintf ( 'Showing most recent %1$s searches and saves.    
				Click to return to results.  Right click to name and share.', $wic_query->found_count );		
		return $header_message;
	}

	protected function get_the_buttons( &$wic_query ) {
	}
	

}	

