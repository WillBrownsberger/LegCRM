/*
*
* upload-validate.js
*
*/
jQuery(document).ready(function($) { 

	// note that this button appears on the map form
	$( "#wp-issues-crm" ).on ( "click", "#wic-upload-validate-button", function () {
		wpIssuesCRM.doValidatePopup()
	})
	
	.on ( "click", "#wic_back_to_parse_button", function() {
		wpIssuesCRM.loadUploadSubform( '' );
	});

});


// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	var uploadID, uploadParameters, chunkSize, chunkPlan, chunkCount;

	wpIssuesCRM.doValidatePopup = function() {
		wpIssuesCRM.ajaxPost( 'upload', 'load_form',  $( "#ID" ).val(), 'validate',  function( response ) {
			validationDialog = $.parseHTML ( '<div id="validation-dialog" title="Resetting validation indicators">' + response.form + '<iframe id="wic-download-frame" style="display:none"></iframe>' + '</div>');
			wpIssuesCRM.validationDialogObject = jQuery( validationDialog );
			wpIssuesCRM.validationDialogObject.dialog({
				appendTo: "#wp-issues-crm",
				closeOnEscape: true,
				close: function ( event, ui ) {
					wpIssuesCRM.validationDialogObject.remove();						// cleanup object
					},
				position: { my: "left top", at: "left top", of: "#wic-upload-validate-button" }, 	
				width: 960,
				height: 480,
				buttons: [
					{
						width: 200,
						text: "Download Errors",
						click: function( event ) {
							if ( $(event.target).is(":button" ) ) {
								$ ( event.target ).val( 'constituent,staging_table,validate,' + uploadParameters.staging_table_name )
							} else {
								$ ( event.target ).parent().val( 'constituent,staging_table,validate,' + uploadParameters.staging_table_name )
							}
							wpIssuesCRM.doMainDownload ( event )
						} 
					},
					{
						width: 200,
						text: "Cancel",
						click: function() {
							wpIssuesCRM.validationDialogObject.dialog( "close" );
						}
					},
					{
						width: 200,
						text: "Accept Map",
						click: function() {
							wpIssuesCRM.validationDialogObject.dialog( "close" ); 
							wpIssuesCRM.loadUploadSubform( 'match' )
						}
					}
				],
				modal: true,
			});

			$(".ui-dialog-buttonpane button:contains('Accept'),.ui-dialog-buttonpane button:contains('Download') ").attr("disabled", true).addClass("ui-state-disabled");
	
			uploadID	 			= 		$( "#ID" ).val();
			// uploadParameters set before dialog instantiation so can use in button definition.
			uploadParameters = JSON.parse ( $( "#serialized_upload_parameters" ).val() )  
			// set chunk size at 1000 for larger files to avoid memory breaks; set lower in smaller files to achieve motion effect in the progress bar
			chunkSize = Math.min( 1000, Math.floor( uploadParameters.insert_count / 10 ) );	
			// make sure that chunksize doesn't floor to too small for small files
			chunkSize = Math.max( 100, chunkSize );
			// set chunkPlan = number of chunks to get
			chunkPlan = Math.ceil( uploadParameters.insert_count / chunkSize );
			// set a counter for number of times chunks called in recursion
			chunkCount = 0;
	
	
			$( "#wic-validate-progress-bar" ).progressbar({
				value: 0
			});

			$( "#wic-validate-progress-bar" ).progressbar ( "value", false );
			$( "#wic-validate-progress-bar" ).show();
			resetValidationIndicators(); // note that reset callback includes invokation of validation				  		
				


		}); // close response to load_form
			
	} // close doValidatePopup

	
	// function is recursive to keep going until all chunks processed
	// chunking validation to support progress reporting and to limit array size on server
	function validateUpload( offset ){

		var validationParameters = {
			"staging_table" : uploadParameters.staging_table_name,
			"offset" : offset,
			"chunk_size" : chunkSize		
		}
		wpIssuesCRM.ajaxPost( 'upload_validate', 'validate_upload',  uploadID, validationParameters,  function( response ) {
			if (  wpIssuesCRM.validationDialogObject.hasClass( "ui-dialog-content" )) { // make sure the window hasn't been closed while waiting for reply
				// calling parameters are: entity, action_requested, id_requested, data object, callback
				chunkCount++;
				$( "#wic-validate-progress-bar" ).progressbar ( "value", 100 * chunkCount / chunkPlan );
				if ( chunkCount < chunkPlan ) {
					progressLegend = ' . . . validated ' + ( chunkCount * chunkSize ).toLocaleString( 'en-US' )  + ' of ' + uploadParameters.insert_count.toLocaleString( 'en-US' ) + ' records.'; 
					wpIssuesCRM.validationDialogObject.dialog( "option", "title", progressLegend )
					$( "#validation-results-table-wrapper" ).html( response );
					validateUpload ( chunkCount * chunkSize );
				} else {
					progressLegend = 'Validated ' + uploadParameters.insert_count.toLocaleString( 'en-US' ) + ' records -- done.';
					wpIssuesCRM.validationDialogObject.dialog( "option", "title", progressLegend )
					$( "#validation-results-table-wrapper" ).html( response);
					wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'validated',  function( response ) {		
						$( "#wic-validate-progress-bar" ).hide();
						$(".ui-dialog-buttonpane button:contains('Accept'),.ui-dialog-buttonpane button:contains('Download')").attr("disabled", false).removeClass("ui-state-disabled");
					});		
				}
			}
		});
	}
	
	function resetValidationIndicators() { 
		wpIssuesCRM.ajaxPost( 'upload_validate', 'reset_validation',  uploadID, uploadParameters.staging_table_name,  function( response ) {
			wpIssuesCRM.validationDialogObject.dialog( "option", "title", response )
			$( "#wic-validate-progress-bar" ).progressbar ( "value", 0 );
	  		// start validation at 0 offset
	  		validateUpload( 0 );
		});
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	