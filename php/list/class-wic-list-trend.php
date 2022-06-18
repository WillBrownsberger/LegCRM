<?php
/*
* class-wic-list-trend.php
* 
* issue list with pro-con totals ( too many differences to do within issues list )
*/ 

class WIC_List_Trend extends WIC_List_Parent {
	
	public function format_entity_list( &$wic_query, $header ) {
		
  		// set up form
		$output = '<div id="wic-post-list"><form method="POST">' . 
			'<div class = "wic-post-field-group wic-group-odd">';


		// prepare the custom/mixed list fields for header set up and list formatting
  		$fields = $this->get_list_fields ( $wic_query );
	
		$output .= '<ul class = "wic-post-list">' .  // open ul for the whole list
			'<li class = "pl-odd">' .				 // header is a list item with a ul within it
				'<ul class = "wic-post-list-headers">';				
					foreach ( $fields as $field ) {
							$output .= '<li class = "wic-post-list-header pl-' . $wic_query->entity . '-' . safe_html($field[1]) . '">' . safe_html($field[0]) . '</li>';
						}			
		$output .= '</ul></li>'; // header complete
		$output .= $this->format_rows( $wic_query, $fields ); // format list item rows from child class	
		$output .= '</ul>'; // close ul for the whole list
		
		$output .= 	WIC_Admin_Setup::wic_nonce_field() .
		'</form></div>'; 
		
		return $output;
   } // close function 	
	
	
	protected function get_list_fields ( &$wic_query ) {
		return array (
			array ( 'Issue', 'id' ),
			array ( 'Total', 'total' ),   		
			array ( 'Pro', 'pro' ),
			array ( 'Con', 'con' ),
			array ( 'Categories', 'post_category' ),
  		);	
	}
	
	protected function format_rows( &$wic_query, &$fields ) {

		$output = '';
		$line_count = 1;

		// check current user so can highlight assigned cases
		$current_user_id = get_current_user_id();

		foreach ( $wic_query->result as $row_array ) {
			$row= '';
			$line_count++;
			$row_class = ( 0 == $line_count % 2 ) ? "pl-even" : "pl-odd";
			// add special row class to reflect case assigned status
			$issue = WIC_DB_Access_Issue::get_post_details ( (int) $row_array->id );
			$issue_staff = $issue->issue_staff;
			$issue_status = $issue->follow_up_status;
			$issue_review_date = $issue->review_date;			
			
			if ( 'open' == $issue_status ) {
				$row_class .= " case-open ";
				if ( '' == $issue_review_date ) {	
					$review_date = new DateTime ( '1900-01-01' );
				} else {
					$review_date = new DateTime ( $issue_review_date );					
				}
				$today = new DateTime( current_time ( 'Y-m-d') );
				$interval = date_diff ( $review_date, $today );
				if ( 0 == $interval->invert ) {
					$row_class .= " overdue ";				
				}
			} 

			$row .= '<ul class = "wic-post-list-line">';			
				foreach ( $fields as $field ) { 
					if ( 'id' != $field[1] ) {
							$display_value = $row_array->{$field[1]}; // php 7 conversion{}
						} else {
							$display_value = safe_html( WIC_DB_Access_Issue::format_title_by_id( $row_array->id ) );		
						}
						$row .= '<li class = "wic-post-list-field pl-' . $wic_query->entity . '-' . safe_html($field[1]) . ' "> ';
							$row .=  $display_value ;
						$row .= '</li>';			
					}	

			$row .='</ul>';				
			
			$list_button_args = array(
					'entity_requested'	=> 'issue',
					'action_requested'	=> 'id_search',
					'button_class' 		=> 'wic-post-list-button ' . $row_class,
					'id_requested'			=> $row_array->id,
					'button_label' 		=> $row,				
			);			
			$output .= '<li>' . WIC_Form_Parent::create_wic_form_button( $list_button_args ) . '</li>';	
		} // close for each row
		return ( $output );		
	} // close function 

	public function format_message( &$wic_query, $header='' ) {}

	protected function get_the_buttons ( &$wic_query ) {}

 }	

