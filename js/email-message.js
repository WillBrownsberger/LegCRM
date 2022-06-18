/*
*
* email-message.js
*
*
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	// flag to indicate that saved or loaded a template;
	// only consequence is that user will be shown confirm screen to allow training
	wpIssuesCRM.usedStandard = false;
	// assigned staff
	wpIssuesCRM.assignedStaff = false;
	// parse details popup info
	var parseDetails = false;
	// cache for pro_con for current issue
	var templatedProConArray = [];
	var viewMessageDialogObject, previewMessageDialogObject; 
	// cache for list of all addressees of curretnt message
	wpIssuesCRM.allAddressees = [];
	// cache to display transition line for reply preview image
	wpIssuesCRM.replyTransitionLine = '';
	// message is a final draft reply from inbox
	wpIssuesCRM.replyFinal = 0;

	/*
	*
	*  tinymce load
	*
	*  selector is id without #
	*  reply is true or false ( include reply plugin and buttons )
	*  focus is true or false ( take focus when loaded )
	*  editorChange is the function passed as an argument to be triggered on change
	* 	(this used to be passed to a delayed change function, but since not saving on change, better to do immediately)
	*/
	wpIssuesCRM.tinyMCEInit = function ( selector, reply, dear, focus, editorChange ) {
		var replyPlugin = reply ? 'wicreply' : '';
		var replyButton = reply ? ' wicreply_save wicreply_load wicreply_preview |' : '';
		var dearPlugin  = dear ? ', wicdear' : '';
		var dearButton  = dear ? 'wicdear_button |' : '' ;
		tinymce.init({
 			anchor_top: false,
 			anchor_bottom: false,
			autofocus: focus ? selector : false,
			branding: false,
			browser_spellcheck: true,
			cache_suffix: '?v=3.6', // last wp-issues-crm version in which plugin changed
			forced_root_block : 'div',
			init_instance_callback: function (editor) {
    			editor.on('change keyup paste', function (e) {
					editor.save();
					editorChange();
    			});
    		},
    		link_context_toolbar: true,
    		link_assume_external_targets: true,
			menubar: false,
			plugins: [ replyPlugin + ' advlist, code, colorpicker, help, hr, image, link, lists, media, paste, preview, print, textcolor' + dearPlugin
				],
			relative_urls: false,
			remove_script_host : false,
			selector: '#' + selector,	
			statusbar: false,
			// can break line on group | boundaries; if all one group, cannot break, so make small groups
			toolbar: [  dearButton +  replyButton  + ' styleselect fontselect fontsizeselect | forecolor backcolor | bullist numlist | outdent indent | ' +
				' link | media image | hr | print | removeformat code | undo redo '
				], 
		});
	}
	
	// -- call only by scrollMessage
	wpIssuesCRM.showMessageDetails = function () {
	
		// hide inbox
		$( "#wic-email-inbox" ).css( "display", "none" );
		/*
		*
		* add listeners
		*
		*/
		$( "#wic-inbox-message-review" )
		// navigation handlers
		.on( "click", ".subject-line-move-button", function( event ) {
			subjectLineMoveEventCache = event;
			if ( wpIssuesCRM.formDirty ) {
				handleTemplateChange();
			} 
			subjectLineMove();
		})
		.on( "click", ".wic-form-button.scroll-button", wpIssuesCRM.handleScrollButton )
		.on ( "click", "#wic-email-close-button", function(){
			if ( wpIssuesCRM.formDirty ) {
				handleTemplateChange();
			} 
			wpIssuesCRM.closeMessageDetails();
		})
		//  info request handlers
		.on( "click", "#parse-popup-button", wpIssuesCRM.doParseDetailsPopup ) 
		.on( "click", "#view-issue-button", wpIssuesCRM.doIssuePeekPopup )
		// change listeners ( in addition to tinymce form dirty change)
		.on ( "change", "#wic-message-sender-constituent  #constituent_id", wpIssuesCRM.saveConstituentToInboxImage )
		.on ( "change", "#wic-inbox-work-area #issue", handleIssueChange )
		.on ( "change", "#wic-inbox-work-area #pro_con", handleProConChange ) 
		// initialize action request handlers
		.on( "click", "#assigned-case-popup-button", wpIssuesCRM.handleAssignmentPopupClick ) 	
		// processEmail distinguishes the trigger buttons
		.on( "click", ".trigger-email-process-button", wpIssuesCRM.processEmail )
		.on( "click", "#new_issue_button", newIssue )
		// display message review area
		.css( "visibility", "visible" );
			
		// remove any extant tinymce instances
		tinymce.remove();
		// initialize tinymce -- change lights save button color
		wpIssuesCRM.tinyMCEInit ( 'working_template', true, true, true, function(){
			setSaveButtonColor()
			// 4.3.0 handle dirty
			if ( ! wpIssuesCRM.formDirty ) {
				wpIssuesCRM.formDirty = true;
				// set timer for saving of template
				setTimeout( handleTemplateChange, 5000 );
			}
		});
	}
	
	wpIssuesCRM.closeMessageDetails = function () {
		// 4.3.0, new dirty
		wpIssuesCRM.formDirty = false;
		// release all listeners
		$( "#wic-inbox-message-review" ).off();
		$( "#wic-inbox-message-review" ).css( "visibility", "hidden" );				
		$( "#wic-load-inbox-inner-wrapper" ).html (''); // empty inbox before displaying it -- will be reloading anyway -- save the full repaint;  
		$( "#wic-email-inbox" ).css( "display", "block");
	
		// close undo popup if displayed
		wpIssuesCRM.removeUndo();
		// remove editor
		tinymce.remove('#working_template');
		// reload the inbox (this also updates the message counts in the header)
		wpIssuesCRM.loadSelectedPage(); // is inbox
	}

	
	/*
	*
	* navigation handlers
	*
	*/
	var subjectLineMoveEventCache = false;
	function subjectLineMove( ) {
		// 4.3.0, new dirty
		event = subjectLineMoveEventCache;
		wpIssuesCRM.formDirty = false;
		var targetVal = $( event.target ).val();
		// handle click on span within button
		if ( ! targetVal ) { 
			targetVal = $( event.target ).closest( ".email-action-button" ).val();
		}
		var skipToLine = "right" == targetVal ?
			 wpIssuesCRM.activeLine.nextAll("li:not(.item-sending)")[0] :
			 wpIssuesCRM.activeLine.prevAll("li:not(.item-sending)")[0] ;
		wpIssuesCRM.jumpToSubjectLine( skipToLine, 0 );

	}
	// messages within same subject line
	wpIssuesCRM.handleScrollButton = function ( event ) { 

		var targetVal = $( event.target ).val();
		// handle click on span within button
		if ( ! targetVal ) { 
			targetVal = $( event.target ).closest( ".scroll-button" ).val();
		}
		// scroll		
		wpIssuesCRM.currentMessageVars.activeMessage = wpIssuesCRM.currentMessageVars.activeMessage + Number ( targetVal );
		wpIssuesCRM.scrollMessage ( false );
	}

	/* 
	*  ScrollMessage retrieves message info based on activeMessage pointer.
	*  It only show/hides loaders and areas to be filled, enables/disables approve/assign buttons -- no other display decisions change in it
	*
	*  Note that this is scrolling within a subject line ( or loading the first message of a subject line); jumpToSubjectLine scrolls across lines
	*/
	wpIssuesCRM.scrollMessage = function( switchingSubjects ) {

		// reset template used flag
		wpIssuesCRM.usedStandard = false;	
		// show progress popup
		wpIssuesCRM.doUpdateInProgressPopup();
		// reset save/load buttons before doing call
		resetSaveButtonColor();
		resetLoadButtonColor();
		// reset envelope area ( remove listeners, disable input and clear prior tile)
		wpIssuesCRM.deinitializeMultiEmailcontrols ( 'envelope-edit-wrapper' );
		$( "#multi-line-warning-legend" ).show();

		// do not cancel pending xhr -- may be some catch up page swapping, but don't want to cancel pending actions that may be running in background

		//  reset and then set scroll arrows enabled state
		$(".scroll-button").attr("disabled", false).removeClass( "ui-state-disabled" );
		if ( 0 == wpIssuesCRM.currentMessageVars.activeMessage ) {
			$("#left-message-scroll").attr("disabled", true).addClass("ui-state-disabled");
		}
		if ( wpIssuesCRM.currentMessageVars.activeMessage == ( wpIssuesCRM.currentMessageVars.countMessages -1 ) ) {
			$("#right-message-scroll").attr("disabled", true).addClass("ui-state-disabled");
		}

		// pass through issue, template and pro_con from input
		// overwrite them on server side only if changing subject, simpler just to always pass through
		var data = { 
			issue: 		$( "#issue" ).val(),
			template: 	$( "#working_template" ).val(), 
			pro_con: 	$( "#pro_con" ).val(),
			switching: 	switchingSubjects,
		}
		// get the message
		wpIssuesCRM.ajaxPost( 'email_message', 'load_message_detail',  currentUID(), data,  function( response ) {
			/*
			* set approval button appearance
			*/
			setThumbsUpDown( response.reply_is_final );
			/*
			* set up the title for the subject line group of message
			*/
			var fullSubjectString = 
				wpIssuesCRM.currentMessageVars.activeSubject + 	
				'<span id="wic-message-subject-line-position"> (Page Line ' + 
					( wpIssuesCRM.activeLine.prevAll().length + 1 ) + 
						' of ' + 
					( wpIssuesCRM.activeLine.siblings().length + 1 ) +
				')</span>';

			$( "#wic-message-subject").html(fullSubjectString);
			/*
			* set up the header line for the current sender
			*/
			// update the sender name
			$( "#wic-message-sender-name" ).text( response.sender_display_line );
			$( "#wic-message-sender-name" ).attr( "title", response.from_email );
			// set up the constituent selectmenu
			wpIssuesCRM.setVal ( 
				$( "#wic-message-sender-constituent #constituent_id" )[0],
				response.assigned_constituent,
				response.assigned_constituent_display
			)
			// save assigned staff -- setting to 0 if unassigned
			wpIssuesCRM.assignedStaff = response.assigned_staff;
			// set button values attributes and properties
			manageApproveAssignButtons();
			// save final draft status
			wpIssuesCRM.replyFinal = response.reply_is_final;
			// update the scroll positions
			$( "#wic-message-scroll-position" ).html( wpIssuesCRM.currentMessageVars.activeMessage + 1 )
			$( "#wic-message-scroll-total" ).html( wpIssuesCRM.currentMessageVars.countMessages  )	
			/*
			* assemble the incoming message information info
			*/
			// cc and to information
			$( "#recipients-display-line").html( response.recipients_display_line );
			// attachments
			$( "#attachments-display-line" ).html( response.attachments_display_line );
			// save message details and add tooltip to new button
			parseDetails = response.incoming_message_details; 
			$( "#parse-popup-button" ).text( 'p?' );
			// update the found message information
			$( "#inbox_message_text" ).html( response.incoming_message )  // raw_html_body
			/*
			* set up the RHS issue/pro_con template data
			*/
			// update the issue and pro_con selectmenu fields
			wpIssuesCRM.setVal ( $("#wic-inbox-work-area #issue"	)[0], response.issue, response.issue_title ) ;  
			wpIssuesCRM.setVal ( $("#wic-inbox-work-area #pro_con"	)[0], response.pro_con, '' ) ;  
			// fill in issue text and links
			synchIssueFields () 
			/*
			*
			* load the template data
			*
			*
			* note that second branch below initializes the instance of tinymce AFTER the content has been set -- quick setContent afterwards does not work
			*	e.g.: https://github.com/tinymce/tinymce/issues/3413 -- this works:setTimeout ( function() { tinymce.activeEditor.setContent ( 'test' ) }, 1000 );
			* 	but a straight execution does not -- simpler just to assure sequencing and remove between scrolls 
			*/
			$( "#working_template" ).val( response.template ); 
			if ( "visible" == $( "#wic-inbox-message-review" ).css( "visibility" )  ){
				tinymce.activeEditor.setContent ( response.template ); // initialized in previous message, so adequate delay
				resetSaveButtonColor(); // the set content looks like a change if previous differed from new
			} else {
				wpIssuesCRM.showMessageDetails(); // initializes the instance of tinymce AFTER the underlying content has been set
			}
			/*
			* set up the envelope area (fully cleared and disabled above by deinitializeMultiEmailcontrols at start of scrollMessage )
			*/
			// define subject line for outgoing email
			$( "#" + "envelope-edit-wrapper #message_subject" ).val ( ( "re:" == wpIssuesCRM.currentMessageVars.activeSubject.substring( 0, 3 ).toLowerCase() ? '' : "Re: " ) + wpIssuesCRM.currentMessageVars.activeSubject )
			// define initial too address (only alterable in single email subject lines )
			wpIssuesCRM.address_array_to_email_tile( response.reply_array, $( "#" + "envelope-edit-wrapper #message_to")[0] )
			if ( 1 ==  wpIssuesCRM.currentMessageVars.countMessages  ) {
				wpIssuesCRM.initializeMultiEmailcontrols( 'envelope-edit-wrapper');	
				$( "#multi-line-warning-legend" ).hide()
			}
			// reset addressee list
			wpIssuesCRM.allAddressees = response.clean_all_array;
			// no need to close updateInProgressPopupObject.dialog . . . closed on complete of post (if open)
			
			// cache transition line
			wpIssuesCRM.replyTransitionLine  = response.reply_transition_line;


		});		
	}
	/*********
	*
	*
	* info request handlers
	*
	*
	**********/
	wpIssuesCRM.handleAssignmentPopupClick = function () {
		if ( 1 == wpIssuesCRM.currentMessageVars.countMessages ) {
			wpIssuesCRM.doAssignmentPopup();
		} else {
			wpIssuesCRM.alert ( 
				"<p>Assignment only available for single messages.</p><p>Instead, you could assign the whole issue to a staff member " +
				 "(update the issue) OR just switch the inbox out of grouped mode (<b><em>1</em></b>).</p>" );
		}
	}
	/*
	*
	* popup for case assignment -- note, converted this to support assignment of individual email instead of assigning whole case; 
	* 
	**
	***
	**** did not change all variable names, so #case_assigned here refers to the mail being assigned, not the underlying constituent.
	***
	**
	*
	*/
	wpIssuesCRM.doAssignmentPopup = function() {

		var popupWidth = 480;
		var popupContent = '<div title="Assign Staff to Handle Message"><div id = "staff_assignment_popup">' + 
			$( "#hidden-staff-assignment-control" ).html() + 
			'<p>Email will be assigned to selected staff and will show in the Assigned tab.</p>' +
			'<p>Other emails with the exact same subject line will automatically follow the assigned email into the Assigned tab until ' +
			'the assigned email is disposed of; you will still need to sweep them or individually reply to them.  They are not yet recorded or assigned or grouped ' +
			 'with the assigned email.  They are just following it to reduce clutter in other inbox tabs, while assigned staff drafts a reply. ' +
			 'You can act on them individually at any time just as if they were in any other tab.</p>'; 
		dialog 				= $.parseHTML( popupContent );
		wpIssuesCRM.assignedCasePopupObject = $( dialog );
  		wpIssuesCRM.assignedCasePopupObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				wpIssuesCRM.assignedCasePopupObject.remove();	
  				wpIssuesCRM.assignedCasePopupObject = false;
  				},
			width: popupWidth,
			height: 480,
			position: { my: "left top", at: "left bottom", of: "#wic-message-sender-constituent  .wic-selectmenu-wrapper" }, 	
			show: { effect: "fadeIn", duration: 200 },
			buttons: [
				{
					width: 80,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.assignedCasePopupObject.dialog( "close" ); 
					}
				},
				{
					width: 80,
					text: "Assign",
					autofocus: true,
					click: function() {
						wpIssuesCRM.assignedStaff = selectStaffButton.val();
						wpIssuesCRM.saveValueToInboxImage( 'staff', wpIssuesCRM.assignedStaff );
						manageApproveAssignButtons(); 
						wpIssuesCRM.assignedCasePopupObject.dialog( "close" ); 
					}
				}
			],  			
			modal: true,
  		});
  		selectStaffButton = $( "#staff_assignment_popup #case_assigned" );
		wpIssuesCRM.setVal( selectStaffButton[0], wpIssuesCRM.assignedStaff, '' ); // empty string is ignored provided staff # exists

	};

	// informational popup 
	wpIssuesCRM.doParseDetailsPopup = function() { 
		var popupWidth = ( $( window ).width() - 250 )/2;
		var popupContent = '<div title="Message Parse Results"><div id = "parse_details_popup">' + parseDetails + '</div></div>'; 
		dialog 				= $.parseHTML( popupContent );
		wpIssuesCRM.parseDetailsDialogObject = $( dialog );
  		wpIssuesCRM.parseDetailsDialogObject.dialog({
			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				wpIssuesCRM.parseDetailsDialogObject.remove();	
  				wpIssuesCRM.parseDetailsDialogObject = false;
  				},
			width: popupWidth,
			height: 500,
			position: { my: "left top", at: "left bottom", of: "#parse-popup-button" }, 	
			show: { effect: "fadeIn", duration: 200 },
			buttons: [],
  			modal: true,
  		});
	};

	// informational popup 
	wpIssuesCRM.doIssuePeekPopup = function() {
		var popupWidth = ( $( window ).width() - 250 )/2;
		// use display value for title
		var popupContent = '<div title="' + $( "#issue").next().val() + '"><div id = "issue_text_popup">' + $( "#issue_view_area" ).html() + '</div></div>'; 
		dialog 				= $.parseHTML( popupContent );
		wpIssuesCRM.issuePeekDialogObject = $( dialog );
  		wpIssuesCRM.issuePeekDialogObject.dialog({
 			appendTo: "#wp-issues-crm", 		
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				wpIssuesCRM.issuePeekDialogObject.remove();	
  				wpIssuesCRM.issuePeekDialogObject = false;
  				},
			width: popupWidth,
			height: 500,
			position: { my: "left top", at: "left bottom", of: "#issue-selectmenu-display"  }, 	
			show: false,
			buttons: [],
  			modal: true,
  		});
	};

	// preview full message with editor popup
	wpIssuesCRM.previewMessage = function() {

		// use display value for title
		var popupContent = 
			'<div title="Sample Message Text">' +
				'<div id = "message_preview">' + 
					tinymce.activeEditor.getContent() +
					wpIssuesCRM.replyTransitionLine +
					$( "#inbox_message_text" ).html() +
				'</div>' +
			'</div>'; 
		var dialog 	= $.parseHTML( popupContent );
		previewMessageDialogObject = $( dialog );
  		previewMessageDialogObject.dialog({
			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				previewMessageDialogObject.remove();	
  				previewMessageDialogObject = false;
  				},
			width: 600,
			height: 500,
			position: { my: "left top", at: "left bottom", of: "#issue-selectmenu-display"  }, 	
			show: false,
			buttons: [
				{
					width: 80,
					text: "Close",
					click: function() {
						previewMessageDialogObject.dialog( "close" ); 
					}
				}
			
			],
  			modal: true,
  		});
  		$( ".ui-dialog-content" ).scrollTop(0);
	};	
	
	// view full message by ID
	wpIssuesCRM.viewMessage = function( selectedPage, inboxSubjectLine ) {
		var ID = $( inboxSubjectLine ).find( ".message-ID" ).text() 
		// use display value for title
		var popupContent = 
			'<div title="View Message (' + selectedPage + ' #' + ID + ')">' +
				'<div id = "message_view">' + 
				'</div>' +
			'</div>'; 

		var dialog 	= $.parseHTML( popupContent );
		viewMessageDialogObject = $( dialog );
		var buttonArray =  [
				{
					width: 100,
					text: "Reply",
					value: 'reply,' + ID + ',' + selectedPage,
					class: 'email-compose-button',
					click: function(){}
				},
				{
					width: 100,
					text: "Reply All",
					value: 'reply_all,' + ID + ',' + selectedPage,
					class: 'email-compose-button',
					click: function(){}
				},
				{
					width: 100,
					text: "Forward",
					value: 'forward,' + ID + ',' + selectedPage,
					class: 'email-compose-button',
					click: function(){}
				},				
				{
					width: 100,
					text: "Close",
					click: function() {
						viewMessageDialogObject.dialog( "close" ); 
					}

				}
			];
		if ( 'outbox' == selectedPage ) {
			buttonArray.unshift ( {
				width: 100,
				text: "Delete",
				click: function() {
			  		wpIssuesCRM.showLoader( 'message_view' );
					wpIssuesCRM.ajaxPost( 'email_send', 'delete_message_from_send_queue',  '', ID,  function( response ) { 
						$( inboxSubjectLine ).remove(); 
						var $outboxBase  = $( "#outbox-lower-range" );
						var $outboxRange = $( "#outbox-upper-range" );
						var $outboxTotal = $( "#outbox-total-count" );
						$outboxRange.text( Number( $outboxRange.text() ) - 1 ) 			
						$outboxTotal.text( Number( $outboxTotal.text() ) - 1 ) 			
						if ( Number( $outboxBase.text() ) > Number( $outboxRange.text() ) ) {
							$outboxBase.text( $outboxRange.text() );
						}
						viewMessageDialogObject.dialog( "close" );
					});
				}
			} )
		}

  		viewMessageDialogObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				viewMessageDialogObject.remove();	
  				viewMessageDialogObject = false;
  				},
			width: 800,
			height: 800,
			position:  { my: "right top", at: "right bottom+10", of: "#wic-top-level-form"  }, 	
			show: false,
			buttons: buttonArray,
  			modal: true,
  		});
  		wpIssuesCRM.showLoader( 'message_view' );
		wpIssuesCRM.ajaxPost( 'email_message', 'load_full_message',  ID, selectedPage,  function( response ) { 		
  			$( "#message_view" ).html( 
  				response.recipients_display_line + 
  				( response.attachments_display_line ? '<hr/>' : '') + 
  				response.attachments_display_line + '<hr/>' +  
  				response.message + 
  				( ( response.issue_link || response.constituent_link ) ? '<hr/>' : '' ) +
  				response.issue_link + ' | ' +
  				response.constituent_link
  			);
  		});
  		$( ".ui-dialog-content" ).scrollTop(0);
	};	
	
	// view template by ID
	wpIssuesCRM.viewSaved = function( inboxSubjectLine ) {
		// extract parameters from subject line
		var ID 		= $( inboxSubjectLine ).find( ".message-ID" ).text(); 
		var subject	= $( inboxSubjectLine ).find( ".actual-email-subject" ).text(); 
		var proCon  = $( inboxSubjectLine ).find( ".from-summary" ).text(); // poor choice of class name -- is the pro-con label
			proCon  = proCon.trim() ? proCon.trim() : ' -- ';
		var proConValue = $( inboxSubjectLine ).find( ".pro-con-value" ).text()
		var replyID = $( inboxSubjectLine ).find( ".reply-ID" ).text()
		// prepare dialog object
		var popupContent = 
			'<div title="Saved Reply for: ' + subject + ' (' + proCon + ')">' +
				'<div id="wic_popup_template_view_loader"></div>' +
				'<textarea id = "wic_popup_template_view">' + 
				'</textarea>' +
			'</div>'; 
		var dialog 	= $.parseHTML( popupContent );
		var viewTemplateDialogObject = $( dialog );
		// define alternative buttons with associated functions
		var closeButton = {
					width: 80,
					text: "Close",
					click: function() {
						viewTemplateDialogObject.dialog( "close" ); 
					}
				};
		var updateButton = {
					width: 80,
					text: "Update",
					click: function() {
						wpIssuesCRM.showLoader( 'wic_popup_template_view_loader' );
						data = {
							pro_con_value: proConValue,
							template_title: 'Reply to "' + subject + '" -- ' + proCon ,
							template_content: tinymce.activeEditor.getContent()
						}
						wpIssuesCRM.ajaxPost( 'email_message', 'save_update_reply_template',  ID,  data,  function( response ) { 		
	  						$( '#wic_popup_template_view_loader' ).html('');
						});
					}
				};
		var deleteButton = {
					width: 80,
					text: "Delete",
					click: function() {
						wpIssuesCRM.showLoader( 'wic_popup_template_view_loader' );
		  				tinymce.remove('#wic_popup_template_view');
						$( "#wic_popup_template_view" ).hide();
						wpIssuesCRM.ajaxPost( 'email_message', 'delete_reply_template',  ID,  proConValue,  function( response ) { 	
							$( "#wic_popup_template_view_loader" ).html( response.message );
							if ( ! response.success ) {
								viewTemplateDialogObject.dialog ( "option", "buttons", [ closeButton ] );
							} else {
								viewTemplateDialogObject.dialog ( "option", "buttons", [ closeButton ] );							
							}
							wpIssuesCRM.loadSelectedPage();
						});
					}
				};
		var buttonArray =  [ updateButton, deleteButton, closeButton ];
		// open the dialog
  		viewTemplateDialogObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				tinymce.remove('#wic_popup_template_view');
  				viewTemplateDialogObject.remove();	
  				viewTemplateDialogObject = false;
  				},
			width: 800,
			height: 800,
			position: { my: "right top", at: "right bottom", of: "#wic-email-inbox-header"  }, 	
			show: false,
			buttons: buttonArray,
  			modal: true,
  		});
  		// show a loader
  		wpIssuesCRM.showLoader( 'wic_popup_template_view_loader' );
  		// load the reply template
		wpIssuesCRM.ajaxPost( 'email_message', 'get_reply_template',  ID,  proConValue,  function( response ) { 
	  		$( '#wic_popup_template_view_loader' ).html('');
  			$( "#wic_popup_template_view" ).html( response );
  			wpIssuesCRM.tinyMCEInit ( 'wic_popup_template_view', false, true, true, function(){} )
  		});
  		$( ".ui-dialog-content" ).scrollTop(0);
	};
	/*
	*
	* change handlers
	*
	*/

	/* 
	* saveValueToInboxImage is used  to pass values to 
	* inbox_defined_staff,
	* inbox_defined_issue,
	* inbox_defined_pro_con,
	* inbox_defined_reply_text,
	* inbox_defined_reply_is_final
	*
	* pass field to update as "staff" or "issue", etc.
	*
	*/
	wpIssuesCRM.saveValueToInboxImage = function( field_to_update, field_value ){
		// no reason to disable processing buttons
		
		// set up update values
		var data = {
			field_to_update: field_to_update,
			field_value: field_value,
		};
		// do the update
		wpIssuesCRM.ajaxPost( 'email_message', 'quick_update_inbox_defined_item', currentUID(), data,  function( response ) {
		});
	}
	
	
	
	
	wpIssuesCRM.saveConstituentToInboxImage = function(){
		// disable processing buttons
		processingButtons = $( ".trigger-email-process-button" );
	 	processingButtons.prop("disabled", true ).addClass('ui-state-disabled');

		// set up update values
		var data = {
			assigned_constituent: $( "#wic-message-sender-constituent #constituent_id" ).val(),
		};
		// do the update
		wpIssuesCRM.ajaxPost( 'email_message', 'quick_update_constituent_id', currentUID(), data,  function( response ) {

			// if in multi line mode, automatically update selected constituent_name in displayed address (since not changeable directly by user)
			if ( response.constituent_name && 1 < wpIssuesCRM.currentMessageVars.countMessages ) {
				var $toTile = $( "#envelope-edit-wrapper #message_to").parent().siblings(".constituent-email-item"); 
				$toTile.data( "emailParms" ).name = response.constituent_name;
				$toTile.children( ".constituent-email-item-text" ).text ( response.constituent_name );
			}
			// enable processing buttons
		 	processingButtons.prop("disabled", false ).removeClass('ui-state-disabled');
		});
	}

	/*
	*
	* these two functions allow split of synchIssueFields and setLoadButtonColor from update
	*
	*/
	function handleIssueChange() {
		synchIssueFields();
		wpIssuesCRM.saveValueToInboxImage ( 'issue',  $( "#wic-inbox-work-area #issue").val() );
	}
	
	function handleProConChange () {
		setLoadButtonColor();
		wpIssuesCRM.saveValueToInboxImage ( 'pro_con',  $( "#wic-inbox-work-area #pro_con").val() ); 
	
	}
	/*
	*
	* isolated so can be called on timer and on leave 
	*
	*/
	function handleTemplateChange() {
		if ( wpIssuesCRM.formDirty ) {
			// reset dirty indicator to false before saving so that will start new timer on next keystroke after getContent -- last timer must always run
			// However, note that risk exists if save is delayed on server side, could queue multiple unnecessary requests.  Accept this risk rather than trying to abort earlier -- prioritize delivering the save
			wpIssuesCRM.formDirty = false;
			//proceed to save
			if ( tinymce.activeEditor ) {
				wpIssuesCRM.saveValueToInboxImage ( 'reply_text', tinymce.activeEditor.getContent() );
			}
		}
	}
	/*
	* update current issue links
	*
	* fired on change issue
	* also fired on scroll message to set up issue link
	*
	* slight risk that fast user on slow system could select and then to the peek popup before this function returns, but
	* disabling the peek while waiting would mean that peek disabled on blur when normally the issue view will have been populated by select
	*/
	function synchIssueFields() {
		// reset template used flag
		wpIssuesCRM.usedStandard = false;	
		resetLoadButtonColor();
		issue = $( "#wic-inbox-work-area #issue").val(); 
		if ( '' < issue ) { 
			$( "#edit_mapped_issue_link" ).attr( "href", wpIssuesCRM.editMappedIssueLinkBase + issue );
			$( "#edit_mapped_issue_link" ).show();
			// do not show loader since field hidden; 
			// this fires on blur of issue
			wpIssuesCRM.ajaxPost( 'email_message', 'get_post_info',  issue, '' ,  function( response ) {
				$( "#issue_view_area #inbox_issue_text" ).html( response.content );
				templatedProConArray = response.templated_pro_con_array;
				setLoadButtonColor();
			});					
		} else {
			$( "#edit_mapped_issue_link" ).hide();
			$( "#issue_view_area #inbox_issue_text").html( " . . . <em>no issue assigned</em> . . . " );	
		}
	}
	/*
	*
	* handle approval button appearance
	*
	*
	*/
	// set to opposite of status -- note that status comes through as a string value
	function setThumbsUpDown ( status ) {
		if ( '1' == status ) {
			$( "#wic-email-approve-button span").addClass('dashicons-thumbs-down');
			$( "#wic-email-approve-button span").removeClass('dashicons-thumbs-up');
			jQuery (  "#wic-email-approve-button" ).attr("title","Reject draft");
		} else {
			$( "#wic-email-approve-button span").addClass('dashicons-thumbs-up');
			$( "#wic-email-approve-button span").removeClass('dashicons-thumbs-down');		
			jQuery (  "#wic-email-approve-button" ).attr("title","Submit draft");
		}
	}
	//
	/*
	*
	*
	* Action request handlers
	*
	*/
	function newIssue () {
		var data = { 
		}
		wpIssuesCRM.ajaxPost( 'email_message', 'new_issue_from_message', currentUID(), data,  function( response ) {
			wpIssuesCRM.setVal( $( "#issue")[0], response['value'], response['label'] )
			synchIssueFields();
			if ( response['notice']) {
				wpIssuesCRM.alert ( response['notice'] );
			}
		});

	}
		
	wpIssuesCRM.saveIssueReplyTemplate = function() {
		var contentBlank = ( tinymce.activeEditor.getContent() === '' );
		var templateExistsNow = templateExists();
		var identifiers	 = issueProConList();
		// four possibilities
		if ( !templateExistsNow && contentBlank ) {
			wpIssuesCRM.alert ( 
				'<p>Cannot save a blank standard since no prior standard exists.</p>' +
				'<p>If a prior standard existed, saving a blank would delete it.</p>'
			);
		} else if ( !templateExistsNow && !contentBlank )  {
			wpIssuesCRM.confirm (
				doSaveTemplate,
				false,
				'<p><strong>Save current reply content as standard for:</strong></p>' + identifiers + 
				'<p>This reply will be available for you to load when this issue/pro-con combination is selected in the future.</p>' +
				'<p>Also, if you select "Train" when you "Send", this reply will be preloaded for your review if the same subject line and content are received.</p>' 
			);
		} else if ( templateExistsNow && contentBlank ) {
			wpIssuesCRM.confirm (
				doSaveTemplate,
				false,
				'<p><strong>Delete standard reply for:</strong></p>' + identifiers + 
				'<p>Saving a blank standard reply deletes the existing standard reply.</p>'
			);
		} else { // templateExists and !contentBlank
			wpIssuesCRM.confirm (
				doSaveTemplate,
				false,
				'<p><strong>Overwrite existing standard reply for:</strong></p>' + identifiers			
			);		
		}
	}
	
	function doSaveTemplate() {
		resetSaveButtonColor();
		resetLoadButtonColor();
		data = {
			pro_con_value:  $( "#pro_con" ).val(),
			template_title: 'Reply to "' + $("#issue-selectmenu-display").val() + '" -- ' + $("#pro_con-selectmenu-display").val() ,
			template_content: tinymce.activeEditor.getContent()
		}
		wpIssuesCRM.ajaxPost( 'email_message', 'save_update_reply_template', $( "#issue" ).val(), data,  function( response ) {
			wpIssuesCRM.usedStandard = true;	
		});
	}

	wpIssuesCRM.loadReplyTemplate = function() {
		resetSaveButtonColor();
		resetLoadButtonColor();
		wpIssuesCRM.ajaxPost( 'email_message', 'get_reply_template', $( "#issue" ).val(), $( "#pro_con" ).val(),  function( response ) {
			if ( response ) { 
				tinymce.activeEditor.setContent( response );
				wpIssuesCRM.usedStandard = true;	
			} else {
				wpIssuesCRM.alert ( 
					'<p><strong>No standard reply set for:</strong></p>' +
					issueProConList()
				);
			}
		});
	}
	
	wpIssuesCRM.insertDearToken = function() {
	
		tinymce.activeEditor.execCommand('mceInsertContent', false, wpIssuesCRMSettings.dearToken );
	
	}	
	/*
	*
	* supporting functions
	*
	*/
	function manageApproveAssignButtons() {
		/*
		* enable/disable assign/approve buttons -- could do at jumpToSubjectLine, but doing it here makes
		* possibility of adding message specific legends in enabled cases
		*
		*/
		// enable or disable approval/unapproval button based on tab selection and message line count; is enabled for all users
		if ( !( ['CATEGORY_ASSIGNED', 'CATEGORY_READY'].indexOf(wpIssuesCRM.inboxSelectedTab) > -1 || 
			wpIssuesCRM.currentMessageVars.countMessages > 1  ) ){
			$( '#wic-email-approve-button' ).prop( 'disabled',true ).addClass('ui-state-disabled');
		} else {
			$( '#wic-email-approve-button' ).prop( 'disabled',false ).removeClass('ui-state-disabled');
		}
		// enable/disable staff reassignment button based on user capability and message count; is enabled in all tabs
		if ( ! wpIssuesCRMSettings.canViewAllEmail || wpIssuesCRM.currentMessageVars.countMessages > 1 ){
			$( '#assigned-case-popup-button' ).prop( 'disabled',true ).addClass('ui-state-disabled').attr("title","Cannot assign/reassign.");
		} else {
			$( '#assigned-case-popup-button' ).prop( 'disabled',false ).removeClass('ui-state-disabled');
			$( '#assigned-case-popup-button' ).prop("title", wpIssuesCRM.assignedStaff > 0 ? "Assigned -- click to reassign." : "Unassigned -- click to assign.");
		}

		/*
		*
		* choose staff button color
		*/
		var color;
		if( wpIssuesCRM.currentMessageVars.countMessages > 1 ) {
			color = '#ddd'
		} else if ( wpIssuesCRM.assignedStaff > 0 ) {
			color = 'green'
		} else {
			color = '#999';
		}
		$( "#assigned-case-popup-button" ).css( "color", color );
	}
		
	function currentUID() {
		return wpIssuesCRM.currentMessageVars.uidArray[wpIssuesCRM.currentMessageVars.activeMessage];
	}
	// handle save/load buttons
	function templateExists() { 
		return -1 <  templatedProConArray.indexOf ( $( "#pro_con" ).val() ? $( "#pro_con" ).val() : 'blank' );	
	}
	/* 
	* load button is highlighted (or not) on change of issue or p/c
	* -- is reset on either a load or a save
	* -- is not altered by change of the text
	*/
	function setLoadButtonColor () {
		// if a template exists for the current issue/pro_con combination, highlight the load button
		if ( templateExists() ) {
			$( ".mce-ico.mce-i-restoredraft" ).addClass('highlight-tinymce-button');		
		} else {
			$( ".mce-ico.mce-i-restoredraft" ).removeClass('highlight-tinymce-button');	
		}
	}
	function resetLoadButtonColor () {
		$( ".mce-ico.mce-i-restoredraft" ).removeClass('highlight-tinymce-button');	
	}
	
	/* 
	* save button is highlighted when changed -- linked in tinymce init (with caching delay)
	* -- is reset on either a load or a save
	* -- can get rehighlighted if text is further changed
	*/
	function setSaveButtonColor () {
		$( ".mce-ico.mce-i-save" ).addClass('highlight-tinymce-button');		
	}
	function resetSaveButtonColor () {
		$( ".mce-ico.mce-i-save" ).removeClass('highlight-tinymce-button');		
	}
	
	function issueProConList() {
		return '<ul>'+
				'<li><ul class = "save-reply-confirm"><li class= "save-reply-confirm-item caption">Issue:  </li><li class= "save-reply-confirm-item">' + $("#issue-selectmenu-display").val() + '</li></ul></li>' +
				'<li><ul class = "save-reply-confirm"><li class= "save-reply-confirm-item caption">Pro/Con:</li><li class= "save-reply-confirm-item">' + $("#pro_con-selectmenu-display").val() + '</li></ul></li>' +
			'</ul>';
	}
	
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
