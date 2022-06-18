/*
*
*	email-send.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 
 
	$( "#wp-issues-crm" ).on ( "click" , '.email-compose-button', wpIssuesCRM.handleComposeButton );

	//https://stackoverflow.com/questions/18111582/tinymce-4-links-plugin-modal-in-not-editable#answer-18209594
	//http://learn.jquery.com/jquery-ui/widget-factory/extending-widgets/
	$.widget("ui.dialog", $.ui.dialog, { 
		_allowInteraction: function(event) {
			return !!$(event.target).closest(".mce-container").length || this._super( event );
		}
	});

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	var sendButtonValue = '';
	var intervalTimer = false; // for autosaves
	// only allow one window up at a time (modal), so safe to use a single named cache
	wpIssuesCRM.outgoingObjectCache = false;

	wpIssuesCRM.handleComposeButton = function ( event ) { 
	
		if( !wpIssuesCRMSettings.canSendEmail) {
			wpIssuesCRM.alert( '<p><em>Sorry, your user role can only work with reply emails.</em></p>');
			return;
		}	
	
		// handle click on span within button  
		var eventVal = $( event.target ).val();
		if ( ! eventVal ) { 
			eventVal = $( event.target ).closest( ".email-compose-button" ).val();
		}
		// split button value
		eventValArray = eventVal.split(',');
		var request = {
			context:eventValArray[0],
			id: 	eventValArray[1],
			parm:	eventValArray[2],
		}
		/*
		* send alert if main form is dirty -- might be changing the destination email address
		* could trigger save as for activity popup, but this adds a lot of complexity and email compose more likely a standalone (not expect edits) 
		*/
		if ( wpIssuesCRM.isParentFormChanged() ) {
			wpIssuesCRM.alert ( '<p>Save changes before composing an email.</p>')
		}	else {
			// use cache as a pending flag to catch double click	
			if ( !wpIssuesCRM.outgoingObjectCache ) { 
				wpIssuesCRM.outgoingObjectCache = true;
				wpIssuesCRM.ajaxPost( 'email_send', 'prepare_compose_dialog',  0, request,  function( response ) {
					if ( undefined == response.object ) {
						wpIssuesCRM.alert ( '<p>' + response + '</p>' )
						wpIssuesCRM.outgoingObjectCache = false;
					} else {
						// cache response
						wpIssuesCRM.outgoingObjectCache = response.object;
						// open window
						wpIssuesCRM.composeWindow ( response );
					}
				});			
			}
		}
	}
	
	
	
	wpIssuesCRM.composeWindow = function( response ) {
	
		// show the dialog
		var composeObject = $.parseHTML( response.form, document );
		var $composeObject = $ ( composeObject );
  		$composeObject.dialog( {
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  					clearInterval( intervalTimer ); 
					tinymce.activeEditor.remove();
					wpIssuesCRM.deinitializeMultiEmailcontrols( 'compose-envelope-wrapper' );
					$composeObject.dialog( "destroy" );
					$composeObject.remove();
					wpIssuesCRM.formDirty = false;
					wpIssuesCRM.outgoingObjectCache = false;
					var currentPage = $( ".wic-active-inbox-page .wic-inbox-page-value" ).text();
					if ( 'draft' == currentPage || 'outbox' == currentPage ) {
						$( "#wic-inbox-refresh-button ").trigger( "click" );
					}
  				},
			position: { my: "center top", at: "center top+110", of:  "#wp-issues-crm" } , 	
			width: 750,
			height: 800,
			buttons: [
				{
					width: 170,
					text: "Send Now",
					click: function() { 
						//populate the object
						populateOutgoingObject ( wpIssuesCRM.outgoingObjectCache, 0 ); // 0 is no longer a draft
						// if no addresses or any invalid, return after alert -- not applicable on list send
						var isList = 'email_send' == wpIssuesCRM.outgoingObjectCache.search_type.substring ( wpIssuesCRM.outgoingObjectCache.search_type.length - 10 )
						if ( !isList && !wpIssuesCRM.validateEmailArrays ( wpIssuesCRM.outgoingObjectCache ) ) {
								return;
						}
						// check basic form completion
						if ( ! wpIssuesCRM.outgoingObjectCache.issue  ) {
							wpIssuesCRM.alert ( 'Please select an issue.' )
						} else if ( wpIssuesCRM.outgoingObjectCache.subject.trim().length < 2 ) {
							wpIssuesCRM.alert ( 'Message subject must have at least 2 characters.' )  
						} else if ( wpIssuesCRM.outgoingObjectCache.html_body.trim().length < 5 ) {
							// note that this validation will pass a message with just the user signature
							wpIssuesCRM.alert ( 'Message must have at least 5 characters.' )
						} else { 
							// confirm before send for list seds
							if ( isList ) {
								wpIssuesCRM.confirm (
									updateMessageDraft,
									false,
									'<p><strong>Send messages to all with emails on list?</strong></p>' + $( '#search-link' ).html()
								); 
							} else {
								updateMessageDraft();
							}
						}
					}
				},
				{
					width: 170,
					text: "Finish Later",
					click: function() {
						populateOutgoingObject ( wpIssuesCRM.outgoingObjectCache, 1 ); // 1 is still a draft
						updateMessageDraft();
					}
				},
				{
					width: 170,
					text: "Discard Draft",
					click: function() {
						populateOutgoingObject ( wpIssuesCRM.outgoingObjectCache, -1 ); // -1 delete this draft
						updateMessageDraft();
					}
				}
			],
  			modal: true,
  		});
  		
  		
  		/*
  		* set up all elements in dialog window
		*/
		// wpIssuesCRM.initializeMultiEmailcontrols( 'compose-envelope-wrapper');	
  		[ 'to', 'cc', 'bcc' ].forEach ( function( element ) {
			wpIssuesCRM.address_array_to_email_tile( response.object[element + '_array'],  $( "#" + "compose-envelope-wrapper #compose_" + element )[0] )
		});
		$( "#compose_subject" ).val ( response.object.subject );	
		// on new form, the response.object.issue is 0, but the control already has the value set
		if (response.object.issue ) {	
			wpIssuesCRM.setVal ( $( "#compose_issue" )[0], response.object.issue, '');
		}
		$( "#compose_content" ).val( response.object.html_body );
		// init tinymc	
  		wpIssuesCRM.tinyMCEInit ( 
  			'compose_content', // selector
  			false, // reply?
  			'email_send' == wpIssuesCRM.outgoingObjectCache.search_type.substring ( wpIssuesCRM.outgoingObjectCache.search_type.length - 10 ), // dear?
  			true,  // focus?
  			function(){ // editorChange function
				$("#compose_content").trigger("change");
			}
		);	
		// initialize listeners
		wpIssuesCRM.initializeMultiEmailcontrols( 'compose-envelope-wrapper' ) 
		// initialize attachment uploader
		wpIssuesCRM.initializeAttachmentUploader( response.object.draft_id ); //upload-upload.js

		// manage page unloads -- do not manage form unloads ( escape key or "cancel edits" are clear enough indications of intent)
  		$ ( "#compose-message-popup" ).on ( "mousedown keydown change", function () { 
  			wpIssuesCRM.formDirty = true;
  		});
	
		$ ( "#compose-attachment-list").on ( "click", ".wic-attachment-list-item .dashicons-dismiss", function ( event ) {
			$listItem = $( event.target ).parent();
			var messageID = $listItem.find( ".wic-attachment-list-item-message_id" ).text();
			var attachmentID = $listItem.find( ".wic-attachment-list-item-attachment_id" ).text();
			// remove the item immediately -- no need to wait for process to finish
			$listItem.remove();
			wpIssuesCRM.ajaxPost( 'email_attachment', 'delete_message_attachments',  0, { message_id: messageID, attachment_id: attachmentID },  function( response ) {
				// only handle full error responses through ajax.js
			});			
		});
	
		intervalTimer = setInterval( autoSaveDraft, 20000 );
	
	}

	function populateOutgoingObject ( object, saveDraft ) {
		object.to_array		= wpIssuesCRM.email_tile_to_address_array( $( "#compose-envelope-wrapper #compose_to")[0] );
		object.cc_array		= wpIssuesCRM.email_tile_to_address_array( $( "#compose-envelope-wrapper #compose_cc")[0] );	
		object.bcc_array	= wpIssuesCRM.email_tile_to_address_array( $( "#compose-envelope-wrapper #compose_bcc")[0] );
		object.subject		= $( "#compose_subject" ).val();
		object.html_body 	= $( "#compose_content" ).val();
		object.is_draft		= saveDraft;
		object.issue 		= $( "#compose_issue" ).val();
		return object
	}

	// populate and update, no close
	function autoSaveDraft() {
		if ( wpIssuesCRM.formDirty ) {
			// populate outgoing object as draft
			populateOutgoingObject ( wpIssuesCRM.outgoingObjectCache, 1 )
			// send transaction 
			wpIssuesCRM.ajaxPost( 'email_send', 'update_draft',  0, wpIssuesCRM.outgoingObjectCache, function( response ) {
				// checking false return codes as error messages in .ajaxPost, but not otherwise giving feedback		
			});		
			wpIssuesCRM.formDirty = false;
		}
	}

	
	function updateMessageDraft () {
		// send transaction 
		wpIssuesCRM.ajaxPost( 'email_send', 'update_draft',  0, wpIssuesCRM.outgoingObjectCache, function( response ) {
			// checking false return codes as error messages in .ajaxPost, but not otherwise giving feedback		
		});	
		// proceed to close window, which includes reset main form dirty flag -- there is only one window allowed, so safe to refer by #id
		$( "#compose-message-popup" ).dialog( "close");
	}
	

	wpIssuesCRM.editDraft = function ( inboxSubjectLine ) {
		var ID = $( inboxSubjectLine ).find( ".message-ID" ).text() 
		if ( ! wpIssuesCRM.outgoingObjectCache ) {
			wpIssuesCRM.outgoingObjectCache = true;
			wpIssuesCRM.ajaxPost( 'email_send', 'load_draft',  0, ID,  function( response ) {
				if ( undefined == response.object ) {
					wpIssuesCRM.alert ( '<p>' + response + '</p>' )
					wpIssuesCRM.outgoingObjectCache = false;
				} else {
					// cache response
					wpIssuesCRM.outgoingObjectCache = response.object;
					// open window
					wpIssuesCRM.composeWindow ( response );
				}
			});
		}
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
