/*
*
*	main.js 
*	
*	handles formDirty processing ( activates on window.unload )
*		formDirty is initialized false as part of page load (below) 
*			gets reset to false on a new form load ( wrong to call form clean if new form is just an invalid message, but user has been warned )
*		formDirty gets set true on change   
*			in the page standalone functions
*				option		-- change and deletedWICRow on form
* 				dictionary 	-- change on form
*			in the main form cluster
*				issue form 			-- change
*				constituent form	-- change and deletedWICRow
*				upload sequence 	-- after file copied and upload parse form initialized 
*				user preferences 	-- change 
*			NOTE:  setChangedFlags is also invoked at most instances of setting dirty (but not in upload context); setChangedflags prepares form changed flags for server.
*	formDirty gets checked
*		Here on window.unload ( to new page )
*		on top button process ( to new form, mimic page unload )
*		on uploader top button press (to new forms via uploader sequence )
*
*	close of pending requests happens implicitly on any new page, but we need todo it explicitly through wpIssuesCRM.cancelAll 
*		on top button process ( to new form )
*		on uploader top button press (to new forms via uploader sequence )
*		( do it on acceptance of formDirty override, not with any separate confirm )
*
*
*   script also does section show hide for forms and misc top level listeners
*/


jQuery( document ).ready( function($) { 

	$(window).on ( 'beforeunload' , function() { 		
		if ( !wpIssuesCRM.formDirty ) {
	  		if ( wpIssuesCRM.doingDownload ) {
				wpIssuesCRM.doingDownload = false; 
	  		} else {
	  			wpIssuesCRM.windowUnloaded = true; // flag is used in ajax error handling to suppress spurious errors on unload
		 	}
		 	return;
		}
		// Prevent multiple prompts - seen on Chrome and IE (from AreYouSure plugin)
		if (navigator.userAgent.toLowerCase().match(/msie|chrome/)) {
		  if (window.wicHasPrompted) {
			return;
		  }
		  window.wicHasPrompted = true;
		  window.setTimeout(function() {window.wicHasPrompted = false;}, 900);
		}
		// if form is dirty, cannot interrogate user action, so won't set unloaded flag; 
		// have delay as backup to suppress error on new page; better to fail to suppress error than to suppress real error after user has returned to dirty form
		return ( 'Form may have unsaved data -- do you really wish to navigate away?' )
		;
	});

	// listeners useful on most forms
	$( "#wp-issues-crm" ).on( "click", ".field-group-show-hide-button", function ( event ) {
			var sectionID = $( this ).next().attr("id");
			wpIssuesCRM.togglePostFormSection ( sectionID );
	})

});

