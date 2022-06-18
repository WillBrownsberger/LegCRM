/*
*
*	owner.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

		
	$( "#wp-issues-crm" ).on ( 
		"change", 
		'form#wic-form-owner',
		function (e) {
			wpIssuesCRM.formDirty = true;
			wpIssuesCRM.setChangedFlags(e);
		}
	
	)
	

	$( "#wp-issues-crm" ).trigger ( "initializeWICForm" );

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

/* no special function */

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
