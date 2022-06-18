/*
*
* email-process.js
*/


// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {
	
	// shared objects among  functions within this closure
	var processEmaildata 	= {}; 		// current action
	var undoPopupObject 	= false;	// the object storing the undo dialog
	var undoTimer 			= false;	// pointer to current timer for undo
	var lastDeleted 		= false;	// last deleted array (may be multi lines if blocked)
	var lastDeletedUids		= false;	// the list of uids in the target line that was deleted and defined the block

	// prepares instructions for the server and seeks user confirmation
	wpIssuesCRM.processEmail = function( event ){
		// before going anywhere, trigger the save from the editor to the textarea and close the last undo link
		if ( tinyMCE.activeEditor ) {
			tinyMCE.activeEditor.save();
		}
		wpIssuesCRM.removeUndo();	
		// handle click on span within button  
		var eventVal = $( event.target ).val();
		if ( ! eventVal ) { 
			eventVal = $( event.target ).closest( ".trigger-email-process-button" ).val();
		}		

		// handle approval processing with different ui
		if ( 'approve' == eventVal ) {
			toggleApproval();
			return;
		}

		if( !wpIssuesCRMSettings.canSendEmail) {
			wpIssuesCRM.alert( '<p><em>Sorry, your user role cannot send emails, but you can submit them for approval' + 
				' <span style="color: #aaa" class="dashicons dashicons-thumbs-up"></span>. or withdraw them <span style="color: #aaa" class="dashicons dashicons-thumbs-down"></span>.</em><p>' );
			return;
		}
		
		// handle sweep processing with different ui
		if ( 'sweep' == eventVal ) {
			handleSweep();
			return;
		}

		// not sweeping or approving, so cache the active line object and set up its uids for use in the next undo link
		lastDeleted 	= wpIssuesCRM.activeLine.clone();
		lastDeletedUids = wpIssuesCRM.activeLine.find( ".UIDs").text();
		// handle blocks and deletes separately -- simpler transaction
		if ( 'delete' == eventVal || 'block' == eventVal ) {
			handleDeleteBlock ( "block" == eventVal );
			return;
		}
		// otherwise proceed
		processEmaildata = {
			// in sweep mode, will attempt reply, but will always skip reply for subjects where no saved template
			reply: 		'reply' == eventVal,
			// triggers subject save -- toggled by check box in confirm window 
			train: 		false, 								
			// triggers subject lookup and read of whole inbox 										
			sweep:		false,
			// assigned case -- set in individual message view (and only if single message) -- reset if sweeping
			assigned: 	wpIssuesCRM.assignedStaff,
   			// remaining variables may be blank if sweeping 				
			uids:		wpIssuesCRM.currentMessageVars.uidArray,
			subject:	wpIssuesCRM.currentMessageVars.activeSubject, 
			issue: 		$( "#issue" ).val(),
			template: 	$( "#working_template" ).val(), 
			pro_con: 	$( "#pro_con" ).val(),
			/*
			* setting the following variables, but they will be ignored by email-process.php except for non-sweep single message
			* 	-- in case of a multi-message line, they will have the value of the displayed message ( so not useful in multi-message )
			*/
			subjectUI:	$( "#" + "envelope-edit-wrapper #message_subject").val(),
			to_array:	wpIssuesCRM.email_tile_to_address_array( $( "#envelope-edit-wrapper #message_to")[0] ),
			cc_array:	wpIssuesCRM.email_tile_to_address_array( $( "#envelope-edit-wrapper #message_cc")[0] ),
			bcc_array:	wpIssuesCRM.email_tile_to_address_array( $( "#envelope-edit-wrapper #message_bcc")[0] ),
			include_attachments: $( "#envelope-edit-wrapper #include_attachments").prop("checked"),
			
		
		}
		// if no addresses or any invalid, return after alert
		if ( !  wpIssuesCRM.validateEmailArrays ( processEmaildata ) ) {
			return;
		}
		if ( ! $( "#issue" ).val() ) {
			wpIssuesCRM.alert(
				'<p>Please complete the Issue to Assign field.</p>' +
				'<p>Emails are recorded by saving an activity record.  Each activity record must be assigned an issue.</p>' +
				'<p>Issues are just Wordpress posts, but may be public or private.</p>' + 
				'<p>Create new issues without leaving the email template editor by using the <u>New Issue</u> link below the issue selector.</p>'
			)	
		} else if ( processEmaildata.reply && processEmaildata.template.length < 3 ) {
			wpIssuesCRM.alert (
				'<p>Cannot reply without specifying a message.</p>' +
				'<p>Message currently has less than 3 characters -- give them a little more!</p>'
			)
		} else {
			if ( wpIssuesCRM.currentMessageVars.countMessages > 1 || wpIssuesCRM.usedStandard || !processEmaildata.reply ) {
				wpIssuesCRM.confirm (
					executeProcessEmail,
					false,
					'<p><strong>Please confirm actions:</strong></p>' +
					'<ol><li>Select messages in current inbox subject line:<br /><em>' + processEmaildata.subject + '</em></li>' +
					'<li>Record them as assigned to issue:<br /><em>' + $( "#issue").next().val() + '</em></li>' +
						( processEmaildata.reply ? '<li>Queue ' + (  wpIssuesCRM.currentMessageVars.countMessages > 1 ? ' <b><em>identical</em></b> templated replies to all selected messages ' : ' reply '  )  + 'for mailing.</li>' : '' ) +
					'</ol>' +
					'<p>Check if you want to map this subject line to this issue and pro/con combination:</p>' +
					'<div id="wic-control-remember-this" class="wic-control">'  +
						'<label class="wic-label remember-this" for="remember_this">Map subject line?</label>' +
						'<input id="remember_this" class="wic-input-checked" type="checkbox" value="1" name="remember_this">' +
					'</div>' +
					'<p>If you check "Map subject line", future incoming messages with the same subject line will appear as grouped in the inbox as "<em>Mapped</em>" and you will have the option ' + 
						'of sweeping them.  Do not map subject lines that could be associated with varying content, like "Thank you".</em></p>' 
				)
				$( "#wic-control-remember-this #remember_this" ).change( function () {
					processEmaildata.train = ! processEmaildata.train
				});
			// no confirm for only one message
			} else {
				executeProcessEmail();
			}
		}

	}
	
	// loop across standard arrays and check for presence and validity of addresses
	wpIssuesCRM.validateEmailArrays = function ( objectWithArrays ) {
		// has at least one addressee been supplied
		if ( 0 == objectWithArrays.to_array.length + objectWithArrays.cc_array.length + objectWithArrays.bcc_array.length ) {
			wpIssuesCRM.alert(
				'<p>Please supply at least one addressee for your message.</p>'
			);	
			return false;
		}
		// are all emails valid?
		var emailsValid = true;
		$.each( [ objectWithArrays.to_array, objectWithArrays.cc_array, objectWithArrays.bcc_array ], function( index, value ){
				$.each( this, function ( innerIndex, innerValue ) {
					emailsValid = wpIssuesCRM.validateEmail ( innerValue[1], false );
					return emailsValid; // break on false
				})
				return emailsValid; // break on false
		});
		if ( ! emailsValid ) {
			wpIssuesCRM.alert(
				'<p>Please check email addresses highlighted in red.</p>'
			);	
			return false;	
		} 	
		// no errors found, continue validating
		return true;
	}
	
	
	// actually transmits server instructions, handles returns
	function executeProcessEmail () {
		/*
		* note that processEmaildata could get overwritten in a series of actions, but is not used in the asynch callback
		*/
		// set form dirty flag
		wpIssuesCRM.formDirty = true;
		if ( processEmaildata.sweep ) {
			// set sweep flag to prevent double submits of sweeps
			wpIssuesCRM.pendingSweep = true;
			// mark lines in question with red italic font and spinner
			wpIssuesCRM.activeLine.addClass ( "item-sending");
			var spinner;
			wpIssuesCRM.activeLine.each ( function () {
				spinner = $( "#inbox-ajax-loader img" ).clone();
				$(this).find( ".subject-line-item.subject").prepend(' ').prepend( spinner );
			});			
		} else {
			// working with single active line, plan to go to next line
			var nextLine = wpIssuesCRM.activeLine.nextAll("li:not(.item-sending)")[0];
			// go ahead and do the remove and let user proceed while the task is running;
			wpIssuesCRM.activeLine.remove();
			wpIssuesCRM.jumpToSubjectLine( nextLine, 250 );
		}
		// increment count of pending process requests for formDirty logic ( can't use other xhr queue )
		wpIssuesCRM.pendingMailRequests++;

		// issue process requests
		wpIssuesCRM.ajaxPost( 'email_process', 'handle_inbox_action_requests',  '', processEmaildata,  function( response ) {
			// count down on pending requests for formDirty logic
			wpIssuesCRM.pendingMailRequests--;
			if ( !wpIssuesCRM.pendingMailRequests ) {
				wpIssuesCRM.formDirty = false;
			} 
			// reset sweep flag if that's what we just did
			if ( response.data.sweep ) {
				// delete the right lines ( have not done so yet in sweep );
				$( ".inbox-subject-line.trained-subject" ).remove();
				wpIssuesCRM.pendingSweep = false;
			} else {
				doUndoPopup();
			}
		});		
	}
	
	/*
	*
	*
	* functions for handling delete or block
	*
	*
	*/
	// handle block and delete actions
	function handleDeleteBlock( block ) {

		// triggers application of new filter before deletion of message
		processEmaildata = {
			block: 			block,	
			wholeDomain: 	false,
			sweep:			false, // maintain this variable for cosmetics in uid reservation log
			deleter:		true,  // redundant in this routine, but need to distinguish action in undo popup;
			uids:			wpIssuesCRM.currentMessageVars.uidArray,
			fromEmail: 		wpIssuesCRM.currentMessageVars.fromEmail,
			fromDomain: 	wpIssuesCRM.currentMessageVars.fromDomain,
			// subject used only for recording in uid_reservation 
			subject:		wpIssuesCRM.currentMessageVars.activeSubject 
		} 
		if ( ! block ) {
			// no confirmation on deletes
			executeDeleteBlock();
		} else {
			// do not handle multiple messages on block
			if ( wpIssuesCRM.currentMessageVars.countMessages > 1 ) {
				wpIssuesCRM.alert ( 
					'<p>Cannot block sender when multiple emails are grouped under a single subject line -- shift to single line view if you want to block grouped emails.</p>'
				);
			} else {
				wpIssuesCRM.confirm (
					executeDeleteBlock,
					false,
					'<p><strong>Please confirm block:</strong></p>' +
					'<ol>' + 
						'<li>Delete this message without recording it.</li>' +
						'<li>Silently delete all future messages from <br /><em>' + wpIssuesCRM.currentMessageVars.fromEmail + '</em></li>' +
					'</ol>' +
					( isDomainBlockable ( processEmaildata.fromDomain ) ?
						(
						'<p>Check if you also want to block other senders from the same domain:</p>' +
						'<div id="wic-control-block-domain" class="wic-control">'  +
							'<label class="wic-label block-domain" for="block-domain">Block whole domain <strong>' +  wpIssuesCRM.currentMessageVars.fromDomain + '</strong>?</label>' +
							'<input id="block-domain" class="wic-input-checked" type="checkbox" value="1" name="block-domain">' +
						'</div>' 
						) :
						'<p>Not offering the option to block whole domain <strong>' + wpIssuesCRM.currentMessageVars.fromDomain + '</strong> because it is too popular to safely block.</p>'
						
					) +
					'<p>The new block rule will automatically be applied to other messages that are already in your inbox (upon refresh).</p>' +
					'<p>Blocked/deleted emails can be recovered from the WP_Issues_CRM_Processed folder on your mail server.</p>' +
					'<p>You can view and remove filtered address/domains from the main inbox menu under <span class="dashicons dashicons-warning"></span>.</p>'
				)
				$( "#wic-control-block-domain #block-domain" ).change( function () {
					processEmaildata.wholeDomain = ! processEmaildata.wholeDomain
				});
			} // not multiple messages
		} // block
	
	}

	function executeDeleteBlock() { 
		/*
		* for delete/block, do no UI indicators --  worst case, user redeletes an object already deleted.
		*
		* delete and scroll before completing the deletion
		*/
		/*
		* add dirty reset in 4.3.0
		* note question: this appears safe, but is not consistent with treatment in executeProcessEmail which does dirty false only on return
		*/
		wpIssuesCRM.formDirty = false;
		// determine the next line
		var nextLine = wpIssuesCRM.activeLine.nextAll("li:not(.item-sending)")[0]; //theoretically a long sweep could be in progress
		// actually delete the line
		wpIssuesCRM.activeLine.remove();
		// issue scroll requests (with delay in case last line delete)	
		wpIssuesCRM.jumpToSubjectLine( nextLine, 250 );
		// process deletes
		wpIssuesCRM.ajaxPost( 'email_process', 'handle_delete_block_request',  '', processEmaildata,  function( response ) {
			doUndoPopup();
			// do not server handle errors -- have moved on and failure to delete or block is detectable and fixable
		});	

	}


	function isDomainBlockable ( testDomain ) {
		var popularDomains = [
		  /*
		  *
		  * list of popular domains from https://github.com/mailcheck/mailcheck/wiki/List-of-Popular-Domains distributed under the MIT license
		  *
		  *	The MIT License (MIT)
		  *	Copyright © 2012 Received Inc, http://kicksend.com
  		  *
		  * Permission is hereby granted, free of charge, to any person obtaining a copy
		  *	of this software and associated documentation files (the “Software”), to deal
		  *	in the Software without restriction, including without limitation the rights
		  *	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
		  *	copies of the Software, and to permit persons to whom the Software is
		  *	furnished to do so, subject to the following conditions:
		  *
		  *	The above copyright notice and this permission notice shall be included in
		  *	all copies or substantial portions of the Software.
		  *
		  *	THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
		  *	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
		  *	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
		  *	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
		  *	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
		  *	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
		  *	THE SOFTWARE.
		  *
		  */
		  /* Default domains included */
		  "aol.com", "att.net", "comcast.net", "facebook.com", "gmail.com", "gmx.com", "googlemail.com",
		  "google.com", "hotmail.com", "hotmail.co.uk", "mac.com", "me.com", "mail.com", "msn.com",
		  "live.com", "sbcglobal.net", "verizon.net", "yahoo.com", "yahoo.co.uk",

		  /* Other global domains */
		  "email.com", "games.com" /* AOL */, "gmx.net", "hush.com", "hushmail.com", "icloud.com", "inbox.com",
		  "lavabit.com", "love.com" /* AOL */, "outlook.com", "pobox.com", "rocketmail.com" /* Yahoo */,
		  "safe-mail.net", "wow.com" /* AOL */, "ygm.com" /* AOL */, "ymail.com" /* Yahoo */, "zoho.com", "fastmail.fm",
		  "yandex.com","iname.com",

		  /* United States ISP domains */
		  "bellsouth.net", "charter.net", "comcast.net", "cox.net", "earthlink.net", "juno.com",

		  /* British ISP domains */
		  "btinternet.com", "virginmedia.com", "blueyonder.co.uk", "freeserve.co.uk", "live.co.uk",
		  "ntlworld.com", "o2.co.uk", "orange.net", "sky.com", "talktalk.co.uk", "tiscali.co.uk",
		  "virgin.net", "wanadoo.co.uk", "bt.com",

		  /* Domains used in Asia */
		  "sina.com", "qq.com", "naver.com", "hanmail.net", "daum.net", "nate.com", "yahoo.co.jp", "yahoo.co.kr", "yahoo.co.id", "yahoo.co.in", "yahoo.com.sg", "yahoo.com.ph",

		  /* French ISP domains */
		  "hotmail.fr", "live.fr", "laposte.net", "yahoo.fr", "wanadoo.fr", "orange.fr", "gmx.fr", "sfr.fr", "neuf.fr", "free.fr",

		  /* German ISP domains */
		  "gmx.de", "hotmail.de", "live.de", "online.de", "t-online.de" /* T-Mobile */, "web.de", "yahoo.de",

		  /* Russian ISP domains */
		  "mail.ru", "rambler.ru", "yandex.ru", "ya.ru", "list.ru",

		  /* Belgian ISP domains */
		  "hotmail.be", "live.be", "skynet.be", "voo.be", "tvcablenet.be", "telenet.be",

		  /* Argentinian ISP domains */
		  "hotmail.com.ar", "live.com.ar", "yahoo.com.ar", "fibertel.com.ar", "speedy.com.ar", "arnet.com.ar",

		  /* Domains used in Mexico */
		  "hotmail.com", "gmail.com", "yahoo.com.mx", "live.com.mx", "yahoo.com", "hotmail.es", "live.com", "hotmail.com.mx", "prodigy.net.mx", "msn.com",

		  /* Domains used in Brazil */
		  "yahoo.com.br", "hotmail.com.br", "outlook.com.br", "uol.com.br", "bol.com.br", "terra.com.br", "ig.com.br", "itelefonica.com.br", "r7.com", "zipmail.com.br", "globo.com", "globomail.com", "oi.com.br"
		];
	
		return -1 == $.inArray( testDomain, popularDomains );
	}

	/*
	*
	*
	* function for setting up sweep
	*
	*/
	function handleSweep () {
		// base configuration
		processEmaildata = {
			// in sweep mode, will attempt reply, but will always skip reply for subjects with no saved template
			reply: 		true,
			train: 		false, 								
			sweep:		true,
			// assigned case -- set in individual message view (and only if single message) -- reset if sweeping
			assigned: 	'',
   			// remaining variables may be blank if sweeping 				
			uids:		[], // to be completed below
			subject:	wpIssuesCRM.currentMessageVars.activeSubject, 
			issue: 		$( "#issue" ).val(),
			template: 	$( "#working_template" ).val(), 
			pro_con: 	$(	"#pro_con" ).val(),
		}
		// complete configuration	
		if ( wpIssuesCRM.pendingSweep ) {
			wpIssuesCRM.alert ( '<p>Sweep already in progress.</p><p>Sometimes a sweep can take many seconds.  </p>' +
				'<p>You can close the page,  but you will not receive any error messages that may arise. </p>'
			)
			return false;
		}
		// get the set of mapped lines
		wpIssuesCRM.activeLine = $( ".inbox-subject-line.trained-subject" ); // trained subject is set in WIC_Entity_Email_Inbox::load_inbox and defines the lines with messages meeting sweep criteria
		// reset the UID list
		var selectedMessages = 0;
		var firstLine = true;
		wpIssuesCRM.activeLine.each ( function() {
			messageCount = $( this ).find (".subject-line-item.count .inner-count" ).text()
			selectedMessages = selectedMessages + Number( messageCount )
			processEmaildata.uids = processEmaildata.uids.concat( $( this ).find (".subject-line-item.UIDs" ).text().split(',') );
			firstLine = false;
		});
		if ( 0 == wpIssuesCRM.activeLine.length ) {
			wpIssuesCRM.alert ( '<p>Currently no trained subject lines to sweep in the Inbox.</p><p>If you have directly added subject lines, refresh the inbox ( <span class="dashicons dashicons-update"></span> ) to apply the new mappings.</p>' );
			return false;
		} else {
			wpIssuesCRM.confirm (
				executeProcessEmail,
				false,
				'<p><strong>Please confirm actions for sweep:</strong></p>' +
				'<ol><li>Select messages with mapped subject lines ( '  + selectedMessages + ' messages with ' + 	wpIssuesCRM.activeLine.length + ' subject lines).</li>'  +
				'<li>Record messages if no reply template has been saved. Reply and record if there is a saved template for the mapped subject line.</li>' +
				'</ol>'
			);
		}	
	}

	// non-process function to toggle approval status
	toggleApproval = function() {
		// alert regarding multi-message subjects
		if ( wpIssuesCRM.currentMessageVars.countMessages > 1 ) {
			wpIssuesCRM.alert (
						'<p>Cannot take approval or disapproval action when handling more than one message in a subject line.</p>' +
						'<p>Switch the inbox out of grouped mode to manage approval status (<b><em>1</em></b>).</p>'		
			)
		} else {
			// toggle approval status 
			newStatus = $( "#wic-email-approve-button span").hasClass('dashicons-thumbs-down') ? 0 : 1;
			wpIssuesCRM.saveValueToInboxImage ( 'reply_is_final',  newStatus );
			// move to next line -- toggled status should move message to new tab
			var nextLine = wpIssuesCRM.activeLine.nextAll("li:not(.item-sending)")[0];
			wpIssuesCRM.activeLine.remove();
			wpIssuesCRM.jumpToSubjectLine( nextLine, 250 );	
		}		

	
	}

	// bulk action on delete or block from inbox level
	 wpIssuesCRM.doDeleteBlockSelected = function ( block ) {
		// first close any prior undo popup
		wpIssuesCRM.removeUndo();	
		// collect all selected UIDs
		var toBeDeletedUIDs=[];
		var spinner;
		$( "#wic-load-inbox-inner-wrapper .inbox-subject-line-selected").each ( function () { // must specify div! other boxes also have subject lines
			$.merge ( toBeDeletedUIDs, $(this).find(".subject-line-item.UIDs").text().split(','))
			spinner = $( "#inbox-ajax-loader img" ).clone();
			$(this).find( ".subject-line-item.subject").prepend(' ').prepend( spinner );
		})
		$( "#wic-email-bulk-delete-button, #wic-email-bulk-block-button" ).prop ( "disabled", true );
		processEmaildata = {
			block: 			block,	
			wholeDomain: 	false,
			sweep:			false, // maintain this variable for cosmetics in uid reservation log
			deleter:		true,  // redundant in this routine, but need to distinguish action in undo popup;
			uids:			toBeDeletedUIDs,
			fromEmail: 		'bulk',
			fromDomain: 	'bulk',
			// subject used only for recording in uid_reservation 
			subject:		'bulk',
			isBulk:			true 
		}
		wpIssuesCRM.ajaxPost( 'email_process', 'handle_delete_block_request',  '', processEmaildata,  function( response ) {
			// reload page on success
			$( "#wic-email-bulk-delete-button, #wic-email-bulk-block-button" ).prop ( "disabled", false );
			wpIssuesCRM.loadSelectedPage()
			doUndoPopup();
		});

	}


	function doUndoPopup () {
		
		var countMessages 	= processEmaildata.uids.length;
		var action;
		var undoRequested = false; // prevent double click on same popup
		
		if ( processEmaildata.block ) {
			action = ' deleted and sender blocked';
		} else if ( processEmaildata.deleter ) {
			action = ' deleted'
		} else if ( processEmaildata.reply ) {
			action = ' recorded -- repl' + ( countMessages > 1 ? 'ies ' : 'y ' ) + 'sent'
		} else {
			action = ' recorded'
		}
		
		var undoMessage = countMessages + ' message' + ( countMessages > 1 ? 's ' : ' ' ) + action + '. <strong><u>Undo?</u></strong>';
		
		// open dialog popup
		undoPopup = $.parseHTML ( 
			'<div id="undo-email-process-dialog" title="Undo . . .">' +
				'<div id="undo-message-button">' + undoMessage + '</div>' +
			'</div>'
		);
		undoPopupObject = $( undoPopup );
		undoPopupObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: false,
			close: function ( event, ui ) {
				undoPopupObject.remove();
			},
			dialogClass: "wic-undo-popup",
			position: { my: "top", at: "top-20", of: "#wic-inbox-message-review" }, 	
			width: 350,
			height: 30,
			modal: false 
		});

		$( "#undo-message-button" ).on( "click", function() { 
			if ( ! undoRequested ) {
				undoRequested = true;
				executeUndo()
			}
		});
		wpIssuesCRM.clearTimer ( undoTimer );
		undoTimer = setTimeout ( wpIssuesCRM.removeUndo, 10000 ); 
	}

	function executeUndo() {
		// notify that undo is in progress and protect from interruptions
		undoPopupObject.dialog( "option", "modal", true );
		$( "#undo-message-button" ).text( "Undoing . . . " ).addClass( "flash" ); 
		wpIssuesCRM.ajaxPost( 'email_unprocess', 'handle_undo',  '', processEmaildata,  function( response ) {
			// in case of fully or partially successful delete, restore the inbox
			var returnToLine = false;
			if ( response.delete_successful ) {
				if ( processEmaildata.isBulk )  {
					wpIssuesCRM.loadSelectedPage()
				} else {
					// note: this routine is written to handle multiple deletes, but this case is no longer created
					lastDeleted.each( function() {  
						$this 			= $(this);
						var huntUIDs 	= $this.find( ".UIDs" ).text();
						var huntDate 	= $this.find( ".oldest" ).text();
						var foundInsert = false;
						// reinsert the element back in the inbox at the appropriate place
						$inboxList = $( ".inbox-subject-line" );
						$inboxList.each( function () { 
							var $innerThis = $( this );
							var $innerDate =  $innerThis.find( ".oldest" ).text();
							// going down the list as it appears in the dom -- may be in ascending or descending order
							if 	( 
									(   wpIssuesCRM.inboxAscending.inbox && $innerDate > huntDate ) || 
									( ! wpIssuesCRM.inboxAscending.inbox && $innerDate < huntDate ) 
								) { 
									$innerThis.before( $this );
									if ( huntUIDs == lastDeletedUids ) {
										returnToLine = $innerThis.prev()[0];
									}
									foundInsert = true;
									return false; 
							} // close found insert point 
						}); // close inner loop through inbox list
						// possible that was actually the last record; 
						if ( !foundInsert ) {
							$inboxList.last().after( $this );
							if ( huntUIDs == lastDeletedUids ) {
								returnToLine = $inboxList.last()[0];
							}						
						} // close not found insert point 
					}); // close outer loop through deleted lines
					// returnToLine should always be true at this point
					if ( returnToLine ) {
						wpIssuesCRM.jumpToSubjectLine( returnToLine, 0 );
					}
				} // close not bulk
			} else {// close delete_successful ?
				wpIssuesCRM.alert ( 'Sorry. Could not undo: ' + response.message ); // blank if successful
			}	
			wpIssuesCRM.removeUndo();
		});	// close return from ajax Post
		
	}

	wpIssuesCRM.removeUndo = function() {
		if ( undoPopupObject ) {
			if ( undefined != undoPopupObject.dialog( "instance" ) ) {
				undoPopupObject.dialog ( "close" );
			}
			undoPopupObject = false;
		}
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