( function( wpIssuesCRM, $, undefined ) {
	// control unload control variables
	wpIssuesCRM.formDirty		= false; // to control exit from forms
	wpIssuesCRM.windowUnloaded 	= false; // to prevent false error events when ajax uncompleted on unload
	wpIssuesCRM.doingDownload	= false; // used to avoid setting unloaded when doing download submit (which triggers unwanted unload event)

	wpIssuesCRM.confirmDialog 	= false; // object to expose confirm form to modification
	
	// show/hide form sections
	wpIssuesCRM.togglePostFormSection = function( section ) { 
		var constituentFormSection = document.getElementById ( section );
		var display = constituentFormSection.style.display;
		if ('' == display) {
			display = window.getComputedStyle(constituentFormSection, null).getPropertyValue('display');
		}
		var toggleButton	= document.getElementById ( section + "-show-hide-legend" );
		if ( "block" == display ) {
			constituentFormSection.style.display = "none";
			toggleButton.innerHTML = '<span class="dashicons dashicons-arrow-down"></span>';
		} else {
			constituentFormSection.style.display = "block";
			toggleButton.innerHTML = '<span class="dashicons dashicons-arrow-up"></span>';
		}
	}

	/*
	*
	*	Cancel all forms of pending requests
	*		Note that new form requests are synchronous, so not included here
	*
	*/
	wpIssuesCRM.cancelAll = function() {
		if ( 2 == wpIssuesCRM.uploader.state ) {
			wpIssuesCRM.uploader.stop(); // uploader has own ajax controller
		}
		if ( wpIssuesCRM.pendingPosts.length > 0 ) {
			window.clearInterval ( wpIssuesCRM.parseProgressCheckInterval ); // task to check parse progress is set to timer
			wpIssuesCRM.abortPendingXHR(); // abort all component ajax requests		
		}
	}

	/*
	*
	* set changed flag in form and, if appropriate, set current multivalue row changed flag
	*
	*/
	wpIssuesCRM.setChangedFlags = function ( e ) {
		$( "#is_changed" ).val ( '1' ); // set main form is_changed variable -- note that the row values have ID beginning with the row identifier
		if ( e.type != "deletedWICRow" ) {
			$( e.target ).parents ( ".wic-multivalue-block" ).find ( "[id*=is_changed]" ).val( '1' ) // set current multivalue block row is_changed
		}
	}


	// to pass parameters to an cancel or OK function set them within an anonymous function when calling confirm
	wpIssuesCRM.confirm = function ( okFunction, cancelFunction, confirmMessage ) {

		confirmDialog = $.parseHTML (
			'<div class="wp-issues-crm-confirm-dialog" title = "Confirm">' +
				'<div class="wp-issues-crm-confirm-dialog-inner-html">' + confirmMessage + '</div>' +
			'</div>' );
		wpIssuesCRM.confirmDialog  = $ ( confirmDialog );	
		wpIssuesCRM.confirmDialog.dialog ( {
			appendTo: "#wp-issues-crm",
			close: function () { // execute the cancel function regardless of how the confirm dialog is closed 
					if ( cancelFunction ) {
						cancelFunction ();
					}
					$( confirmDialog ).remove();
				}, 
			position: { my: "center top", at: "center top+110", of:  "#wp-issues-crm" },
			width: 480,
			height: 480,
			show: { effect: "fadeIn", duration: 300 },
			buttons: [
				{
					width: 100,
					text: "OK",
					click: function() {
						if ( okFunction ) {
							okFunction ()
						}
						$( confirmDialog ).remove();
					}
				},
				{
					width: 100,
					text: "Cancel",
					click: function() {
						$( confirmDialog ).dialog( "close" );
					}
				}
			],
  			modal: true,
  		});
	}

	wpIssuesCRM.alert = function ( alertMessage ) {

		// if an alert dialog is showing, add the alert text to the pending message
		if ( $( ".wp-issues-crm-alert-dialog-inner-html").length > 0 ) {
			wpIssuesCRM.pendingAlerts += alertMessage;
			$( ".wp-issues-crm-alert-dialog-inner-html").html ( wpIssuesCRM.pendingAlerts ); 
		// otherwise start the alert string fresh and open a dialog box
		} else {
			wpIssuesCRM.pendingAlerts = alertMessage;
			alertDialog = $.parseHTML (
				'<div class="wp-issues-crm-alert-dialog" title = "Notice">' +
					'<div class="wp-issues-crm-alert-dialog-inner-html">' + alertMessage + '</div>' +
				'</div>' );
			$alertDialog = $ ( alertDialog );	
			$alertDialog.dialog ( {
				appendTo: "#wp-issues-crm",
				close: function () { 
						$alertDialog.remove();
					}, 
				position: { my: "center top", at: "center top+110", of:  "#wp-issues-crm" },
				width: 480,
				height: 480,
				show: { effect: "fadeIn", duration: 300 },
				buttons: [
					{
						width: 100,
						text: "OK",
						click: function() {
							$alertDialog.dialog( "close" );
						}
					},
				],
				modal: true,
			});
		}
	}

	// replace html of element with id with loader image ( set in admin-setup.php localize script )
	wpIssuesCRM.showLoader = function ( id ) {
		document.getElementById(id).innerHTML = wpIssuesCRMLoader;
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	

