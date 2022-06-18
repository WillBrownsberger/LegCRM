/*
*
* upload-download.js
*
*/
jQuery(document).ready(function($) { 
	
	$( "#wp-issues-crm" ).on ( "initializeWICForm initializeWICSubForm", function () {
		if ( $ ( "#wic-form-upload-download" )[0]  ) { 
			wpIssuesCRM.initializeDownload() 
		}
	});


});
// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {
	
	wpIssuesCRM.initializeDownload = function() {
		//$ ( ".wic-form-button").tooltip();  in contemplation of removal of all tooltips
	}
	
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	