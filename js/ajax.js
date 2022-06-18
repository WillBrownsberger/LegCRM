/*
*
*	ajax.js 
*
*	this module includes all the client to server communication routines (not all of which are ajax)
*
*/

// set this right away -- no need to wait for document ready
window.onpopstate = function(event) { 
	wpIssuesCRM.onPopState (event);
};


// listeners useful on most forms
jQuery( document ).ready( function($) { 

	/*
	* 	save current state if got here as a get (history.null) and have the top menu buttons to define state
	* 	-- 	avoids incorrect action on back button from a pushState navigation
	*		( as in view first screen on main via a get, then select and navigate by pushState )	
	* 	--  note that this is not enough to trick firefox into going back to a push state from a get 
	*		even though the gotten page now has a state, because navigation was a get, will return as to a get
	*			(with last form entries on the get before any pushState)
	*
	*	note that all the navigation possibilities for arrival to here are
	*		a push state  (in which case history is set and this doesn't activate)
	*		a get in main, where top button will be selected
	*		a straight get as in options/fields/storage, in which no button is set and no entity or action is set 
	*/
	if ( ! history.state ) {
		var topButton;
		if ( topButton = $( ".wic-form-button-selected")[0] ) {
			var controlArray = topButton.value.split(',') ;
			wpIssuesCRM.saveState ( controlArray[0], controlArray[1] );
		} else {
			wpIssuesCRM.saveState ( '', '' );
		}
	}

	/*
	* main button action 
	*   for top menu buttons (other than upload and help link, which have a different name)
	*	for most form buttons (standard name is wic_form_button )
	*/
	$( "#wp-issues-crm" ).on ( "click", "button[name=wic_form_button]", function ( event ) {
		wpIssuesCRM.screenButtonAction( this, event );
	})

	// pass button value through to top level form to pick up nonce
	// note that wic-post-export-button does this on selectmenu select
	.on ( "click", ".wic-download-button, .message-attachment-link-button", function ( event ) {
		wpIssuesCRM.doMainDownload( event )
	});

	
});

( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.onPopState = function( event ) {
		var valuesObject;
  		if ( event.state ) {

			wpIssuesCRM.closeDialogs(); // close any dialogs from form going back from
  			
			$ ( "#wic-main-form-html-wrapper" ).html( event.state.form );
		
			// if saved values with html for state, restore them 
			if ( event.state.values ) {
				valuesObject = event.state.values; 
				$ (  "#wic-main-form-html-wrapper :input" ).not( "button" ).each( function() {
					if ( "checkbox" == $(this).attr("type") || "radio" == $(this).attr("type") ) {
						$(this).attr("checked", valuesObject[$(this).attr("name")] );
					} else {
						$(this).val( valuesObject[$(this).attr("name")] );
					}
					$(this).val( valuesObject[$(this).attr("name")] );
				});
			}
		
			wpIssuesCRM.setButtonsColor ( event.state.entity, event.state.action )
			
			// initialize reloaded form 
			$ ( "#wp-issues-crm" ).trigger ( "initializeWICForm" );
		}
	} 

	// note: this function invoked directly on loadDashboardPanel in dashboard.js
	 wpIssuesCRM.saveState = function ( currentEntity, currentAction ) {
		var valuesObject = {};
		$ (  "#wic-main-form-html-wrapper :input" ).not( "button" ).each( function() {
			if ( "checkbox" == $(this).attr("type") || "radio" == $(this).attr("type") ) {
				valuesObject[$(this).attr("name")] = $(this).prop("checked");
			} else {
				valuesObject[$(this).attr("name")] = $(this).val();
			}
		});
		try { 
			var stateObject = { 	
					form: $ ( "#wic-main-form-html-wrapper" ).html(), 
					entity: currentEntity, 
					action: currentAction, 
					values: valuesObject
				}
			history.replaceState (
				stateObject,   
				'WP Issues CRM', 
				document.location 
			);
		} catch (err) {
			wpIssuesCRM.alert(
				'<h4>Browser Error</h4><p>A browser error occurred in the function history.replaceState</p>' +
				'<p>' + parseJSErr( err, JSON.stringify(stateObject) ) + '</p>'
			);
			console.log ('err in wpIssuesCRM.saveState', err, 'stateObject length was: ' + JSON.stringify(stateObject).length );
		}	
	}

	wpIssuesCRM.saveStateForNull = function ( button ) {

		if ( ! button ) {
			return (  false );
		}
		var controlArray = button.value.split(',') ;
		wpIssuesCRM.saveState ( controlArray[0], controlArray[1] );
	}

	wpIssuesCRM.screenButtonAction = function ( mainFormButton, event ) {
		// main button handler -- in all cases, cancel default
		event.preventDefault();
		// top menu button process is a new process, effectively a new page, although less loaded, so mimic unload
		if ( $( mainFormButton ).hasClass ( "wic-top-menu-button" ) ) {
			if ( wpIssuesCRM.formDirty ) {
				wpIssuesCRM.confirm ( // okFunction, cancelFunction, confirmMessage
					function() {
						wpIssuesCRM.formDirty = false;
						$( mainFormButton ).trigger ( "click" );
					},
					false,
					'Do you mean to click away?  Looks like you have unsaved work.' +
					'<p><em>Possibly a form you have not saved or an upload that is incomplete ' +
					'or an email processing request that has not run to completion.</em></p>'
				);
			} else {
				wpIssuesCRM.cancelAll();
				wpIssuesCRM.mainFormButtonPost( mainFormButton, event );
			}
		} else {
			wpIssuesCRM.mainFormButtonPost( mainFormButton, event );
		}
	}
	/*
	* synchronous form post, but minimum transmittal -- submit event default is prevented
	*
	*/
	wpIssuesCRM.mainFormButtonPost = function( mainFormButton, event ) { 
		
		// identify requested action
		var controlArray = mainFormButton.value.split(',') ;
		
		// set button going to
		wpIssuesCRM.setButtonsColor ( controlArray[0], controlArray[1] );
		
		// if doing a form search, add the values for the current form to the state
		if ( controlArray[1] == 'form_search' ) {
			wpIssuesCRM.saveState( history.state.entity, history.state.action );
		}
		
		// highlighting email form
		if ( controlArray[0] == 'email_inbox' ){
			$( "#wic-main-form-html-wrapper" ).addClass("showing-email")			
		} else {
			$( "#wic-main-form-html-wrapper" ).removeClass("showing-email")			
		}

		
		// grab the parent form and assemble the post data
		submittedForm =  $( mainFormButton ).parents( "form" )[0];
		formData = new FormData ( submittedForm );
		// add the flag that wp is looking for routing
		formData.append( 'action', 'wp_issues_crm_form' );
		// add in unchecked checkbox fields from the form
		$( submittedForm ).children( "[type=checkbox] ").not(":checked").each( function() { 
			formData.append ( $(this).attr("name"), 0 );
		});
		// make sure the button value is in the form
		formData.append( 'wic_form_button', mainFormButton.value )
		// show a progress bar
		wpIssuesCRM.doUpdateInProgressPopup();
		// post the form asynchronously -- treat as any other request, although user access blocked until return
		var xhr = $.ajax({
			url: wic_ajax_object.ajax_url, 
			type: 'POST',
			data: formData, 
			processData: false,
			contentType: false,
			success: function( rawresponse, status, xhr ) {
				/*
				* remove from the pending list
				*/
				removePendingXHR ( xhr )
				/*
				* close any dialog that was open when the form was requested (and the progress pop-up )
				*
				* policy note: no main form button submissions return to dialog -- not feasible since function replaces underlying form frame
				* 	-- dialogs submit via a dialog button; 
				*   -- example case of using this remove() is when dups dialog clicks to go switch to one of the dups
				*   -- this also closes the popup progress object
				*/
				wpIssuesCRM.closeDialogs();
				/*
				* try json parse
				*/
				try {	
					var response = JSON.parse ( rawresponse );
					// load response in all cases (form error responses with smaller html substitution not implemented)					
					if ( 'undefined' !== typeof response.state_data ) {
						$( "#wic-main-form-html-wrapper" ).html( response.state_data );
						// set new form to clean unless coming back with a changed flag set to '1', i.e., sent changed flag and form came back with errors.
						if ( ! ( '1' === $( "#is_changed" ).val() ) ) {
							wpIssuesCRM.formDirty = false;
						}
					}
				} catch ( err ) {
					wpIssuesCRM.alert 	( '<h4>The server returned an error:</h4>'  
									+	'<div id="error_from_ajax_synch_post" class="wic_error_popup">'
										+ rawresponse  
									+ '</div>'

					);				
				}
				/*
				* try to initialize form if state defined -- javascript error may surface at this stage	
				*/
				try {
					if ( 'undefined' !== typeof response ) {
						$( "#wp-issues-crm" ).trigger ( "initializeWICForm" );
					}
				} catch ( err ) {
					console.log ( err )
					wpIssuesCRM.alert 	( '<h4>Error on new form initialization:</h4>'  +
						'<div id="error_from_ajax_synch_post" class="wic_error_popup">' +
							(
							'string' == typeof err ? 
								( '<p>' + err + '</p>' ) :
								( 	
									'<p>' + err.message + '</p>' +
									'<p>' + err.name + '</p>' +
									'<p>View browser debugger console for additional error details.</p>'
								) 
							) +
						'</div>'
					);						
				}
				/*
				* try to save new state if defined
				*
				* WIC_Admin_Navigation returns blank response.state_action on form validation message
				*/
				try {
					if ( 'undefined' != typeof response && response.state_action ) {
						history[response.state_action]( 
							{ form: response.state_data, entity: controlArray[0], action: controlArray[1] },   'WP Issues CRM', response.state );
					}
				} catch( err ) { 
					console.log ('Save state error in wpIssuesCRM.mainFormButtonPost:', err, ' -- response.state_data.length was: ' + response.state_data.length );
					// had response.state_data if err at this stage
					var parsedErr = parseJSErr( err, response.state_data )
					if ( parsedErr ) {
						wpIssuesCRM.alert ( '<h4>Browser error:</h4><p>' + parsedErr + '</p>' );
					} else {
						wpIssuesCRM.alert ( '<h4>Browser error:</4><p>Unknown error in history state save, reported by ajax.js.</p><p>Check console log.</p>' + '<p>' + ( err.message ? err.message : '' ) + '</p>' );
					}
				}
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				handlePostError ( jqXHR, textStatus, errorThrown, 'mainFormButtonPost' )
			},
		});
		
		addPendingXHR ( xhr )
 		return ( xhr );

	}
	
	function parseJSErr( err, stateString ) {
		if (  'string' == typeof err.name && 'NS_ERROR_ILLEGAL_VALUE' == err.name && 'string' === typeof stateString && stateString.length > 300000 ) {
			return  ' <p>Likely that exceeded max page size for browser history caching. </p>' + 
				'<p>Attempted to save page of length ' + stateString.length + ' -- too long for your browser. Switch to Google Chrome for maximum capacity of 10M.</p>' + 
				'<p>If you are using Chrome and this error persists, please contact <a href="mailto:help@wp-issues-crm.com">help@wp-issues-crm.com</a></p>';
		} else if ( 'string' == typeof err && err.length > 0 ) {
			return err;
		} else {
			return false;
		}
	}
	
	
	wpIssuesCRM.doUpdateInProgressPopup = function() {

		// open dialog popup
		updateInProgressPopup = $.parseHTML ( 
			'<div id="communications-progress-dialog" title="Communicating . . .">' +
				'<div id="wic-communications-progress-bar"></div>' +
			'</div>'
		);
		wpIssuesCRM.updateInProgressPopupObject = $( updateInProgressPopup );
		wpIssuesCRM.updateInProgressPopupObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: false,
			close: function ( event, ui ) {
				wpIssuesCRM.updateInProgressPopupObject.remove();
			},
			dialogClass: "wic-communications-popup",
			position: { my: "top", at: "top+200", of: "#wp-issues-crm" }, 	
			width: 200,
			height: 33,
			modal: true 
		});
		$( ".ui-widget-overlay.ui-front" ).css ( "opacity", "0.1" );
		// initialize progress bar within dialog
		$( "#wic-communications-progress-bar").progressbar({
				value: false
		});
	}




	wpIssuesCRM.setButtonsColor = function ( entity, action ) {
		$ ( ".wic-top-menu-button" ).each ( function () {
			var controlArray = this.value.split(',') ;
			if ( controlArray[0] == entity ) {
				$ ( this ).addClass( 'wic-form-button-selected' );
			} else {
				$ ( this ).removeClass (  'wic-form-button-selected'  );
			}
		});
	}

	/*
	*
	* standardizing calls less than full form -- data is the object to be passed, returns an object
	* action is always wp_issues_crm -- posting to Wordpress admin ajax (localized in WIC_Admin_Setup)
	* sub_action is the action requested for WP Issues CRM
	*  
	* note must manipulate the return object within the callback function b/c AJAX post is a synchronous --
	*	this function (and so any function calling it) will return before completion of the call.
	*
	*/
	wpIssuesCRM.ajaxPost = function( entity, action, idRequested, data, callback ) { 
		var ajaxLoader;
		var postData = {
			action: 'wp_issues_crm', 
			wic_nonce: wic_ajax_object.wic_nonce,
			entity: entity,
			sub_action: action,
			id_requested: idRequested,
			wic_data: JSON.stringify( data )
		};

		// note: cloning an element with ID is bad form, but if clone by class, show two copies; also if have multiple requests pending only show 1
		// allow first to close spinner even while second pending; in our usage second is secondary (update learning table)
		if ( 0 == $("#post-form-message-box").children("#ajax-loader" ).length ) {
			ajaxLoader = $("#ajax-loader").clone().appendTo("#post-form-message-box").css("display", "inline");
		} 
		var xhr = $.ajax({
			type: 	"POST",
			url: 	wic_ajax_object.ajax_url, 
			data: 	postData, 
			success:function( response, textStatus, jqXHR ) {
				// then process response
				var responseError 	= false;
				var JSONParseOK		= false
				try {	
					var decodedResponse = JSON.parse ( response );
					JSONParseOK = true;
				} catch( err ) {
					responseError =  response;
				}
				if ( JSONParseOK ) {
					if ( decodedResponse.response_code ) {
						callback ( decodedResponse.output );
					} else {
						responseError = decodedResponse.output;
					}			
				}
				if ( responseError ) {
					console.log ( 'Server error response via wpIssuesCRM.ajaxPost: ', response );
					wpIssuesCRM.alert 	( '<h4>The server returned an error:</h4>'
											+	'<div id="error_from_ajax_asynch_post" class="wic_error_popup">'
												+ '<p>' + responseError + '</p>'
											+ '</div>'
										);
				}
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				handlePostError ( jqXHR, textStatus, errorThrown, 'ajaxPost' )
			},
			complete: function () { 
				removePendingXHR ( xhr );  // xhr is set one level above	
				ajaxLoader.remove();
				if ( wpIssuesCRM.updateInProgressPopupObject ) {
					wpIssuesCRM.updateInProgressPopupObject.remove();
				}
			}
		});
		
		// add request to pending list after issuing it (and also return it) 
		addPendingXHR ( xhr )
 		return ( xhr );

	}
	/*
	* standard severe error handling for post responses
	*/
	handlePostError = function( jqXHR, textStatus, errorThrown, postFunction ) {
		if ( wpIssuesCRM.windowUnloaded ) {
			console.log ( 'Pending request abandoned on window unload.' );
		} else if ( 'abort' === jqXHR.statusText ) {
			console.log ( 'Pending request aborted OK.' );
		} else {
			console.log ( 'ajax POST (in module ajax.js, function ' + postFunction + ') returned an error. jqXHR:', jqXHR, ' textStatus: ', textStatus, errorThrown ? ' -- errorThrown: ' + errorThrown : ' -- no server errorThrown' );
			// setTimeout avoids showing error on unload in case windowUnloaded value not set
			setTimeout ( 
				function() { 
					wpIssuesCRM.alert 	( '<h4>Please try refreshing page.  You may need to login again.</h4>'  
						+ '<div class="wic_error_popup">'
						+'<p>Likely that your session timed out.</p>'
						+ '<p>If this error recurs, contact support.</p>' 
						+ '</div>'
					)
				}, 
				2000
			);
		}	
	}




	/* 
	* track pending ajax requests, so can abort when interrupt
	*
	*/

	// array of request objects -- add when posted, remove when responded
	wpIssuesCRM.pendingPosts = [];
	
	// functions for management of request queue
	function addPendingXHR ( xhr ) {
		wpIssuesCRM.pendingPosts.push( xhr );
	}
	
	function removePendingXHR ( xhr )  {
		var index = wpIssuesCRM.pendingPosts.indexOf (xhr)
		wpIssuesCRM.pendingPosts.splice(index, 1); 
	}

	// note that the abort function only stops the client from listening for the response -- it does not end a started task on the server.
	wpIssuesCRM.abortPendingXHR = function() {
		var arrayLength = wpIssuesCRM.pendingPosts.length;
		// pass array and abort all pending
		if ( arrayLength > 0 ) {
			for (var i = arrayLength - 1 ; i > -1; i--) {
				wpIssuesCRM.pendingPosts[i].abort();
			} 
		} 
		// reset array
		wpIssuesCRM.pendingPosts = [];
	}
	
	// dialog boxes should be abandoned if form is abandoned
	wpIssuesCRM.closeDialogs = function () {
		$(":ui-dialog").not( wpIssuesCRM.uploaderDialogObject ).each(function(){
  			$(this).dialog ("close");
		})
	}

	// pass value of download buttons through hidden field in top level form to pick up nonce into $_POST
	wpIssuesCRM.doMainDownload = function ( event ){
		buttonVal = $( event.target ).val() ? $( event.target ).val() : $( event.target ).parent().val(); // chrome sees target as button html
		$( "#wic-export-parameters" ).val( buttonVal );
		wpIssuesCRM.doingDownload = true;
		event.preventDefault();
		$( "#wic-top-level-form" ).submit();
	};

	// variation of doMainDownload to support map events
	wpIssuesCRM.doMapDownload = function ( exportParameters ){
		$( "#wic-export-parameters" ).val( exportParameters );
		wpIssuesCRM.doingDownload = true;
		$( "#wic-top-level-form" ).submit();
	};


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	

