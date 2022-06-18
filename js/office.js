/*
*
*	option-group.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

		
	$( "#wp-issues-crm" ).on ( 
		"change deletedWICRow ", 
		'form#wic-form-office',
		function (e) {
			wpIssuesCRM.formDirty = true;
			wpIssuesCRM.setChangedFlags(e);
		}
	
	)
	
	/*set up listener to trigger form initialization
	.on ( "initializeWICForm", function () { 

	 
	}) 

	
	$( "#wp-issues-crm" ).trigger ( "initializeWICForm" );*/

});

// anonymous function creates namespace object
/*( function( wpIssuesCRM, $, undefined ) {

	
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
*/