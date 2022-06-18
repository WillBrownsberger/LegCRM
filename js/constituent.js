/*
*
*	constituent.js 
*
*	
*
*/ 
jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on ( 
		"change deletedWICRow", 
		'form#wic-form-constituent',
		function (e) { 
			// don't trigger change on file upload
		if ( undefined === e.originalEvent || !$( e.originalEvent.target.parentElement).hasClass('moxie-shim') ) {		
				wpIssuesCRM.formDirty = true;
				wpIssuesCRM.setChangedFlags(e);
			}
		}
	)
	// automatically set case_status to Open when Assigned
	.on( "change", "#case_assigned", function() {
		if ( $ ( this ).val() > 0 ) {
			$( "#case_status" ).val(1)
		}
	})
	
	// list delete button
	.on( "click", "#delete_constituents_button", function() {
		wpIssuesCRM.doConstituentDeletePopup ( $(this).val() ); 
	})
	
	
	// main form dedup button
	.on ("click", "#wic-constituent-delete-button", function ( event ) { 
		var ID = $( event.target ).val();
		wpIssuesCRM.confirm(
			function () {
				wpIssuesCRM.ajaxPost( 'constituent', 'hard_delete', ID, $( "#constituent_delete_dialog #duplicate_constituent" ).val(),  function( response ) {
					$( "#post-form-message-box" ).text ( response.reason );
					$( "#post-form-message-box" ).addClass ( 'wic-form-errors-found' )
					if ( response.deleted ) {
						$( "#wic-form-constituent :input" ).prop( "disabled", true );
						$( ".dashicons-edit").on( "click", function ( event ) {
							event.stopImmediatePropagation()
						});
						if ( response.second_constituent ) { 
							setTimeout ( function () {
								wpIssuesCRM.goToConstituent( response.second_constituent );
								},
								3000
							);	
						}
					}
				});
			},
			false,
			$( "#constituent_delete_shell")[0].outerHTML.replace ( /constituent_delete_shell/, 'constituent_delete_dialog' )
		)
		wpIssuesCRM.confirmDialog.dialog( "option", "height", 650 );

		// show hide the switch link based on whether a value is selected
		$( "#constituent_delete_dialog #duplicate_constituent" ).on( "change", function () { 
			if ( ! $( "#constituent_delete_dialog #duplicate_constituent" ).val() ) {
				$( "#constituent_delete_dialog #switch_to_dup_link" ).hide();
			} else { 
				$( "#constituent_delete_dialog #switch_to_dup_link" ).show();
			}
		});

		// if click link, go the constituent
		$( "#constituent_delete_dialog #switch_to_dup_link").click ( function (event) {
			wpIssuesCRM.goToConstituent ( $( "#constituent_delete_dialog #duplicate_constituent" ).val() )
			event.preventDefault();
		})
	}) // end onclick delete button
	
	// set up listener to trigger form initialization
	.on ( "initializeWICForm", function () { 
		wpIssuesCRM.initializeConstituentForm();
	});

	// do initialize (normally ajax triggered) in case access by get
	wpIssuesCRM.initializeConstituentForm();
	
	// set value of flag used to alert that are receiving a constituent form after selection of a dup
	wpIssuesCRM.dupSelected = false;

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {


	wpIssuesCRM.initializeConstituentForm = function () {

		if (  $( "#wic-form-constituent" )[0] ) { 
			
			wpIssuesCRM.parentFormEntity = 'constituent';
			// this dup check list div will exist on initialize form only if have saved and found dups
			if ( $( "#wic-post-list" )[0] ) {
				wpIssuesCRM.doDupsPopup();
			}
			// this variable will be set as flag if clicked a dup from duplist (doing ID search, not form submission)
			// duplist click will come immediately to this on return
			// purpose is to set form value for no dupcheck, so server won't repeatedly send back dup check list
			if (  wpIssuesCRM.dupSelected ) {
				$( "#no_dupcheck" ).prop( "checked", true ); 
				wpIssuesCRM.dupSelected = false;
			}
			wpIssuesCRM.loadActivityArea( true );
			$("#wic-constituent-delete-button").tooltip( {show: false, hide: false });
			
			if ( !wpIssuesCRMSettings.canViewOthersAssigned ) {
				$( "#wic-inner-field-group-case input").prop("disabled", true)
			}
			
		}
	}

	wpIssuesCRM.goToConstituent = function ( constituentID ) {
		var passThroughButton = $( "#wic_hidden_top_level_form_button" )
		passThroughButton.attr("name", "wic_form_button" );
		passThroughButton.val( "constituent,id_search," + constituentID );
		passThroughButton.trigger("click");
	}

	// display dups list as popUp;
	wpIssuesCRM.doDupsPopup = function() {

		// compute width
		popupWidth =  Math.min( 1200, $( "#wic-form-constituent" ).width() );
		buttonWidth = ( popupWidth - 40 )/4;
		
		wpIssuesCRM.dupsPopup = $( "#wic-post-list" );
		wpIssuesCRM.dupsPopup.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.dupsPopup.remove(); // remove underlying list and attached dialog
				},
			position: { my: "left top", at: "left top", of: "#wic-form-constituent" }, 	
			width: popupWidth,
			height: 'auto',
			buttons: [
				{
					width: buttonWidth,
					text: "Ignore dups and save",
					click: function() {
						$( "#no_dupcheck" ).prop( "checked", true ); // set form value so that dups will continue to be ignored when form is updated again
						$( "#wic-form-constituent .wic-form-button:first" ).trigger ( "click" );
						// close/remove will happen when form returns
					} 
				},
				{
					width: buttonWidth,
					text: "Edit further before saving",
					click: function() {
						wpIssuesCRM.dupsPopup.dialog( "close" );
					}
				},
			],
			modal: true,
			title: 'Possible duplicate(s) found -- click line to switch to editing poss dup.'
		});	
		
		wpIssuesCRM.dupsPopup.on ( "click", "button.wic-post-list-button", function () {
			wpIssuesCRM.dupSelected = true;		
			// close/remove of popup will happen when form returns.
		});
	}



	wpIssuesCRM.doConstituentDeletePopup = function( searchID ) {

		var constituentDeleteObject = $( "#delete_constituent_dialog" );
		var deleteSuccessful = false;
  		constituentDeleteObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				constituentDeleteObject.dialog( "destroy" );
				if ( deleteSuccessful ) {
					location.replace(location.href.replace('action=id_search','action=id_search_to_form')); }
				else {
					location.reload(true);
  				}
  				},
			width:  480,
			height: 480,
			show: { effect: "fadeIn", duration: 300 },
			buttons: [
				{
					width: 100,
					id: 'constituentDeleteButton',
					text: "Delete",
					click: function() {
						if ( 'CONFIRM CONSTITUENT PURGE' != $ ( "#confirm_constituent_action" ).val() ) {
							wpIssuesCRM.alert ( 'To delete constituents and their activities, you must type "CONFIRM CONSTITUENT PURGE" in all caps.' );  
						} else { 
							$( "#delete_constituent_dialog .action-ajax-loader" ).show();
							$( "#constituentDeleteButton" ).remove();
							$( "#cancelconstituentDeleteButton .ui-button-text").text( "Close" );
							wpIssuesCRM.ajaxPost( 'constituent', 'list_delete_constituents',  0, searchID,  function( response ) {
								constituentDeleteObject.html( '<h4><strong>' + ( response.deleted ? 'Successful' : 'Unsuccessful' ) + ':</strong></h4><p>' + response.message + '</p>' + ( response.deleted ? '<p>Your browser will return to show your original search when you close this window.</p>' : '' ))
								$( "#constituentDeleteButton" ).remove();
								if ( response.deleted ) { 
									deleteSuccessful = true;
								} else {
									$( "#cancelconstituentDeleteButton .ui-button-text").text( "Cancel" );
								}
							});
						}
					}
				},
				{
					width: 100,
					id: 'cancelconstituentDeleteButton',
					text: "Cancel",
					click: function() { 
						constituentDeleteObject.dialog( "close" );
					}
				}
			],
  			modal: true,
  		});
	}





}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
