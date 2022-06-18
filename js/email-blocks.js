/*
*
* email-blocks.js
*
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.loadBlockListeners = function () {
		// bind listener to block wrapper (already loaded);
		$ ( "#wic-load-blocks-inner-wrapper" )
			.on ( "click", ".wic-delete-block-button", function () {
				deleteFilter ( this );
			});
	}

	// regular box load
	wpIssuesCRM.loadBlockList = function () {
		$( "#blocks-ajax-loader" ).show();
		wpIssuesCRM.ajaxPost( 'email_block', 'load_block_list',  0, '',  function( response ) {
			$ ( "#blocks-ajax-loader" ).hide();
			$ ( "#wic-load-blocks-inner-wrapper" ).html( response )
 		});		
	}

	function deleteFilter( deleteButton ) {
		var deleteVal =  $( deleteButton ).val()
		// delete the line before doing the actual delete -- user can recover by refreshing if error
		// better to allow rapid clicks (and also not encourage double clicks if delay)
		$( deleteButton ).parent().parent().remove();
		wpIssuesCRM.ajaxPost( 'email_block', 'delete_address_filter',  deleteVal, '',  function( response ) {
 		});		
	}
	
} ( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
