<?php
/*
* class-wic-list-issue.php
*
*
*/ 

class WIC_List_Issue extends WIC_List_Parent {
	
	public static $sort_string = ' post_title ';
	public static $list_fields = array( 
		array('field_slug' =>'ID','field_label'=>'ID','listing_order'=>'1','list_formatter'=>'',), 
		array('field_slug' =>'post_title','field_label'=>'Title','listing_order'=>'5','list_formatter'=>'',), 
		array('field_slug' =>'follow_up_status','field_label'=>'Status','listing_order'=>'10','list_formatter'=>'follow_up_status_options',), 
		array('field_slug' =>'review_date','field_label'=>'Review','listing_order'=>'20','list_formatter'=>'review_formatter',), 
		array('field_slug' =>'issue_staff','field_label'=>'Staff','listing_order'=>'30','list_formatter'=>'issue_staff_formatter',), 
		array('field_slug' =>'wic_live_issue','field_label'=>'Email Rule','listing_order'=>'40','list_formatter'=>'wic_live_issue_options',), 
		array('field_slug' =>'post_category','field_label'=>'Category','listing_order'=>'50','list_formatter'=>'category_formatter',)
		);

	public static $group_by_string = 'issue.wic_live_issue,issue.post_category,issue.post_title,issue.ID,issue.follow_up_status,issue.issue_staff,issue.review_date';

	/*
	* return from wp_query actually has the full post content already, so not two-stepping through lists
	*
	*/



	protected function format_rows( &$wic_query, &$fields ) {

		$output = '';
		$line_count = 1;

		// check current user so can highlight assigned cases
		$current_user_id = get_current_user_id();

		foreach ( $wic_query->result as $row_array ) {

			$row= '';
			$line_count++;
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";

			if ( 'open' == $row_array->follow_up_status ) {
				$row_class .= " case-open ";
				if ( '' == $row_array->review_date ) {	
					$review_date = new DateTime ( '1900-01-01' );
				} else {
					$review_date = new DateTime ( $row_array->review_date );					
				}
				$today = new DateTime( current_time ( 'Y-m-d') );
				$interval = date_diff ( $review_date, $today );
				if ( 0 == $interval->invert ) {
					$row_class .= " overdue ";				
				}
			}	

			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) { 
					if ( 'ID' != $field->field_slug && 0 < $field->listing_order ) {
						// eliminated closed option value in version 3.5, but some issues may have this value set 
						if ( 'wic_live_issue' == $field->field_slug && 'closed' == $row_array->{$field->field_slug} ) {
								$row_array->{$field->field_slug} = '';
						}
						$display_value = $this->format_item ( $wic_query->entity, $field->list_formatter, $row_array->{$field->field_slug} ) ;		
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . $field->field_slug . ' "> ';
							$row .=  $display_value ;
						$row .= '</li>';			
					}	
				}
			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> $wic_query->entity,
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->ID,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
			}
		return ( $output );		
	} // close function 

	protected function format_message( &$wic_query, $header='' ) {
		return $header . sprintf ( 'Found %1$s issues with activities meeting activity/constituent/issue search criteria.', $wic_query->found_count );		
	}
 
   // the top row of buttons over the list -- down load button and change search criteria button
  	protected function get_the_buttons( &$wic_query ) { 
		$user_id = get_current_user_id();
		$buttons = '';

		if ( isset ( $wic_query->search_id ) ) { 
			$buttons .= WIC_List_Parent::back_to_search_form_button ( $wic_query, true );
			$buttons .= WIC_List_Parent::hidden_search_id_control( $wic_query );
			$buttons .= WIC_List_Parent::search_inspection_button( $wic_query );
			$buttons .= WIC_List_Parent::search_name_control ( $wic_query );
		}
		
		return ( $buttons );
	}
 

 }	

