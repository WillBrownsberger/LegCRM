/*
* help.js
*/
jQuery( document ).ready( function($) { 

	$( "#wic_help_button" ).on( "click", function ( event ) {
		 window.open( "https://github.com/WillBrownsberger/LegCRM/wiki" );
		 event.stopImmediatePropagation();
	});
	$( "#wic_manual_button" ).on( "click", function ( event ) {
		 window.open( "https://github.com/WillBrownsberger/LegCRM/wiki" );
		 event.stopImmediatePropagation();
	});

});