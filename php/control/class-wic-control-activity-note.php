<?php
/*
* class-wic-control-textarea.php
*
*
*/

class WIC_Control_Activity_Note extends WIC_Control_Textarea {

	public static function create_control ( $control_args ) {

		$control = parent::create_control ( $control_args );
	
		$control.= '<div id="frozen_activity_note_display"></div>';
			
		return ( $control );

	}	
}

