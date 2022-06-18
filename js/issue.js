/*
*
*	issue.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on ( 
		'change', 
    	'form#wic-form-issue',
		function (e) { 
			// don't trigger change on file upload
			if ( undefined === e.originalEvent || !$( e.originalEvent.target.parentElement).hasClass('moxie-shim') ) { 
				wpIssuesCRM.formDirty = true;
				wpIssuesCRM.setChangedFlags(e);
			}
		}
	)

	.on( "change",  "#issue_staff", function()  {
		if ( $ ( this ).val() > '' ) {
			wpIssuesCRM.setVal( $( "#follow_up_status" )[0], "open" )
		}
	})
	// dlete button
	.on ("click", "#wic-issue-delete-button", function ( event ) { 
		var ID = $( event.target ).val();
		wpIssuesCRM.confirm(
			function () {
				wpIssuesCRM.ajaxPost( 'issue', 'hard_delete', ID, 'dummy' ,  function( response ) {
					$( "#post-form-message-box" ).text ( response.reason );
					$( "#post-form-message-box" ).addClass ( 'wic-form-errors-found' )
					if ( response.deleted ) {
						$( "#wic-form-issue :input" ).prop( "disabled", true );
						$( ".dashicons-edit").on( "click", function ( event ) {
							event.stopImmediatePropagation()
						});
					}
				});
			},
			false,
			$( "#issue_delete_shell")[0].outerHTML.replace ( /issue_delete_shell/, 'issue_delete_dialog' )
		)
	}) // end onclick delete button

	// set up listener to trigger form initialization
	.on ( "initializeWICForm", function () { 
		wpIssuesCRM.initializeIssueForm();
	});
	
	// initialize directly (normally ajax triggered) in case access by get
	wpIssuesCRM.initializeIssueForm();

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.initializeIssueForm = function () {
		if (  $( "#wic-form-issue" )[0] ) { 
			wpIssuesCRM.parentFormEntity = 'issue';
			wpIssuesCRM.loadActivityArea( true );
		}

		if ( !wpIssuesCRMSettings.canViewOthersAssigned ) {
			$( "#wic-inner-field-group-issue_management input").prop("disabled", true)
		}


	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
