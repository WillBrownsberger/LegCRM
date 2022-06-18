/*
*
* upload-match.js
*
*/
jQuery(document).ready(function($) { 
	
	$( "#wp-issues-crm" ).on ( "initializeWICForm initializeWICSubForm", function () {
		if ( $ ( "#wic-form-upload-match" )[0] ) { 
			wpIssuesCRM.initializeMatch() 
		}
	})
	
	.on ( "click", "#wic-upload-match-button", function () {
		wpIssuesCRM.doMatchPopup()
	})

	.on ( "click", "#wic-upload-back-to-map-button", function () {
		wpIssuesCRM.loadUploadSubform( 'map' );
	})

});


// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
		
	var uploadID, uploadParameters, chunkSize, chunkPlan, chunkCount, currentPassPointer, sortedIDs, startingMatchButtonText, matchingInProgress;

	wpIssuesCRM.initializeMatch = function() {
		
		uploadID	 		= 		$( "#ID" ).val();
		uploadParameters 	=		JSON.parse ( $( "#serialized_upload_parameters" ).val() )  
		// set chunk size at 1000 to avoid memory breaks; do not go smaller -- OK, if no progress motion within that.
		chunkSize 			= 		1000;
		// set chunkPlan = number of chunks to get
		chunkPlan 			= 		Math.ceil( uploadParameters.insert_count / chunkSize );

		$( "ul.wic-sortable" ).sortable ( {
			connectWith: "ul",
			dropOnEmpty: true
		});

  		$( "ul.wic-sortable" ).disableSelection();
	}

	wpIssuesCRM.doMatchPopup = function() {
	
		// reset counter for number of times chunks called in recursion
		chunkCount			 = 	0;
		// reset pointer for array of match strategies 
		currentPassPointer = 	0;
	
	
		matchDialog = jQuery.parseHTML ( '<div id="match-dialog" title="Resetting match records . . .">' +
				'<div id = "wic-match-progress-bar"></div>' +
				'<div id = "upload-results-table-wrapper"></div>' + 
			'</div>');
		wpIssuesCRM.matchDialogObject = $( matchDialog );
		wpIssuesCRM.matchDialogObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.cancelAll();				// cancel pending requests
				wpIssuesCRM.matchDialogObject.remove();	// cleanup object
			},
			position: { my: "left top", at: "left top", of: "#wic-upload-match-button" }, 	 	
			width: 1080,
			height: 480,
			buttons: [
				{	
					width: 200,
					text: "Download Errors",
					click: function( event ) {
						if ( $(event.target).is(":button" ) ) {
							$( event.target ).val( 'constituent,staging_table,bad_match,' + uploadParameters.staging_table_name )
						} else {
							$( event.target ).parent().val( 'constituent,staging_table,bad_match,' + uploadParameters.staging_table_name );
						}
						wpIssuesCRM.doMainDownload ( event )
					}
				},
				{
					width: 200,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.matchDialogObject.dialog( "close" ); 
					}
				},
				{
					width: 200,
					text: "Accept Match",
					click: function() {
						wpIssuesCRM.matchDialogObject.dialog( "close" ); 
						wpIssuesCRM.loadUploadSubform( 'set_defaults' );
					}
				}
			],
			modal: true,
		});

		$(".ui-dialog-buttonpane button:contains('Accept')").attr("disabled", true).addClass("ui-state-disabled");
		$(".ui-dialog-buttonpane button:contains('Errors')").attr("disabled", true).addClass("ui-state-disabled");


		$( "#wic-match-progress-bar" ).progressbar({
			value: false
		});

		sortedIDs = $( "#wic-match-list ul" ).sortable( "toArray" ); // populate the ID's array 
		matchingInProgress = 1;
		resetMatchIndicators( sortedIDs ); // note that reset callback includes invocation of actual mapping
		 				  		
	}


	function resetMatchIndicators( sortedIDs ) {
		
		chunkCount = 0;
		currentPassPointer = 0;		
		
		var data = {
			table: uploadParameters.staging_table_name,
			usedMatch: sortedIDs		
		}; 

		wpIssuesCRM.ajaxPost( 'upload_match', 'reset_match',  uploadID, data, function( response ) {
			wpIssuesCRM.matchDialogObject.dialog( "option", "title", response )
			$( "#wic-match-progress-bar" ).progressbar ( "value", 0 );
			matchUpload();
		});
	}
	

	function matchUpload() {
		if ( undefined != sortedIDs[currentPassPointer] ) {
			// reset chunk count
			chunkCount			 = 0;
			// initiate next pass with offset 0
			matchUploadPass (0);
		} else { 
			// create the unmatched table
			$( "#wic-match-progress-bar" ).progressbar ( "value", false );
			$( "#match-button" ).text( "Analyzing . . ." );
			wpIssuesCRM.matchDialogObject.dialog( "option", "title", " . . . identifying unique values remaining unmatched after all passes." )
			analyzeUnmatched (); // after analysis, will close out processing;
		}
	}


	// function is recursive to keep going until all chunks processed
	// chunking match proces to support progress reporting and to limit array size on server
	function matchUploadPass ( offset ) {		

		var matchParameters = {
			"staging_table" : uploadParameters.staging_table_name,
			"offset" : offset,
			"chunk_size" : chunkSize,
			"working_pass" : 	sortedIDs[currentPassPointer] // always defined in this function	
		}
		
		wpIssuesCRM.ajaxPost( 'upload_match', 'match_upload',  uploadID, matchParameters,  function( response ) {
			// calling parameters are: entity, action_requested, id_requested, data object, callback
			chunkCount++;
			$( "#wic-match-progress-bar" ).progressbar ( "value", 100 * chunkCount / chunkPlan );
			var processedTotal = Math.min ( chunkCount * chunkSize, uploadParameters.insert_count );
			progressLegend = 'Processed ' + processedTotal.toLocaleString( 'en-US' )  + ' of ' + uploadParameters.insert_count.toLocaleString( 'en-US' ) + ' records in current pass.'; 
			wpIssuesCRM.matchDialogObject.dialog( "option", "title", progressLegend )
			$( "#upload-results-table-wrapper" ).html( response ); //  + progressLegend );
			if ( chunkCount < chunkPlan ) {
				matchUploadPass ( chunkCount * chunkSize );
			} else {
				// move to next pass or end
				currentPassPointer++;
				matchUpload();
			}
		});
	}
	
	function analyzeUnmatched () {		

		var matchParameters = {
			"staging_table" : uploadParameters.staging_table_name,
		}
		
		wpIssuesCRM.ajaxPost( 'upload_match', 'create_unique_unmatched_table',  uploadID, matchParameters,  function( response ) {
			// calling parameters are: entity, action_requested, id_requested, data object, callback
			wpIssuesCRM.matchDialogObject.dialog( "option", "title", "Test match results for " +  uploadParameters.insert_count.toLocaleString( 'en-US' ) + " input records saved in staging table." );
			$( "#upload-results-table-wrapper" ).html( response ); 
			// close out processing
			wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'matched',  function( response ) {		
				$( "#wic-match-progress-bar" ).hide();
				$(".ui-dialog-buttonpane button:contains('Errors')").attr("disabled", false).removeClass("ui-state-disabled");
				$(".ui-dialog-buttonpane button:contains('Accept')").attr("disabled", false).removeClass("ui-state-disabled");
			});
		});
	}
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	