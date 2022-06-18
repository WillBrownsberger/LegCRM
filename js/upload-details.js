/*
*
* wic-upload-details.js
*
*/
jQuery(document).ready(function($) { 

	// initialize value of the interval timer id -- used in progress tracking of parse
	wpIssuesCRM.parseProgressCheckInterval = -1; 
	
	// set launch of parse progress popup
	$( "#wp-issues-crm" ).on ( "click", "#wic_upload_verify_button", function () {
		wpIssuesCRM.doParseProgressPopup();
		wpIssuesCRM.parseDialogObject.dialog( "option", "title", 'Verifying file parse settings . . .'    );		
		$( "#wic-parse-progress-bar" ).progressbar ( "value", false ); // indeterminate initial checking time
		upload_parms = {
			includes_column_headers : $( "[name=includes_column_headers]" ).prop( "checked" ),
			delimiter 				: $( "[name=delimiter]:checked " ).val(),
			enclosure 				: $( "[name=enclosure]:checked " ).val(),
			escapeChar				: $( "#escape" ).val(),
			max_line_length			: $( "#max_line_length" ).val(),
			max_execution_time		: $( "#max_execution_time" ).val(),
			charset					: $( "#charset" ).val()
		}
		wpIssuesCRM.ajaxPost( 'upload_upload', 'verify_upload',  $( "#ID" ).val(), upload_parms, function( response ) {
			if ( !response.count ) { // upload error condition -- returns message, not an object with .count property
				$( "#post-form-message-box" ).html( response ).addClass ( "wic-form-errors-found" );						
				wpIssuesCRM.parseDialogObject.remove();
			} else if ( 1 == response.count ) {	// check with user if this is intended
				wpIssuesCRM.parseDialogObject.remove(); // progress bar should disappear
				wpIssuesCRM.confirm ( 
					function () {
						wpIssuesCRM.doParseProgressPopup(); // then need to reinit progress bar
						wpIssuesCRM.doParse ( upload_parms, response.row_count );
					},
					function () {
						$( "#post-form-message-box" ).html( 'Experiment with changing the delimiter.' ).addClass ( "wic-form-errors-found" );						
						wpIssuesCRM.parseDialogObject.remove();
					},
					'Are settings right?  Really parse file as having only a single column?' 
				)
			} else {
				wpIssuesCRM.doParse ( upload_parms, response.row_count );
			}
		})
	});
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.loadUploadSubform = function( subform ) {
		wpIssuesCRM.ajaxPost( 'upload', 'load_form',  $( "#ID" ).val(), subform,  function( response ) {
			$( "#upload-form-slot" ).html ( response.form );
			$( "#post-form-message-box").html( '<span id="post-form-message-base">' + response.message + '</span>'); // span used only  upload-set-defaults.js
			$( "#post-form-message-box" ).removeClass ( "wic-form-routine-guidance wic-form-errors-found wic-form-good-news" );
			$( "#post-form-message-box" ).addClass ( response.css_message_level );
			$( "#wp-issues-crm" ).trigger ( "initializeWICSubForm" ); 
		});
	}

	wpIssuesCRM.doParse = function ( upload_parms, countRecords ) {
		$( "#wic-parse-progress-bar" ).progressbar ( "value", 0 ); // now can show as progress bar with defined values
		wpIssuesCRM.parseProgressCheckInterval = window.setInterval ( wpIssuesCRM.parseProgressCheck, 10000, $( "#ID" ).val(), countRecords ); // start progress polling
		wpIssuesCRM.parseDialogObject.dialog( "option", "title", 'Parsing ' + countRecords + ' input records into staging table . . .' )
		wpIssuesCRM.tester = wpIssuesCRM.ajaxPost( 'upload_upload', 'stage_upload',  $( "#ID" ).val(), upload_parms, function( response ) {
			wpIssuesCRM.parseDialogObject.remove();
			wpIssuesCRM.loadUploadSubform( 'map' )
		});
	}


	wpIssuesCRM.parseProgressCheck = function( id, countRecords ) {
		// if progress bar is visible && no error has occurred, check count, if not clear interval
		if ( $('#wic-parse-progress-bar').is(':visible') && 0 == $( ".wic_error_popup" ).length ) {
			wpIssuesCRM.ajaxPost( 'upload_upload', 'get_staging_table_record_count',  id, '',  function( response ) {
				// if still visible and no error after check, update, if not clear interval 
				if ( $('#wic-parse-progress-bar').is(':visible') && 0 == $( ".wic_error_popup" ).length ) {
				 	$('#wic-parse-progress-bar').progressbar( 'value', 100 * response/countRecords);
				 	wpIssuesCRM.parseDialogObject.dialog( "option", "title", 'Parsing ' + countRecords + ' input records into staging table (' + Math.round ( 100 * response/countRecords ) + '%)' ) 	
				} else {
					window.clearInterval ( wpIssuesCRM.parseProgressCheckInterval );
				}
			});
		} else {
			window.clearInterval ( wpIssuesCRM.parseProgressCheckInterval );
		}
	}

	wpIssuesCRM.doParseProgressPopup = function() {

		// open dialog popup
		parseDialog = $.parseHTML ( 
			'<div id="parse-dialog">' +
				'<div id="wic-parse-progress-bar"></div>' +
			'</div>'
		);
		wpIssuesCRM.parseDialogObject = $( parseDialog );
		wpIssuesCRM.parseDialogObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.cancelAll();
				wpIssuesCRM.parseDialogObject.remove();
				$( "#post-form-message-box").html( 'Parse attempt cancelled.  You can retry.' );
				$( "#post-form-message-box" ).removeClass ( "wic-form-routine-guidance	wic-form-errors-found wic-form-good-news" );
				$( "#post-form-message-box" ).addClass ( "wic-form-errors-found" );				
			},
			position: { my: "left top", at: "left top", of: "#wic_upload_verify_button" }, 	 	
			width: 960,
			height: 150,
			buttons: [
				{
					width: 200,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.parseDialogObject.dialog( "close" ); 
					}
				}
			],
			modal: true 
		});
		
		// initialize progress bar within dialog
		$( "#wic-parse-progress-bar").progressbar({
				value: 0
		});
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
