/*
*
* search.js manages front page search box
*
*/
jQuery( document ).ready( function($) { 
	$( "#wic-main-search-box" ).focus().on( 'wicAutocompleteSelect', wpIssuesCRM.selectSearchBox );
});



// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.selectSearchBox = function( event, entityValue, entityType ) {
		if ( -1 != entityValue ) {
			passThroughButton 		= $ ( "#wic_hidden_top_level_form_button" )
			passThroughButton.attr("name", "wic_form_button" );
			passThroughButton.val( entityType + ",id_search," + entityValue );
			passThroughButton.trigger("click");
			$( event.target ). val( '');
		}
	}
	
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	