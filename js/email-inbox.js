/*
*
* email-inbox.js
*
*	Note on validation and options settings:  
*		All the dynamic settings appearing in the inbox area -- issue, pro-con, template --	are controlled in this script.
*		They are transmitted by ajax on the submission of an action and are not saved otherwise.
*	  	Note that these values persist until the email inbox page is abandoned.
*
*	See also notes in email-process.js.
*
*	INTERFACE NOTE:
*   The inbox list loaded by loadInbox includes the following interface variables coming from the server
*		-- the class "trained-subject" which identifies sweepable lines
*		-- the list of UIDs for each subject line
*		-- the message count for each line (which could be reconstructed, but is convenient)
*	The other list elements are just display.  Single view is the same as multi view on the client side.
*
*
*/
jQuery( document ).ready( function($) { 

	// initializer -- normally triggered by AJAX
	$( "#wp-issues-crm" )	.on ( "initializeWICForm", wpIssuesCRM.initializeInboxVars )

	// also directly invoke inbox initialize in case accessed by GET  -- does nothing if inbox not displayed
	wpIssuesCRM.initializeInboxVars ();
	
});



// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	var pendingInboxLoad = false, // prevent line loads while box load pending
		// box view variables	
		inboxGrouped = {
			inbox: true,
			draft: false,
			sent: false,
			done: false,
			outbox: false,
			saved: false
		},
		nextPage = {
			inbox: 0,
			draft: 0,
			sent: 0,
			done: 0,
			outbox: 0,
			saved: 0
		},
		// search control
		latestSearch = '', 	// to discard old filter requests
		acTimer = false, 		// timer for filter search delay 
		selectedPage = 'inbox';
		wpIssuesCRM.inboxSelectedTab = '';
		selectedStaff = 0;
	// define this inbox view variable as global for access in restoring undo
	wpIssuesCRM.inboxAscending = {
			inbox: false,
			draft: false,
			sent: false,
			done: false,
			outbox: false,
			saved: false,
		};

	// fired on load inbox
	wpIssuesCRM.initializeInboxVars = function () {
		// do nothing if the inbox is not open
		if ( ! $( "#wic-email-inbox" )[0] ) { return }
		// initialize a load parm object for tracking inbox content
		wpIssuesCRM.lastLoadParms = {}, // reported by last load operation
		// reset last search
		latestSearch = '';  
		// initialize a counter for pending email process requests ( can't use other xhr queue )
		wpIssuesCRM.pendingMailRequests = 0;
		// initialize a sweep flag used to prevent double sweep submits
		wpIssuesCRM.pendingSweep = false;
		// set up current message variables -- initialized to blank for use in sweeps (controls detail view scrolling)
		wpIssuesCRM.currentMessageVars = { // used for interface from inbox to detail (processing gets detail from form)
			activeSubject		 : '',
			countMessages		 : 0,
			uidArray			 : [],
			activeMessage		 : 0,
		}
		// will be set to the clicked (or scrolled to) inbox line element	
		wpIssuesCRM.activeLine = false; 
		// bind all listeners for inbox pages (to already loaded page containers or other already loaded elements )
		loadInboxListeners()
		// get page load started
		$( ".wic-active-inbox-page" ).trigger( "click" );
	}

	/*
	*
	* define functions for switching and presenting different inbox and detail views
	* -- doSelectedPage for switching among major pages;
	*
	* within inbox page, states are 
	*	the inbox list
	*   the view of a group of messages within a subject line
	*		the templated view mode vs template edit mode in right hand side
	*		the scroll of messages within subject line (which changes LHS and the 
	* so, transition functions are:		
	* -- loadInbox loads the inbox list
	* -- jumpToSubjectLine switches subject lines within list 
	*		always then does scrollMessage to get first message from subject line
	*		triggers showMessageDetails to switch view after message is ready (matters if coming from list).
	* -- showMessageDetails -- for switching from inbox list view to particular subject line view 
	* -- closeMessageDetails --switching back to inbox hide/show
	* -- scrollMessage -- for switching/loading messages within subject line view
	*/

	// switch among selected subpages
	function doSelectedPage( event ) {
		// handle click on span
		var $target;
		if ( $( event.target ).hasClass ( "wic-inbox-page-label") ) {
			$target = $(event.target).parent();
		} else if ( $( event.target ).hasClass ( "wic-inbox-tab-count") ) {
			$target = $(event.target).parent().parent();
		} else {
			$target = $(event.target);
		}
		selectedPage = $target.children(":first-child").text();
		$( ".wic-active-inbox-page").removeClass ( 'wic-active-inbox-page ');
		$target.addClass( 'wic-active-inbox-page');
		// note: this full hide logic is slightly more efficient than a stacked layer visibility swapping model
		$( ".wic-page-option-list" ).find( ".wic-inbox-page-value" ).each( function () {
			var page = $( this ).text(); 
			$( "#wic-email-" + page + "-content" ).css( "display",  page == selectedPage ? "block" : "none" )
		});
		if ( ['inbox','sent','done','outbox','saved','draft'].indexOf( selectedPage ) > -1 ) {
			// set up buttons/legend	
			$( ".inbox-mode-button, .page-link, #wic-inbox-sweep-button, #wic-inbox-title-legend, #wic-email-inbox #subject, #wic-filter-assigned-button").show();
			$( "#wic-inbox-title-alt-legend").hide(); 	 
			// initialize link-base for edit of issues if on first load
			if ( ! wpIssuesCRM.editMappedIssueLinkBase ) {
				wpIssuesCRM.editMappedIssueLinkBase = $( "#edit_mapped_issue_link" ).attr( "href" );
			}
			// do a refresh (since 3.2 -- refresh from local)
			wpIssuesCRM.loadSelectedPage();
		} else {
			// do show/hide of relevant buttons
			$( ".inbox-mode-button, .page-link, #wic-inbox-sweep-button, #wic-inbox-title-legend, #wic-email-inbox #subject, #wic-filter-assigned-button ").hide();
			$( "#wic-inbox-resize-button").show();
			// set up the header slot for the requested page 
			$( "#wic-inbox-title-alt-legend" ).css("display", "inline-block");
			if ( 'manage-subjects' == selectedPage ) {
				wpIssuesCRM.loadSubjectList()
			} else if ( 'manage-blocks' == selectedPage ) {
				wpIssuesCRM.loadBlockList()
			} else if ( 'settings' == selectedPage ) {
				wpIssuesCRM.loadSettingsForm()
			}
		}
	}

	// load the inbox list of subjects ( show on the inbox subpage )
	wpIssuesCRM.loadSelectedPage = function() { 
		// uncheck master checkbox for inbox
		$( "#inbox-master-checkbox" ).prop("checked", false );
		// set up loading UI
		$( ".inbox-mode-button, .page-link" ).prop( "disabled", true ); // prevent double submits
		var $loader = $( '#' + selectedPage + '-ajax-loader' );
		$loader.show();
		pendingInboxLoad = true;
		// if no selected tab, set to first
		if ( ! wpIssuesCRM.inboxSelectedTab ) {
			wpIssuesCRM.inboxSelectedTab = $( '.wic-inbox-tab.ui-state-default.ui-corner-top').first().addClass ( "ui-tabs-active"  ).attr("id"); 		
		}
		var inboxParms = {
			mode:  		inboxGrouped[selectedPage] ? 'grouped' : 'single',
			sort:  		wpIssuesCRM.inboxAscending[selectedPage] ? 'ASC' : 'DESC',
			page:		nextPage[selectedPage],
			filter:		$( "#wic-email-inbox #subject" ).val(),
			tab:		wpIssuesCRM.inboxSelectedTab,
			staff:		selectedStaff
		}
		wpIssuesCRM.ajaxPost( 'email_inbox', 'load_' + selectedPage,  0, inboxParms,  function( response ) {
			// if this is not the last search submitted, discard it and leave buttons disabled
			if ( response.last_load_parms['filter'].trim() != latestSearch.trim() ) {
				return;
			// reload page if page_ok not set -- indicates pages shifted due to consolidation	
			} else if ( ! response.last_load_parms['page_ok'] ) { 
				nextPage.inbox = 0;
				wpIssuesCRM.loadSelectedPage();
				return;
			}
			// release loading UI
			$( ".inbox-mode-button, .page-link" ).prop( "disabled", false );
			$loader.hide();
			pendingInboxLoad = false;
			$( "#subject" ).removeClass ( "flash" );
			// set button value for group/ungroup here since inboxGrouped persists across inbox reentries from top form buttons
			$( "#wic-inbox-ungroup-button" ).html( inboxGrouped[selectedPage] ? '1' : 'n' );
			// record last tracking loaded folder for processing purposes -- not used by passive boxes
			if ( 'inbox' == selectedPage ) {
				 wpIssuesCRM.lastLoadParms = response.last_load_parms;
			}
			// load the header and remove previously loaded buttons
			$( "#wic-inbox-title-legend" ).html( response.inbox_header )
			// disable/enable buttons
			$prev = $( ".page-link.prev" );
			$next = $( ".page-link.next" );
			toggleDisabled ( $prev, response.nav_buttons['disable_prev'] );
			toggleDisabled ( $next, response.nav_buttons['disable_next'] );
			// $( "#wic-inbox-title-legend" ).next().after( $.parseHTML ( response.nav_buttons) );
			// load the inbox
			$( "#wic-load-" + selectedPage + "-inner-wrapper" ).html( response.inbox );
			// set styling of sweep button
			var sweepButton = $( "#wic-inbox-sweep-button")
 			if ( ! $( ".inbox-subject-line.trained-subject" ).length || 'inbox' != selectedPage  ) {
 				sweepButton.css("color","#ddd");
 			} else {
 				sweepButton.css("color","#0a0");
 			}
 			// update Tab Counts and inbox total count
 			if ( 'inbox' == selectedPage ) {
 				for (var category in response.tab_counts ) {
					$( "#" + category + " .wic-inbox-tab-count").text( ' ' + response.tab_counts[category] + ' ' );
					if ( null==response.tab_counts[category] || 0 == response.tab_counts[category] ) {
						$( "#" + category + " .wic-inbox-tab-count").hide()
						$( "#" + category ).addClass("empty-category-tab" ) 
					} else {
						$( "#" + category + " .wic-inbox-tab-count").show()
						$( "#" + category ).removeClass("empty-category-tab" ) 
					}
					if(( 'CATEGORY_ASSIGNED' == category || 'CATEGORY_READY' == category  ) && wpIssuesCRMSettings.canViewAllEmail ) {
						$( "#" + category ).addClass("assignment-category-tab" );						
					}
				}
  			} 

  			// enable or disable assignment selection button
  			if ( !( ['CATEGORY_ASSIGNED', 'CATEGORY_READY'].indexOf(inboxParms.tab) > -1 ) || !wpIssuesCRMSettings.canViewAllEmail ){
  				$( '#wic-filter-assigned-button' ).prop( 'disabled',true ).addClass('ui-state-disabled');
  			} else {
  				$( '#wic-filter-assigned-button' ).prop( 'disabled',false ).removeClass('ui-state-disabled');
  			}

 		
		 if ( response.stuck ) {
				// show alert for stuck reservation queue
				wpIssuesCRM.alert ( response.stuck );
				$( "#clear-reservation-queue-button" ).click ( function () {
					$( "#clear-reservation-queue-button" ).prop( "disabled", true );
					wpIssuesCRM.ajaxPost( 'email_uid_reservation', 'clear_old_uid_reservations',  '', '',  function( response ) {
						$( "#clear-reservation-queue-button" ).text( "Cleared" );
					});
				});
			} 
 		});		
	}


	// shift from inbox view to individual message detail view or from line to line in view
	wpIssuesCRM.jumpToSubjectLine = function ( subjectLine, delay ) {
		if ( undefined !== subjectLine ) {
			wpIssuesCRM.activeLine = $( subjectLine );
			wpIssuesCRM.currentMessageVars = {
				activeSubject	 	: wpIssuesCRM.activeLine.find ( ".subject-line-item.subject .actual-email-subject" ).text(),
				fromEmail			: wpIssuesCRM.activeLine.find ( ".subject-line-item.from-email" ).text(), // note used only by message block in single line view
				fromDomain			: wpIssuesCRM.activeLine.find ( ".subject-line-item.from-domain" ).text(), // note used only by message block in single line view
				countMessages	 	: Number( wpIssuesCRM.activeLine.find(".subject-line-item.count .inner-count").text() ),
				uidArray		 	: wpIssuesCRM.activeLine.find(".subject-line-item.UIDs").text().split(','),
				activeMessage	 	: 0, // first in array
			}
			// not carrying values from prior line -- too error prone -- force review
			// load message information 
			// true means switching Subject Line (passed through to php to reset issue/pro_con/template)
			wpIssuesCRM.scrollMessage( true );
		} else {
			/*
			* introduce headstart delay in case where going straight back to inbox after starting asynch transaction
			* in localhost testing a few milliseconds is enough for 30 delete/block transaction to get right sequence
			* allowing 250 for safety in other environments; only consequence of misorder is that user may get not found message when click on subject line
			*/
			if ( delay ) { 
				setTimeout ( wpIssuesCRM.closeMessageDetails, delay );
			} else { 
				wpIssuesCRM.closeMessageDetails()
			}
		}
	}
	/*
	* doMessageViewer -- for deleted or sent messages
	*/
	function doMessageViewer( inboxSubjectLine ) {
		var $line = $( inboxSubjectLine );
		if ( Number( $line.find( ".inner-count").text() ) > 1 ) {
			wpIssuesCRM.alert ( 'Switch to single line view (1 button) -- except in inbox, can only view one reply or message at a time.' );
		} else {
			if ( 'saved' == selectedPage ) { 
				wpIssuesCRM.viewSaved ( inboxSubjectLine ) 
			} else if ( 'draft' == selectedPage ) {
				wpIssuesCRM.editDraft ( inboxSubjectLine );
			} else {
				wpIssuesCRM.viewMessage ( selectedPage, inboxSubjectLine );
			}
		}
	}
	function confirmBlockSelected() {
		if ( !$( ".inbox-subject-line-selected" ).length ) {
			wpIssuesCRM.alert ( "No subject lines selected to archive and block sender.");
		} else {
			wpIssuesCRM.confirm (
				function () { wpIssuesCRM.doDeleteBlockSelected(true) } ,
				false,
				'<p><strong>Archive selected line items and block the senders?</strong></p>' + 
				'<p><em>Only the sender emails will be blocked, not the whole sender domains.</em></p>'
			)
		}
	}

	/*******************************************************************
	*
	* supporting functions
	*
	********************************************************************/
	function toggleDisabled ( $elem, state ) {
		elemDisabled = $elem.hasClass( "ui-state-disabled" );
		if ( elemDisabled != state ) {
			$elem.toggleClass( "ui-state-disabled" );
		}
	}

	/*
	*
	*
	* set up listeners on initialized form elements
	*
	*
	*/
	function loadInboxListeners() {
		$( "#wic-email-inbox" )
		// set up tab-like page swapper for inbox buttons
		.on ( "click", ".wic-inbox-page-list-item", doSelectedPage )
		// click to open particular subject line (from inbox)
		.on ( "click", ".inbox-subject-line", function ( event ) {
			var isSelected;
			if ( $(event.target).hasClass( "inbox-subject-line-checkbox") || $(event.target).hasClass( "inbox-subject-line-checkbox-wrapper")  ){
				if (  $(event.target).hasClass( "inbox-subject-line-checkbox")  ) {
					isSelected =  $(event.target).prop("checked");
				} else {
					isSelected = $(event.target).children().is(':checked');	
					isSelected = !isSelected;
					$(event.target).children().prop('checked', isSelected);
				}
				if ( isSelected ) {
					$(event.target).closest( ".inbox-subject-line").addClass ( "inbox-subject-line-selected" )
				} else {
					$(event.target).closest( ".inbox-subject-line").removeClass ( "inbox-subject-line-selected" )
				}
			} else if ( ! $( this ).hasClass( "item-sending" ) && ! pendingInboxLoad && 'inbox' == selectedPage ) {
				wpIssuesCRM.jumpToSubjectLine( this, 0 )
			} else if ( 'inbox' != selectedPage ) {
				doMessageViewer( this );
			}
		})

		.on( "click", "#inbox-master-checkbox", function ( event ) {  
				if ( $(event.target).prop("checked") ) {
					$( ".inbox-subject-line-checkbox" ).prop("checked", true );
					$( "#wic-load-inbox-inner-wrapper .inbox-subject-line" ).addClass ( "inbox-subject-line-selected" );  // must specify div! other boxes also have subject lines
				} else {
					$( ".inbox-subject-line-checkbox" ).prop("checked", false );;
					$( "#wic-load-inbox-inner-wrapper .inbox-subject-line" ).removeClass ( "inbox-subject-line-selected" );
				}							
		})

		.on( "click", "#wic-email-bulk-delete-button", function (){
			if ( !$( ".inbox-subject-line-selected" ).length ) {
				wpIssuesCRM.alert ( "No subject lines selected to archive.");
			} else {
				wpIssuesCRM.doDeleteBlockSelected (false)
			}
		})
		.on( "click", "#wic-email-bulk-block-button", confirmBlockSelected )

	
		// listen to inbox mode buttons  
		.on( "click", ".inbox-mode-button", function() {
			var buttonValue = $( this ).val(); // use this, not event.target to avoid chrome treating button html as target
			switch ( buttonValue ) {
				case 'resize':
					if ( $("#resize-inbox-button-content").hasClass ( "dashicons-no" ) ) {
						$("#resize-inbox-button-content").removeClass( "dashicons-no");
						$("#resize-inbox-button-content").addClass( "dashicons-editor-expand");
						$( "#wic-main-form-html-wrapper").removeClass( "showing-email");
					} else if ( $("#resize-inbox-button-content").hasClass ( "dashicons-editor-expand" ) ) {
						$("#resize-inbox-button-content").addClass( "dashicons-no");
						$("#resize-inbox-button-content").removeClass( "dashicons-editor-expand");
						$("#wic-main-form-html-wrapper").addClass( "showing-email");					}
					return;
				case 'sort':
					wpIssuesCRM.inboxAscending[selectedPage] = !wpIssuesCRM.inboxAscending[selectedPage];
					break;
				case 'group':
					inboxGrouped[selectedPage] = !inboxGrouped[selectedPage];
					break;
			}	
			if ( buttonValue != 'refresh' ) {
				nextPage[selectedPage] = 0;
			}
			wpIssuesCRM.loadSelectedPage(); 
		})

		.on( "click", "#wic-filter-assigned-button", function() {
			wpIssuesCRM.doFilterAssignmentPopup();
		})


		.on( "click", ".page-link", function() {
			if ( ! $( this ).hasClass( 'ui-state-disabled' ) ) {
				var increment = ( $( this ).val() == 'next' ) ? 1 : -1; 
				nextPage[selectedPage] = nextPage[selectedPage] + increment;
				wpIssuesCRM.loadSelectedPage()			
			}
		})


		// handle keystrokes in subject finder
		.on ( "keyup", "#subject", function ( event ) { 
			var testVal = $( this ).val();
			if ( testVal != latestSearch ) {
				wpIssuesCRM.clearTimer( acTimer );
				latestSearch = testVal;
				$( this ).addClass ( "flash" );
				nextPage[selectedPage] = 0;
				acTimer = setTimeout (  wpIssuesCRM.loadSelectedPage, 600  );
			}
		})

		// have to do this here for the sweep button, bind the other action buttons in the message view
		.on( "click", "#wic-inbox-sweep-button", function( event ) {
			if ( 'inbox' == selectedPage ) {
				wpIssuesCRM.processEmail( event ) 
			} else {
				wpIssuesCRM.alert( 'Sweeping only available from Inbox.' );
			}
		})

		// treat tab links as merely info source for click on tabs ( a gets ui link styling )
		.on( "click", "a.ui-tabs-anchor.inbox-tab-dummy-link", function( event )  {
			event.preventDefault();
		})
		
		.on ( "click", "li.wic-inbox-tab", function( event ) {
			var $selectedTab;
			// deal with clicks whether on link or outside link
			if ( $( event.target ).hasClass( "wic-inbox-tab-count") ) {
				$selectedTab = $( event.target ).parent().parent();
			} else if ( $( event.target ).hasClass( "inbox-tab-dummy-link") ) {
				$selectedTab = $( event.target ).parent();
			} else {
				$selectedTab = $( event.target );
			}
			// switch ui selected
			if ( !$selectedTab.hasClass ( "ui-tabs-active" ) ) {
				$( ".wic-inbox-tab" ).removeClass ( "ui-tabs-active" );
				$selectedTab.addClass ( "ui-tabs-active"  );
				// go to first page
				nextPage[selectedPage] = 0;
			}
			// set selectedTabID
			wpIssuesCRM.inboxSelectedTab = $selectedTab.attr("id")
			wpIssuesCRM.loadSelectedPage(); 

		})


		/*
		*
		* listeners for other areas, bound now since already loaded and so not repeated until reload
		*
		*/
		wpIssuesCRM.loadOutboxListeners();
		wpIssuesCRM.loadBlockListeners();
		wpIssuesCRM.loadSubjectListeners();
	}


	// popup to select assigned person to filter by
	wpIssuesCRM.doFilterAssignmentPopup = function() {
		var popupWidth = 480;
		var popupContent = '<div title="Filter Assigned/Ready messages"><div id = "staff_filter_assignment_popup">' + 
			$( "#hidden-staff-assignment-control" ).html() + 
			'<p>Selection of staff filters messages in the Assigned and Ready tabs, limiting them to messages assigned to the selected staff.</p>' +
			'<p><em>The messages assigned to other staff and filtered out of the Assigned and Ready tabs will appear in the tabs where they would appear if unassigned.</em></p>' +  
			'</div></div>'; 
		dialog 				= $.parseHTML( popupContent );
		wpIssuesCRM.filterAssignedPopupObject = $( dialog );
  		wpIssuesCRM.filterAssignedPopupObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				wpIssuesCRM.filterAssignedPopupObject.remove();	
  				wpIssuesCRM.filterAssignedPopupObject = false;
  				},
			width: popupWidth,
			height: 480,
			position: { my: "right top", at: "right top", of: "#wic-filter-assigned-button" }, 	
			show: { effect: "fadeIn", duration: 200 },
			buttons: [
				{
					width: 80,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.filterAssignedPopupObject.dialog( "close" ); 
					}
				},
				{
					width: 80,
					text: "Filter",
					autofocus: true,
					click: function() {
						selectedStaff = selectStaffButton.val();
						nextPage[selectedPage] = 0;
						wpIssuesCRM.loadSelectedPage(); 
						wpIssuesCRM.filterAssignedPopupObject.fadeOut( 300, 'swing', function(){ wpIssuesCRM.filterAssignedPopupObject.dialog( "close" );  })	

					}
				}
			],  			
  			modal: true,
  		});
  		selectStaffButton = $( "#staff_filter_assignment_popup #case_assigned" );
		wpIssuesCRM.setVal( selectStaffButton[0],selectedStaff, '' ); // empty string is ignored provided staff # exists

	};



}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
