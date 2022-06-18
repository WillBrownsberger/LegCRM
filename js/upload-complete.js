/*
*
* wic-complete.js
*
*/

jQuery(document).ready(function($) { 
	
	// this button lives on the map form!
	$( "#wp-issues-crm" ).on ( "click", "#wic-upload-express-button", function () {
	
		wpIssuesCRM.doExpressPopup();
		// do progress bar pop-up with value false while create dummy match table
		// do reset with parm pointing it to unmatch version of create unique as next
		// load the complete upload dialog
	})

	.on ( "click", "#wic-upload-complete-button", function () {
		wpIssuesCRM.doCompletePopup()
	})

	.on ( "click", "#wic-upload-restart-button", function() {
		uploadStatus		= 	$( "#upload_status" ).val();
		express				= 	uploadStatus.indexOf ( 'express' ) > -1;
		if ( express ) {
			wpIssuesCRM.doExpressPopup();
		} else {
			wpIssuesCRM.doCompletePopup()
		}
	});

});

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
	
	var 	express, 
			uploadID, uploadParameters, matchResults, defaultDecision, finalResults, 
		 	chunkSize, chunkPlan, chunkCount, currentPhasePointer, currentPhaseArray, currentPhase, 
		 	insertCount, totalMatched, totalUnmatched, uploadCancelled;

	wpIssuesCRM.doCompletePopup = function() {
		express = false;
		uploadCancelled = false;
		wpIssuesCRM.ajaxPost( 'upload', 'load_form',  $( "#ID" ).val(), 'complete',  function( response ) {
			completeDialog = $.parseHTML ( '<div id="complete-dialog" title="' + response.message + '">' + response.form + '</div>');
			wpIssuesCRM.completeDialogObject = $( completeDialog );
			wpIssuesCRM.completeDialogObject.dialog({
				appendTo: "#wp-issues-crm",
				closeOnEscape: true,
				close: function ( event, ui ) {
					wpIssuesCRM.completeDialogObject.remove();						// cleanup object
					},
				position: { my: "left top", at: "left top", of: $("#wic-upload-complete-button").length > 0 ? "#wic-upload-complete-button" : "#wic-upload-backout-button"  }, 	
				width: 960,
				height: 480,
				buttons: [
					{
						width: 200,
						text: "Cancel",
						click: function() {
							wpIssuesCRM.completeDialogObject.dialog( "close" );
						}
					},
					{
						width: 200,
						text: "Finish Upload",
						click: function() {
							wpIssuesCRM.completeDialogObject.dialog ( "option", "buttons", 
							 [
								{
									width: 200,
									text: "Cancel",
									click: function() {
										uploadCancelled = true;
										updateProgress( 'Cancelling Upload' );
										$( "#wic-finish-progress-bar" ).progressbar ( "value", false );			 
										$(".ui-dialog-buttonpane button:contains('Cancel')").attr("disabled", true).addClass("ui-state-disabled");
									}
								}
							]);
							$( "#upload-game-plan").remove();
							$( "#wic-finish-progress-bar" ).show();
							$( "#upload-progress-legend" ).html( "<h3>Starting upload . . . </h3>" );

							wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'started',  function( response ) {
								doUpload();
							});

						}
					}
				],
				modal: true
			});

			wpIssuesCRM.initializeUploadComplete(); 

		}); // close response to load_form
			
	} // close doCompletePopup


	wpIssuesCRM.doExpressPopup = function() {

		express = true; // controls behavior of upload functions
		uploadCancelled = false;
		// need to initialize necessary parameters as if had loaded from upload complete form (if user ends up going that route, will be reinitialized)
		uploadID	 			= $( "#ID" ).val();
		uploadParameters 		= JSON.parse ( $( "#serialized_upload_parameters" ).val() ) ; 
		currentPhaseArray 		= [ 'save_new_constituents', 'update_constituents' ];
		currentPhasePointer		= 0;
		insertCount 			= uploadParameters.insert_count;
		totalUnmatched 			= insertCount;

		// now proceed to dialog
		expressDialog = $.parseHTML ( 
			'<div id="express-dialog" title="Express upload -- bypass validation, matching and setting of defaults.">' +
				'<div id="wic-finish-progress-bar"></div>' +
				'<div id="progress-legend-wrapper"><div id="upload-progress-legend"></div></div>' +
			'</div>'
		);
		wpIssuesCRM.expressDialogObject = $( expressDialog );
		wpIssuesCRM.expressDialogObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: false,
			close: function ( event, ui ) {
				wpIssuesCRM.expressDialogObject.remove();						// cleanup object
				},
			position: { my: "left top", at: "left top", of: $("#wic-upload-validate-button").length > 0 ? "#wic-upload-validate-button" : "#wic-upload-backout-button"  }, 	
			width: 960,
			height: 150,
			buttons: [
				{
					width: 200,
					text: "Cancel",
					click: function() {
						uploadCancelled = true;
						updateProgress( 'Cancelling Upload' );
						$( "#wic-finish-progress-bar" ).progressbar ( "value", false );			 
						$(".ui-dialog-buttonpane button:contains('Cancel')").attr("disabled", true).addClass("ui-state-disabled");
					}
				}
			],
			modal: true
		});

		var expressTag = express ? '_express' : '';
		wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'started' + expressTag,  function( response ) {
			// initialize progress bar
			$( "#wic-finish-progress-bar" ).show();
			$( "#wic-finish-progress-bar" ).progressbar({
				value: 0
			});
			doUpload();
		});
		
	} // close doexpressPopup



	wpIssuesCRM.initializeUploadComplete = function (){

		// uploadID, upload parameters populated on upload (must lock back to pop-up by referring to form ID)
		uploadID	 		= 		$( "#wic-form-upload-complete #ID" ).val();
		uploadParameters 	=		JSON.parse ( $( "#wic-form-upload-complete #serialized_upload_parameters" ).val() ) ; 
		matchResults		=		JSON.parse ( $( "#wic-form-upload-complete #serialized_match_results" ).val() );
		defaultDecisions	= 		JSON.parse ( $( "#wic-form-upload-complete #serialized_default_decisions" ).val() );	
		
		// set up work phase array based on defaultDecisions
		currentPhaseArray = [];
		// if selected, plan to save new issues
		if ( defaultDecisions.create_issues ) { currentPhaseArray.push( 'save_new_issues' ); }		
		// note that default settings will not allow no save and no update -- otherwise, no action taken: will be doing one, the other or both
		// in case of saves, need to do updates afterwards so check whether doing straight updates or not in the update routine
		if ( defaultDecisions.add_unmatched ) { currentPhaseArray.push( 'save_new_constituents' ); }
 		// always do updates to complete saves; logic excludes non-saves		
		currentPhaseArray.push( 'update_constituents' );
		currentPhasePointer = 0;


		// get totals for later use
		totalMatched = 0;
		totalUnmatched = 0;
		insertCount = uploadParameters.insert_count;
		for ( var phase in matchResults ) {
			totalMatched += Number( matchResults[phase].matched_with_these_components );
			totalUnmatched += Number( matchResults[phase].unmatched_unique_values_of_components );
		}

		$( "#wic-finish-progress-bar" ).progressbar({
			value: 0
		});

	}


	function doUpload() { 

		currentPhase = currentPhaseArray[currentPhasePointer];

		if ( undefined != currentPhase ) {
			// reset chunk count
			switch ( currentPhase ) {
				case "save_new_issues":
					$( "#wic-finish-progress-bar" ).progressbar ( "value", false );
					// resetting chunk parms arbitrarily just so variables are defined -- these values are ignored in this phase, not chunked
					resetChunkParms ( 1000 );
					break;
				case "save_new_constituents":
					$( "#wic-finish-progress-bar" ).progressbar ( "value", 0 );	
					// totalUnmatched should equal total number of records on unmatched version of staging_table
					resetChunkParms ( totalUnmatched );
					break;
				case "update_constituents":	
					$( "#wic-finish-progress-bar" ).progressbar ( "value", 0 );
					// the update_constituents routine processes the staging table without any where clauses
					// so, need to go with the insertCount in setting the chunk plan, even if will bypass some records
					resetChunkParms ( insertCount );
					break;					
			}
			// initiate the recursion
			finalUploadPhase ( 0 );
		} else { 
			// wrap up!
			var expressTag = express ? '_express' : '';
			wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'completed' + expressTag,  function( response ) {
				wpIssuesCRM.loadUploadSubform ( 'download' ); 
				wpIssuesCRM.formDirty = 0; // was set dirty on file ready to parse
				workingPopup = express ? wpIssuesCRM.expressDialogObject : wpIssuesCRM.completeDialogObject;
				workingPopup.dialog( "close" );
			});
		}
	}


	// function is recursive to keep going until all chunks processed
	// chunking match process to support progress reporting and to limit array size on server
	function finalUploadPhase ( offset ) {		

		var completeParameters = {
			"staging_table" : uploadParameters.staging_table_name,
			"offset" : offset,
			"chunk_size" : chunkSize,
			"phase" : 	currentPhase, // always defined in this function	-- suffix of method name in class-wic-db-access-upload.php
			"express" : express
		}

		var progressLegend = '';
		var totalToShow = 0;

		// calling parameters are: entity, action_requested, id_requested, data object, callback
		wpIssuesCRM.ajaxPost( 'upload_complete', 'complete_upload',  uploadID, completeParameters,  function( response ) {
			// when hit cancel button, wait for the transaction to come back before actually beginning to reverse
			// otherwise, last chunk can hit after deletion
			if ( uploadCancelled ) {
				if ( express ) {
					updateProgress ( '  Reversing upload!' );
					var data = {
						table: uploadParameters.staging_table_name,
						express: express
					}; 
					wpIssuesCRM.ajaxPost( 'upload_regrets', 'backout_new_constituents', uploadID, data,  function( response ) {		
						wpIssuesCRM.loadUploadSubform ( 'map' ); 
						wpIssuesCRM.expressDialogObject.dialog( "close" );
					});	
				} else {
					wpIssuesCRM.loadUploadSubform ( 'download' ); 
					wpIssuesCRM.completeDialogObject.dialog( "close" );				
				}
				return false;
			}
			// update final results object with response			
			finalResults = response;
			
			// post results to display table			
			if ( ! express ) {
				for ( var finalResult in finalResults  ) { // the global final results array
					$ ( "#wic-form-upload-complete #" + finalResult ).text( finalResults[finalResult] );
				}
			}
			
			// essentially doing three different flavors of the call back function, one for each phase;
			var processedTotal = Math.min ( ( chunkCount + 1 ) * chunkSize, insertCount ); 
			switch ( currentPhase ) {
				case "save_new_issues":
					updateProgress ( 'Completed new issue insertion phase.' ); 
					// new issues is single pass process, so move pointer to next phase when done
					currentPhasePointer++;
					doUpload();
					break;
				case "save_new_constituents":
					// always move chunk count and progress bar
					chunkCount++;
					$( "#wic-finish-progress-bar" ).progressbar ( "value", 100 * chunkCount / chunkPlan );
					// if more to do, show interim legend and do recursion
					if ( chunkCount < chunkPlan ) {
						updateProgress ( '. . . added ' + processedTotal.toLocaleString( 'en-US' )  
							+ ' of ' + totalUnmatched.toLocaleString( 'en-US' ) + ' records in add new constituent base records phase.' ); 
						finalUploadPhase ( chunkCount * chunkSize );
					// otherwise show done legend and go to next phase
					} else {
						updateProgress ( 'Completed add of ' + totalUnmatched.toLocaleString( 'en-US' ) + ' unique records in add new constituents phase.' ); 
						// move to next phase
						currentPhasePointer++;
						doUpload();
					}	
					break;				
				case "update_constituents":	 			
					// always move chunk count and progress bar
					chunkCount++;
					$( "#wic-finish-progress-bar" ).progressbar ( "value", 100 * chunkCount / chunkPlan );
					// if more to do, show interim legend and do recursion 
					if ( chunkCount < chunkPlan ) {
						updateProgress ( '. . . processed ' + processedTotal.toLocaleString( 'en-US' )  
							+ ' of ' + insertCount.toLocaleString( 'en-US' ) + ' records in staging table for possible updates and constituent details.' ); 
						finalUploadPhase ( chunkCount * chunkSize );
					// otherwise show done legend and go to next phase						
					} else {
						updateProgress( 'Completed all upload processing.' ); 
						// move to next phase
						currentPhasePointer++;
						doUpload();
					}
					break;
			}
		});
	}
	


	// set/reset global variables for new phase based on table size
	function resetChunkParms( totalRecords ){
		// set chunk size at 1000 for larger files to avoid memory breaks; don't bother to set lower in smaller files to achieve motion effect in the progress bar
		chunkSize 	= 1000
		// set chunkPlan = number of chunks to get
		chunkPlan 	= Math.ceil( totalRecords / chunkSize );
		// set a counter for number of times chunks called in recursion
		chunkCount	= 0;
	}

	function updateProgress ( progressLegend ) {
		if ( express ) {
			wpIssuesCRM.expressDialogObject.dialog( "option", "title", 'Running express. ' + progressLegend )
		} else {
			$( "#upload-progress-legend" ).html( '<h3>' + progressLegend + '</h3>' ); 
		}
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	