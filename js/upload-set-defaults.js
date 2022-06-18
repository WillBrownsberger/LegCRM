/*
*
* upload-set-defaults.js
*
*/

jQuery(document).ready(function($) { 

	$( "#wp-issues-crm" ).on ( "initializeWICSubForm initializeWICForm", function () {
		if ( $ ( "#wic-form-upload-set-defaults" )[0] ) { 
			wpIssuesCRM.initializeDefaults() 
		}
	})

	.on ( "click", "#wic-upload-back-to-match-button", function () {
		wpIssuesCRM.loadUploadSubform( 'match' );
	})

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	// processing variables
	var columnMap, defaultDecisions, initialUploadStatus, matchResults, saveHeaderMessage, uploadID, uploadParameters,
		validMatched, validUnique, controlsArray, errorsArray;

	// validation variables		
	var addressMapped, phoneMapped, emailMapped, titleMapped, activityMapped, activityIssueMapped, issueMapped, issueTitleColumn, issueContentColumn,  hidegroup;
	
	// for use with us zip code validation if set
	var regPostalCode = new RegExp("^\\d{5}(-\\d{4})?$");

	/*
	*
	* in initialization function, make all choices and all setup possible to make without knowing user input
	*
	*/
	wpIssuesCRM.initializeDefaults = function () { 

		// set up presumptions that fields not mapped -- reassess on each return to form		
		addressMapped 		= false;
		phoneMapped 		= false;
		emailMapped 		= false;
		titleMapped 		= false;
		activityMapped 		= false;
		activityIssueMapped = false;
		issueMapped			= false;
		issueTitleColumn 	= '';
		issueContentColumn 	= '';
		hidegroup 			= '';

		// uploadID, upload parameters, column map and match results populated in prior staps
		uploadID	 		= 		$( "#wic-form-upload-set-defaults #ID" ).val();
		uploadParameters 	=		JSON.parse ( $( "#serialized_upload_parameters" ).val() ) ; 
		columnMap			=		JSON.parse ( $( "#serialized_column_map" ).val() ) ; 
		matchResults		=		JSON.parse ( $( "#serialized_match_results" ).val() ) ;
		// will be unpopulated on first time through this stage -- need to handle further below
		defaultDecisions	= 		JSON.parse ( $( "#serialized_default_decisions" ).val() ) > '' ? 
			JSON.parse ( $( "#serialized_default_decisions" ).val() ) : {};

		// getting all form elements holding displayed values (including selectmenu)
		// note that other elements may become hidden as a result of logic, but array will remain constant
		// note that must limit :input with the main form because the uploader creates an html5 input for the upload button 
		// for issue, carry the display value so can reinsert if unavailable (e.g. closed) when return to upload
		controlsArray =  $ ( "#wic-form-upload-set-defaults :input" )
				.filter ( ".wic-selectmenu-input, .issue .wic-selectmenu-input-display, .wic-input-checked, .city, .zip, .activity-date " );
		// listen to save all updates to default options -- trigger change on autocomplete after widget processing
		controlsArray.change ( function( event ) {
			recordDefaultDecisions();
			decideWhatToShow();
		});

		saveHeaderMessage = $ ( "#post-form-message-base" ).text();	

		// get and save totals
		validMatched = 0;
		validUnique  = 0;
		for ( var matchSlug in matchResults  ) { 
			validMatched +=  matchResults[matchSlug].matched_with_these_components;
			validUnique  +=  Number( matchResults[matchSlug].unmatched_unique_values_of_components ) ;
				// this field has type string because of ancestry as a literal ? for display purposes
		}	

		/*
		* process column map and hide fields already mapped
		* set up flags for entities and fields that are mapped for use in validating
		*/
		for ( var inputColumn in columnMap  ) {
			
			// hide controls for fields that have already been mapped
			var mappedField = columnMap[inputColumn].field; 
			if ( undefined != mappedField ) { 
				if ( 'issue' == mappedField || 'post_title' == mappedField  ) {
					$( "#wic-control-issue" ).hide();		
				} else {
					hideGroup =  '#wic-control-' + mappedField.replace ( '_', '-' );
					$( hideGroup ).hide();				
				}
			}
			
			// set flags for entities/fields are mapped	
			if ( 'address' == columnMap[inputColumn].entity ) {
				addressMapped = true; // any address field			
			} 
			if ( 'phone_number' == mappedField ) {
				phoneMapped = true;			
			}
			if ( 'email_address' == mappedField ) {
				emailMapped = true;			
			}
			if ( 'activity' == columnMap[inputColumn].entity ) { 
				activityMapped = true; // any activity	field 		
			} 
			if ( 'issue' == columnMap[inputColumn].entity ) {
				issueMapped = true; // any issue field 		
			} 
			if ( 'issue' == mappedField ) {
				activityIssueMapped = true;	// the actual numeric issue code (which is an activity field );		
			}
			if ( 'post_title' == mappedField ) {
				titleMapped = true; 
				issueTitleColumn = inputColumn;	
			}
			if ( 'post_content' == mappedField ) {
				issueContentColumn = inputColumn;			
			}
		}

		// if no activity fields remain, do not show activity group
		if ( 0 == $( '#wic-inner-field-group-activity div[id^="wic-control-"]:visible ' ).length ) {
			$( "#wic-field-group-activity" ).hide();
		}

 		// add the unselected val for issue since it doesn't exist and make it the default
		var $issue = $( "#issue" )
		wpIssuesCRM.setVal( $issue[0], '', ' -- No Issue Selected' );
		// populate form with saved values or initialize with some defaults
		// if user has already set defaults, retrieve values 
		if ( ! $.isEmptyObject ( defaultDecisions ) ) { 
			controlsArray.each ( function ( index ) {
				elementID 		= $( this ).attr( "id" ) 
				// handle checkboxes with prop setting
				if ( 'checkbox' == $( this ).attr( "type" ) ) {
				 $( this ).prop ( "checked", defaultDecisions[ $( this ).attr( "id" ) ] )
				// special handle issues field, because may come in with missing option if post closed
				} else {
					wpIssuesCRM.setVal( 
						this, 
						defaultDecisions[ elementID ], 
						'issue' == elementID ? defaultDecisions[ 'issue-selectmenu-display' ] : ''
					);
				}
			});
		// if user has not previously set defaults, make some recommendations
		} else {
			$ ( "#update_matched" ).prop ( "checked", true );
			$ ( "#add_unmatched" ).prop ( "checked", true );
			$ ( "#protect_identity" ).prop ( "checked", false );
			$ ( "#protect_blank_overwrite" ).prop ( "checked", true );
		}	

		// enable/set necessary values for add update choices (may change previous recommendations)
		// the null case -- kill the form except the back button
		if ( 0 == validMatched && 0 == validUnique ) {
			$ ( "#post-form-message-box" ).html ( "No records found with fields needed for selected match combinations.  Go back one step and redo match OR go back two steps and <em>Run Express</em> (bypasses matching/dup-checking)." );
			$ ( "#post-form-message-box" ).addClass ( "wic-form-errors-found" );			
			$ ( "#wic-form-upload-set-defaults :input" ).not ( "#wic-upload-back-to-match-button" ).prop ( "disabled", true );
			return; // no more setup in this case.
		} else {
			// if no matched constituents, disable update choices
			if ( 0 == validMatched ) {
				$ ( "#update_matched" ).prop ( "checked", false );
				$ ( "#update_matched" ).prop ( "disabled", true );
				$ ( "#protect_identity" ).prop ( "disabled", true );
				$ ( "#protect_blank_overwrite" ).prop ( "disabled", true );
			// if nothing to add, disable the add choice
			} else if ( 0 == validUnique ) {
				$ ( "#add_unmatched" ).prop ( "checked", false );
				$ ( "#add_unmatched" ).prop ( "disabled", true );
			}
		}

		// if title mapped and not overridden by issue, create new issue table in form -- 
		// will be hidden later if no new issues or if default issue is set
		if ( titleMapped && ! activityIssueMapped ) {
			// disable input so can't get to good to go status before look up of issues is complete
			$ ( "#wic-form-upload-set-defaults :input" ).prop( "disabled", true ); 
			// create div to receive results
			$ ( "#wic-inner-field-group-new_issue_creation" ).append ( '<div id = "new-issue-progress-bar-legend"> . . . looking up issues . . . </div>' ); 
			$ ( "#wic-inner-field-group-new_issue_creation" ).append ( '<div id = "new-issue-progress-bar"></div>' );			
			$ ( "#wic-inner-field-group-new_issue_creation" ).append ( '<div id = "new-issue-table"></div>' );	
			$ ( "#new-issue-progress-bar" ).progressbar({
				value: false
			});
			// set up AJAX call and go	
			var data = {
				staging_table : uploadParameters.staging_table_name,
				issue_title_column : issueTitleColumn,
				issue_content_column : issueContentColumn  		
			}
			wpIssuesCRM.ajaxPost( 'upload_set_defaults', 'get_unmatched_issue_table',  $('#ID').val(), data, function( response ) {
				$ ( "#new-issue-table" ).html(response); // show table results
				$ ( "#new-issue-progress-bar-legend" ).remove();
				$ ( "#new-issue-progress-bar" ).remove();
				// reenable input once look up complete
				$ ( "#wic-form-upload-set-defaults :input" ).prop( "disabled", false ); 
				// do these form set up items as callbacks.
				recordDefaultDecisions();  // need to set the number of new issues among the default decisions
				decideWhatToShow();
				// have to do this in the callback on first run through to have the new issue count to test
			});
		} else {
			// first since not using titles, hide the issue creation dialog
			$ ( "#wic-field-group-new_issue_creation" ).hide();
			
			// on ready, after populating form, set database values from form  
			// necessary in case good to go without change and database values have not been saved
			// done in the issue look up callback above as well
			recordDefaultDecisions();
			decideWhatToShow();
			
		}
	}

	// function mostly decides what errors to show -- validating user input
	function decideWhatToShow() {
		// enable disable protect identity, depending on whether doing updates to matched.
		if ( $ ( "#update_matched" ).prop( "checked" ) ){
			$ ( "#protect_identity" ).prop ( "disabled", false );
			$ ( "#protect_blank_overwrite" ).prop ( "disabled", false );
		} else {
			$ ( "#protect_identity" ).prop ( "disabled", true );
			$ ( "#protect_blank_overwrite" ).prop ( "disabled", true );					
		}		
		
		
		/*
		*
		* Prepare messages based on field mapping and form choices
		*
		*/

		// drop prior set of error messages		
		$( "#upload-settings-need-attention" ).remove();
		errorsArray = [];
		
		// if both matched and unmatched are unchecked, nothing will be uploaded
		if ( false === $ ( "#update_matched" ).prop ( "checked" ) && false === $ ( "#add_unmatched" ).prop ( "checked" ) ) {
			errorsArray.push ( 'You have specified that no records will be updated or added -- nothing to upload.')		
		}

		// For address, only validate zip code and only if supplied as default
		if ( $( "#zip" ).val() > '' ) {
			if ( false == regPostalCode.test( $ ( "#zip" ).val() ) ) {
				errorsArray.push ( 'If postal code is supplied, it must be in 5 digit or 5-4 digit format.' )				
			}
		}		

		// address not mapped and no values for other defaults, hide type default
		if ( ! addressMapped && '' == $( "#zip" ).val() && '' == $( "#city" ).val() && '' == $( "#state" ).val() ) {
			$ ( "#wic-control-address-type" ).hide();			
		} 

		// if phone number number not mapped, hide phone type default
		if ( ! phoneMapped ) {
			$ ( "#wic-control-phone-type" ).hide();		
		} 

		// email address not mapped, hide type default
		if ( ! emailMapped ) {
			$ ( "#wic-control-email-type" ).hide();			
		} 


		/*
		*	Activity more complicated . . . if any field mapped or defaulted, need date and issue
		*	Title mapping is, in effect, an activity field, but don't allow default titling 
		*/
		if ( 	activityMapped 						|| // any activity field mapped
				issueMapped							|| // any issue field mapped
				$ ( "#activity_date" ).val() > '' 	|| 
				$ ( "#activity_type" ).val() > ''	||
				$ ( "#pro_con" ).val() > '' 		||	
				$ ( "#issue" ).val() > '' 			
			) { 

			// do enforce date here -- even though don't enforce as required field
			if ( '' == $( "#activity_date" ).val() && $( "#activity_date" ).is(':visible') ) {
				errorsArray.push ( 'Set an activity date to upload activity data.' )		
			}	
	
			// not enforcing type or pro/con	
	
			// must either map activity issue (a numeric link to post on the activity record), default the issue or map title
			if ( !activityIssueMapped && !titleMapped && '' == $( "#issue" ).val() ) {
				errorsArray.push ( 'Choose an issue to upload activity data.' );
			}
			
			// if mapped title and new issues will be created, must show issue creation dialog
			if ( defaultDecisions.new_issue_count > 0 ) {	
				$ ( "#wic-field-group-new_issue_creation" ).show();
				if ( ! $ ( "#create_issues" ).prop ( "checked" ) ) {		
					errorsArray.push ( 'You must affirmatively accept new issue creation or go back and unmap Issue Title.' )	;
				}
			} else {
				$ ( "#wic-field-group-new_issue_creation" ).hide();
			}
		}				

		// show/hide title elements in constituent default field group based on whether all group inputs are hidden
		if ( 0 == $( "#wic-field-group-constituent, #wic-field-group-address, #wic-field-group-email, #wic-field-group-phone" ).find( ":input" ).not( ":button, :hidden" ).length ) {
			$( "#wic-inner-field-group-constituent-toggle-button" ).hide();	
			$( "#wic-inner-field-group-constituent p" ).hide();			
		} else {
			$( "#wic-inner-field-group-constituent-toggle-button" ).show();	
			$( "#wic-inner-field-group-constituent p" ).show();			
		}

		// having set up form appropriately, decide whether to allow updates and what messages to show
		if ( errorsArray.length > 0 ) { 
			$( "#post-form-message-box" ).append( '<ul id="upload-settings-need-attention"></ul>' );
			for ( var i in errorsArray ) {
				$( "#upload-settings-need-attention" ).append( '<li>' +  errorsArray[i]  + '</li>' );
			}
			// if errors, bust back to status matched as if haven't been to default setting
			wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'matched',  function( response ) {});
			$( "#wic-upload-complete-button").prop( "disabled", true );		
		} else {
			// if no errors good to go to next stage 			
			wpIssuesCRM.ajaxPost( 'upload', 'update_upload_status',  uploadID, 'defaulted',  function( response ) {});
			$( "#wic-upload-complete-button").prop( "disabled", false );	
		}		

	}	

	
	function recordDefaultDecisions() {

		// first transfer form value to defaultDecisions object
		controlsArray.each ( function ( index ) {
			elementID 		= $( this ).attr( "id" ) 
			// note that since selects do not have type attribute, cannot user ternary here (get undefined)
			if ( 'checkbox' == $( this ).attr( "type" ) ) {
				elementValue 	= $( this ).prop ( "checked" ); 
			} else {
				elementValue 	= $( this ).val();
			}
			defaultDecisions[elementID] = elementValue;
		});

		// add new issue count to object ( length is #rows, but one row is header )
		defaultDecisions['new_issue_count'] = $( "#new-issue-table tr" ).length - 1 ;
		
		// set saving message
		$ ( "#post-form-message-base" ).text( saveHeaderMessage + " Saving . . . ")
		// send object to server
		wpIssuesCRM.ajaxPost( 'upload_set_defaults', 'update_default_decisions',  $('#ID').val(), defaultDecisions, function( response ) {
			$ ( "#post-form-message-base" ).text( saveHeaderMessage + " Saved.")
		});
	}	
	

		
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	