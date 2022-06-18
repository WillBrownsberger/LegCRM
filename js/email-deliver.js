/*
*
* email-deliver.js
*
* supports queue tab functions
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
	
	wpIssuesCRM.loadOutboxListeners = function() {

		
		$( "#purge-queue-email-button" ).on( "click", function() {
			wpIssuesCRM.confirm ( 
				purgeQueue,			
				false,
				'<p>Made a big mistake? You can immediately purge all unsent messages and their related activity records.</p>' +
				'<p>You will need to check your sent box to see what messages have already been sent.</p>'

			);
		});		
	}
	
	function purgeQueue () {
		$( "#outbox-ajax-loader" ).show();
		$( "#wic-load-outbox-inner-wrapper" ).html('');
		wpIssuesCRM.ajaxPost( 'email_send', 'purge_mail_queue',  0, '',  function( response ) {
			wpIssuesCRM.loadSelectedPage();
	
		});	
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
