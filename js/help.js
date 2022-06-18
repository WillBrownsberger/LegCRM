/*
* help.js
*/
jQuery( document ).ready( function($) { 

	$( "#wic_help_button" ).on( "click", function ( event ) {
		 window.open( "https://wordpress.org/support/plugin/wp-issues-crm" );
		 event.stopImmediatePropagation();
	});
	$( "#wic_manual_button" ).on( "click", function ( event ) {
		 window.open( "http://wp-issues-crm.com/" );
		 event.stopImmediatePropagation();
	});

});