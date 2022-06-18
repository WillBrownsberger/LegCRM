/*
*
*	activity.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 

	// set up listener for activity popup on add-new and form edit buttons
	$( "#wp-issues-crm" ).on( "click", "#add-new-activity-button, #wic-activity-area .dashicons-edit", function ( event ) {
		wpIssuesCRM.prepareToDoPopup( event )
	})

	// mouse in and out to manage the slide down of activities filter menu
	.on( "click", "#filter-activities-button", function ( event ) { 
		if ( ! $ ( "#filter-activities-menu" ).is( ":visible" ) ) {
			$( "#filter-activities-menu").slideDown( 300, 'swing' ) 
		} else {
			$( " #filter-activities-menu").slideUp() 
		}
	})

	// save and implement changes in the filter menu
	.on( "change",  "#filter-activities-menu", function ( event ) { 
		wpIssuesCRM.saveFilterValues();
		wpIssuesCRM.doTypeFilter();
		wpIssuesCRM.updateFilterButtonColor();
		event.stopPropagation()		
	})

	// button to reload activity with show alls
	.on( "click", "#show_all_activities", function( event ) {
		wpIssuesCRM.loadActivityArea ( false );
	})

		// set event listeners for links in list -- treat as if a form submit from top level ( dirty testing and state save )
	.on( "click" , ".activity_list_constituent_show_link, .activity_list_issue_show_link", function ( event ) {
		event.preventDefault();
		var theOtherEntity	 	= 'constituent' ==  wpIssuesCRM.parentFormEntity ? 'issue' : 'constituent'; 
		var idSelector 			= 'constituent' ==  theOtherEntity ? ".activity_list_constituent_id" : ".activity_list_issue"
		passThroughButton 		= $ ( "#wic_hidden_top_level_form_button" )
		passThroughButton.attr("name", "wic_form_button" );
		passThroughButton.val( theOtherEntity + ",id_search," + $( this ).parent().parent().children( idSelector ).text() );
		passThroughButton.trigger("click");
	})              

	// listener for reassignment button
	.on( "click", "#reassign_activities_button, #delete_activities_button", function() {
		wpIssuesCRM.doActivityActionPopup ( $(this).val() ); 
	})

	// set up to look like a click from a saved or sent activity record
	.on( "click", "#show_original_message", function ( ){
		wpIssuesCRM.viewMessage ( $(this).val(), $(this).parent()[0] );
	});


});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	/*
	* First block of function supports activity listing
	*
	* Second block supports the popup for activity add/update/delete
	*/

	/*
	*
	* Activity list load
	*
	*/

	wpIssuesCRM.parentFormEntity = false;

	/* invoked on initial load and on response from save/update of parent */
	wpIssuesCRM.loadActivityArea = function ( initialLoad ) { 
		/*
		* initialLoad is false on click of show_all_activities button -- passes through to server set_up_activity_area and sets no limit on activities
		* initialLoad is true on initialization of forms -- could be first visit or could be return after an update, in which case will use cache if not too much delay
		* note that this is the only function that gets data from the cache (exception: show/hide of list looks at checked filter values in cache)
		* 	further, this function ONLY invoked in two cases itemized above
		*
		* cache changing events are as follows
		*	initialized as false on page load (before document ready for main WP Issues CRM page load)
		*	the cache is updated 
		*		=	on constituent/issue form init (here, after set_up_activity_area), 
		*		=	on save/update of activity
		*		=	on delete of activity 
		*		=	. . . also on change filter values
		*		=	it is not updated on main form ID change (from null to something if saving), but this value is tested before using the cache on load and cache is reloaded
		*/
		var currentEntityID = getCurrentParentEntityID()
		// if have recent activity cache entry for this ID/entity, retrieve it and consider it fresh
		if ( wpIssuesCRM.activityAreaCache && 
			 wpIssuesCRM.activityAreaCache.ID == currentEntityID &&
			 wpIssuesCRM.activityAreaCache.parent == wpIssuesCRM.parentFormEntity && 
			 ( (new Date).getTime() - wpIssuesCRM.activityAreaCache.time ) < 30000 && // consider cache stale after 30 seconds (cache purpose is only to minimize unnecessary reloads).
		   	 initialLoad 
		   ) {
			wpIssuesCRM.retrieveActivityArea() // this is the only retrieval from cache (except use of filter checked values in show/hide )
			wpIssuesCRM.activityAreaCache.time = (new Date).getTime();	   
			wpIssuesCRM.initializeActivityArea();
		// otherwise get new from server, set it up and cache it
		} else {
			$( "#activity-area-ajax-loader").show(); 
			var parametersObject = {
				initialLoad: initialLoad,
				parentForm: wpIssuesCRM.parentFormEntity
			}
			wpIssuesCRM.ajaxPost( 'activity', 'set_up_activity_area',  currentEntityID, parametersObject,  function( response ) {
				$( "#activity-area-ajax-loader").hide(); 
				$( "#wic-activity-area").html( response.activityList );
				$( "#hidden-blank-activity-form" ).html( response.activityForm ); 
				// make hidden activity form into template only
				$( "#hidden-blank-activity-form :input").each( function() {
					$( this ).attr( "id", "template-" + $( this ).attr( "id" ) );
				})
				// inject a blank value into the activity_type filter control -- standard multivalue control excludes blank type; check all
				wpIssuesCRM.initializeActivitiesTypeMenu();
				// cache and initialize
				wpIssuesCRM.cacheActivityArea();
				wpIssuesCRM.initializeActivityArea();
			});
		}	
	}

	wpIssuesCRM.initializeActivitiesTypeMenu = function() {
		var newFirstEntry = $.parseHTML (
			'<p class="wic_multi_select_item">' +
				'<label class="wic-multi-select-label activity-type" for="activity_type[___]">Missing Activity Type </label>' +
				'<input id="activity_type[___]" class="wic-input-checked" type="checkbox" value="1" name="activity_type[___]">' +
			'</p>' );
		$( "#filter-activities-menu	.wic_multi_select" ).prepend( newFirstEntry );
		$( "#filter-activities-menu	.wic_multi_select .wic-input-checked" ).prop( "checked", true );		
		var newLastEntry = $.parseHTML (
			'<p class="wic_multi_select_item">' +
				'<label class="wic-multi-select-label activity-type" for="activity_type[override_show_all]"><em>Override: Show All</em></label>' +
				'<input id="activity_type[override_show_all]" class="wic-input-checked" type="checkbox" value="1" name="activity_type[override_show_all]">' +
			'</p>' );
		$( "#filter-activities-menu	.wic_multi_select" ).append( newLastEntry );
	}

	// initialize cache as false on page load; will not reference until document ready
	wpIssuesCRM.activityAreaCache = false;

	function getCurrentParentEntityID() {
		var currentFormIDSelector = '#wic-form-' + wpIssuesCRM.parentFormEntity + ' #ID';
		return $( currentFormIDSelector ).val();
	}
	
	wpIssuesCRM.isParentFormChanged = function() {
		var currentFormChangedSelector = '#wic-form-' + wpIssuesCRM.parentFormEntity + ' #is_changed';
		return '1' === $( currentFormChangedSelector ).val();
	}
	
	// function to copy visible list to cache -- invoked on load and on add/update activity
	wpIssuesCRM.cacheActivityArea = function() { 
		wpIssuesCRM.activityAreaCache = { 
			time: 	(new Date).getTime(),			
			ID: 	getCurrentParentEntityID(), 
			list: 	$( "#wic-activity-area").html(), 
			form: 	$( "#hidden-blank-activity-form").html(),
			parent:	wpIssuesCRM.parentFormEntity,
			checked: []
		}
		wpIssuesCRM.saveFilterValues(); // populates .checked -- updated when changed
	}

	// populates .checked in cache
	wpIssuesCRM.saveFilterValues =  function() {
		var filterValuesArray = []
		$( "#filter-activities-menu	.wic_multi_select .wic-input-checked:checked" ).each( function() {
			filterValuesArray.push ( $ (this).attr("id") );
		});
		wpIssuesCRM.activityAreaCache.checked = filterValuesArray;
	}
	
	// function to retrieve cache
	wpIssuesCRM.retrieveActivityArea = function() {
		$( "#wic-activity-area").html( wpIssuesCRM.activityAreaCache.list );
		$( "#hidden-blank-activity-form").html( wpIssuesCRM.activityAreaCache.form );
		$( "#filter-activities-menu	.wic_multi_select .wic-input-checked" ).each( function() {
			if ( wpIssuesCRM.activityAreaCache.checked.indexOf ( $ (this).attr("id") ) > -1 ) {
				$ (this).prop( "checked", true )
			}
		});
		wpIssuesCRM.updateFilterButtonColor();	// this is placed in the retrieve, not the initialize as does not need to happen on first page load	
	}

	// filter and set message if none showing -- done on init and change filter
	wpIssuesCRM.doTypeFilter = function() {
		var thisType, thisTypeID, thisTypePlug;
		var someShowing = false;
		var overrideShowAll =  wpIssuesCRM.activityAreaCache.checked.indexOf ( 'activity_type[override_show_all]' ) > -1
		formActivityList = $( "#wic-activity-area #wic_activity_list" ).children()
		formActivityList.each( function () {
			if ( overrideShowAll ) {
				$( this ).show();
				someShowing = true;			
			} else {
				thisType = $( this ).children(".activity_list_activity_type").text();
				thisTypePlug = '' == thisType ? '___' : thisType;
				thisTypeID = 'activity_type[' + thisTypePlug + ']';
				if ( wpIssuesCRM.activityAreaCache.checked.indexOf ( thisTypeID ) > -1 ) {
					$( this ).show();
					someShowing = true;
				} else {
					$( this ).hide();
				}
			}
		});
		
		noActivitiesMessage = $ ( "#wic_no_activities_message" );
		if ( someShowing ) {
			noActivitiesMessage.hide();
		} else {
			noActivitiesMessage.text ( formActivityList.length > 0 ? 'No activities meet filter criteria.' : 'No activities found.' );  
			noActivitiesMessage.show();
		}
	}

	// filter button is yellow if some values filtered -- updated on retrieve from cache and on change (not on load since right)
	wpIssuesCRM.updateFilterButtonColor = function () {
		if ( $( "#filter-activities-menu .wic_multi_select .wic-input-checked:not(:checked)" ).length > 0 ) {
			$( "#filter-activities-button .dashicons.dashicons-filter" ).css ( 'color', '#FFD700' );
		} else {
			$( "#filter-activities-button .dashicons.dashicons-filter" ).css ( 'color', '#999' );
		}
	}

	// run when initial load or retrieve from cache
	wpIssuesCRM.initializeActivityArea = function () {

		// prevent addition of activities until new parent entity saved (disable download, filter and migration buttons)
		if ( 0 < getCurrentParentEntityID() ) {
			$("#add-new-activity-button, #upload-document-button, #download-activities-form-button, #reassign_activities_button, #filter-activities-button").attr( "disabled", false );
			wpIssuesCRM.initializeDocumentUploader();
		} else {
			$("#add-new-activity-button, #upload-document-button, #download-activities-form-button, #reassign_activities_button, #filter-activities-button ").attr( "disabled", true );
		}

		wpIssuesCRM.doTypeFilter();  // show/hide activity rows and set message display

		// if triggered parent form save because there were pending changes, do popup form if main form came back clean ( reset regardless whether form came back clean);
		if ( wpIssuesCRM.popUpRequestPending ) {
			if ( ! wpIssuesCRM.isParentFormChanged() ) {
				wpIssuesCRM.doActivityPopup ( wpIssuesCRM.popUpRequestPending )
			} 
			wpIssuesCRM.popUpRequestPending = false;
		}
		
		// pass button value through to top level form to pick up nonce
		// note that wic-post-export-button does this on selectmenu select
		$ ( ".document-link-button" ).on( "click", function ( event ) {
			wpIssuesCRM.doMainDownload( event )
		});

	}

	/*
	* Activity form logic -- shows and supports popup
	*
	* Initialize form values with activity specific logic
	*
	* Do save/update/delete from popup
	*/

	// initialize pending flag for popup
	wpIssuesCRM.popUpRequestPending = false; 
	
	// do popup if underlying form is not changed, otherwise save it first
	wpIssuesCRM.prepareToDoPopup = function( event ) {
		if ( wpIssuesCRM.isParentFormChanged() ) { 
			wpIssuesCRM.popUpRequestPending = event;
			var parentFormMainButtonSelector = "#wic-form-" + wpIssuesCRM.parentFormEntity + " button[name=wic_form_button]:first" 
			/*
			* trap for the unwary -- there are two identical buttons in each form; if don't include :first selector, trigger clicks on both;
			* since the first click sweeps away the html containing the buttons, an ambiguous directive is issued as to the second trigger;
			* firefox dismisses it which is the right outcome; chrome reloads the whole page, disrupting operations 
			*/
			$( parentFormMainButtonSelector ).trigger ( "click" );
		} else {
			wpIssuesCRM.doActivityPopup( event)
		}	
	}

	wpIssuesCRM.doActivityPopup = function ( event ) {
		/* note on form dirty logic: form is dirty or clean in several ways, all combined in makePopupDirty  ( no need to makePopupClean b/c close on success )
		*  make form dirty on key down, even though not necessarily truly dirty, to clear error message
		*  set global wpIssuesCRM.formDirty to false when leave the form 
		*		-- before allowing popup are making sure it is false, so what is lost is only the activity form info
		*		-- losing activity form info without warning ( on x out of form ) is OK because user sees his own error
		*  do not test whether already marked dirty before doing dirty logic -- keep it simple and reset all (message might need to be reset even though form is already dirty)
		*/
		var sidebarInnerWidth = $( "#wic-form-sidebar-groups").width();
		
		activityPopup = $.parseHTML ( '<div id="activity-popup" class="' + wpIssuesCRM.parentFormEntity + '-activity-popup"  title="Add/Update Activity">' + $( "#hidden-blank-activity-form").html() + '</div>' );

		wpIssuesCRM.activityPopupObject = $( activityPopup );
		wpIssuesCRM.activityPopupObject.dialog({
  			appendTo: "#wp-issues-crm",			
  			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.formDirty = false; 
				wpIssuesCRM.activityPopupObject.remove(); // cleanup object
				},
			position: { my: "left top", at: "left top", of: "#wic-activity-area" }, 	
			width: sidebarInnerWidth,
			height: 600,
			buttons: [
				{
					width: 100,
					text: "Save",
					click: function() {
						wpIssuesCRM.saveUpdateActivity ( event );
					} 
				},
				{
					width: 100,
					text: "Close",
					click: function() {
						if ( wpIssuesCRM.formDirty ) {
							wpIssuesCRM.confirm ( function() { wpIssuesCRM.activityPopupObject.dialog( "close" ) }, false, 'Close without saving?' );
						} else {
							wpIssuesCRM.activityPopupObject.remove();
						}
					}
				},
				{
					width: 100,
					text: "Delete",
					click: function() {
						wpIssuesCRM.deleteActivity ( event );
					}
				}
			],
			modal: true,
		});
		$( ".ui-widget-overlay.ui-front" ).css ( "opacity", "0.4" );

		/* set up form fields */

		// remove template prefix from form field elements
		$( "#activity-popup #wic-form-activity :input").each( function() {
			$( this ).attr( "id", $( this ).attr( "id" ).substring( 9 ) );
		})
		
		// populate from line item if event is an edit link click
		eventTarget = $( event.target );
		if ( !eventTarget.is("button") && !eventTarget.parent().is("button") ) { // chrome will see click on span within button as a non-button
			// copy from li to form
			eventTarget.parent().children().each( function() {
				var possibleID = $(this).attr("class").substring ( 14 );
				var possibleElement = $( "#activity-popup #wic-form-activity #" + possibleID )
				if ( possibleElement.length > 0 ) {
					// use setVal function to handle all fields
					var specialLabel = '';
					if ( 'issue' == possibleElement.attr( "id" ) ) {
						specialLabel = eventTarget.parent().children( ".activity_list_issue_show" ).children( "a" ).text();
					} else if ( 'constituent_id' == possibleElement.attr( "id" ) ) {
						specialLabel = eventTarget.parent().children( ".activity_list_constituent_show" ).children( "a" ).text();
					} else if ( 'activity_type' == possibleElement.attr("id") ) {
						specialLabel = eventTarget.parent().children( ".activity_list_activity_type_show" ).text().trim(',');
					} else if ( 'last_updated_by' == possibleElement.attr("id") ) {
						specialLabel = eventTarget.parent().children( ".activity_list_last_updated_by_show" ).text().trim(',');
					}
					wpIssuesCRM.setVal( 
						possibleElement[0],
						$(this).text(), 
						specialLabel
					);
				}
			});
			// handle noneditable activity type
			var activityDate = eventTarget.parent().children( ".activity_list_activity_date" ).text();
			var reservedActivityType = 'wic_reserved_' == $( "#activity-popup #activity_type" ).val().substring( 0, 13 ); 
			if ( reservedActivityType ) {
				$( "#activity-popup #activity_type-selectmenu-display" ).attr( "disabled", true );
			} else {
				$( "#activity-popup #activity_type-selectmenu-display" ).attr( "disabled", false );
			}
			// show hide textarea or plain display based on reserved activity_type
			if ( reservedActivityType ) { 
				$( "#activity-popup  #frozen_activity_note_display" ).html( $( "#activity-popup #activity_note" ).val() ); 
				$( "#activity-popup #frozen_activity_note_display" ).css( "display", "inline-block");
				$( "#activity-popup #activity_note" ).css( "display", "none");
			} else {
				$( "#activity-popup #frozen_activity_note_display" ).css( "display", "none");
				$( "#activity-popup #activity_note" ).css( "display", "inline-block");
			}
		// if just adding, add constituent id or issue id's as save fields -- no need to do full field set val, since no display
		} else {
			if ( 'constituent' == wpIssuesCRM.parentFormEntity ) {
				$( "#activity-popup #constituent_id" ).val( $( "#wic-form-constituent #ID").val() );
			} else {
				$( "#activity-popup #issue" ).val( $( "#wic-form-issue #ID").val() );  
			}
		}

	
		// show hide amounts on activity type change
		$( "#activity-popup #activity_type" ).on( "change", function ( event ) {
			var changedBlock = $( "#activity-popup" )[0];
			if ( wpIssuesCRMSettings.financialCodesArray.length ){
				wpIssuesCRM.showHideFinancialActivityType();
			}
		})
		// also initialize
		wpIssuesCRM.showHideFinancialActivityType();

		// set up dirty form logic ( focus event choice is conservative with respect to changed detection )
		$( "#activity-popup #wic-form-activity :input" ).not( "button" ).on( "focus", function () { 
			wpIssuesCRM.makePopupDirty();
		} );

		$( "#activity-popup #activity_amount" ).on( "blur", function () {
			wpIssuesCRM.standardizeAmountDisplay ( this );
		} );

	}

	wpIssuesCRM.makePopupDirty = function() {
		// mark form as dirty and changed
		wpIssuesCRM.formDirty = true;
		$("#activity-popup #is_changed").val('1');
		// make form as unsaved and remove error/good-news coloring
		$( "#activity-popup" ).dialog('option', 'title', 'Activity add/update -- unsaved' );
		$( "#activity-popup" ).dialog('option', 'dialogClass', '' );
	}

	/*
	* function showHideFinancialActivityType
	*/ 
	wpIssuesCRM.showHideFinancialActivityType = function() {
		var activityType 	= $( "#activity-popup #activity_type").val()
		var isFinancial		= ( wpIssuesCRMSettings.financialCodesArray.indexOf( activityType ) > -1 );
		if ( ! isFinancial ) { 
			$( "#activity-popup #wic-control-activity-amount" ).hide();
		} else {
			$( "#activity-popup #wic-control-activity-amount" ).show();			
		}
	}


	wpIssuesCRM.standardizeAmountDisplay = function ( elem ) {
		elem.value = elem.value.replace('$','') // drop dollar signs (convenience for $users)
		if ( isNaN( elem.value ) ) { 
			wpIssuesCRM.alert ( "Non-numeric amount -- " + elem.value + " -- will be set to zero." );
			elem.value = "0.00";
		} else {
			elem.value = ( elem.value == '' ? '' : Number( elem.value ).toFixed(2) ) ; 
		} 
	}

	wpIssuesCRM.saveUpdateActivity = function ( event ) {
 		var formData = {};
 		$ ( "#activity-popup #wic-form-activity :input" ).each( function () { 
			formData[$(this).attr("id").replace( /-selectmenu-display/,'_label')] = $(this).val()  
 		});
 		
 		// ID passed as 0, because ignored -- gets issue, constituent_id and activity.ID from formData
 		wpIssuesCRM.ajaxPost( 'activity', 'popup_save_update', 0, formData,  function( response ) {
 			// on OK return, reset dirty, update activity list and close popUp form
 			if ( 'OK' === response.message_level ) { 
				wpIssuesCRM.formDirty = false; 
				// swap in new line item
				eventTarget = $( event.target );
				if (  !eventTarget.is("button") && !eventTarget.parent().is("button")  ) { 
					eventTarget.parent().html ( response.list_item.replace(/(<li>|<\/li>)/g, '') );
				// otherwise add
				} else { 
					$( "#activity-popup #ID" ).val( response.activity_id );
					$( "#wic_activity_list" ).prepend ( response.list_item );
					$( "#wic_no_activities_message" ).hide(); // hide not found message in case first
				}
				wpIssuesCRM.doTypeFilter(); 
				wpIssuesCRM.cacheActivityArea();
				wpIssuesCRM.activityPopupObject.dialog( "close" );
			// on error, show message
			} else {
	 			$( "#activity-popup" ).dialog('option', 'title', response.message);
				$( "#activity-popup" ).dialog('option', 'dialogClass', 'activity-error-alert' );
			}
		});
  	}
 
 	wpIssuesCRM.deleteActivity = function ( event ) {  ;
		deleteID = $("#activity-popup #ID").val()
		// only do a delete if editing a pre-existing activity -- otherwise, just close
 		if ( 0 < deleteID ) { 
 			// do a confirm before deleting
			wpIssuesCRM.confirm (
				function () {
					wpIssuesCRM.ajaxPost(  'activity', 'popup_delete', deleteID, '',  function( response ) {
						$( event.target ).parent().remove();
						wpIssuesCRM.doTypeFilter(); // update whether anything showing
						wpIssuesCRM.cacheActivityArea(); // update cache
						wpIssuesCRM.activityPopupObject.dialog( "close" ); // do within the call back to avoid visual bounce
					});				
				},
				false,
				'Immediately and permanently delete this activity record (but not the parent issue or constituent)?'
			)
  		} else {
			wpIssuesCRM.activityPopupObject.dialog( "close" );
  		}
 	}


	wpIssuesCRM.doActivityActionPopup = function( buttonValue ) {
		var searchType  = buttonValue.split(',')[0];
		var searchID = buttonValue.split(',')[1];
		var action = buttonValue.split(',')[2];
		var dialogID = "#" + action + "_activities_dialog";
		var activitiesActionObject = $( dialogID );
		var isReassignAction = 'reassign' == action;
		var actionSuccessful = false;
		var isAdvancedSearch  = location.href.includes ( 'entity=search_log');
		
  		activitiesActionObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				activitiesActionObject.dialog( "destroy" );
				if ( actionSuccessful && !isReassignAction && isAdvancedSearch ) {
					location.replace(location.href.replace('action=id_search','action=id_search_to_form'));
				} else {
					location.reload(true);
				}
  				},
			width: isReassignAction ? 750 : 480,
			height: 480,
			show: { effect: "fadeIn", duration: 300 },
			buttons: [
				{
					width: 100,
					id: 'activitiesActionButton',
					text: ( isReassignAction ? "Reassign" : "Delete" ),
					click: function() { 	
						var activityListObject = {
							issue			: ( isReassignAction ? $ ( "#reassign_activities_dialog .issue .wic-selectmenu-input" ).val() : 0 ),
							searchID		: searchID,
							searchType		: searchType,
							action			: action						
						};
						if ( isReassignAction && !activityListObject.issue  ) {
							wpIssuesCRM.alert ( 'Please select an issue.' )
						} else if ( 'CONFIRM' != $ ( dialogID + " #confirm_activities_action" ).val() ) {
							wpIssuesCRM.alert ( 'To ' + action + ', you must type "CONFIRM" in all caps.' );  
						} else {
							$( dialogID + " .action-ajax-loader" ).show();
							$( "#activitiesActionButton" ).remove();
							$( "#cancelActivitiesActionButton .ui-button-text").text( "Close" );
							wpIssuesCRM.ajaxPost( 'activity', 'reassign_delete_activities',  0, activityListObject,  function( response ) {
								activitiesActionObject.html( '<h4><strong>' + ( response.reassigned ? 'Successful' : 'Unsuccessful' ) + ':</strong></h4><p>' + response.message + '</p>' + 
								( response.reassigned ? ( '<p>Your browser will refresh to show' + ( isReassignAction ? ' updated search results ' : ' your original search ' ) + ' when you close this window.</p>' ) : '' ))
								if ( response.reassigned ) { 
									actionSuccessful = true;
								} else {
									$( "#cancelActivitiesActionButton .ui-button-text").text( "Cancel" );
								}
							});
						}
					}
				},
				{
					width: 100,
					id: 'cancelActivitiesActionButton',
					text: "Cancel",
					click: function() { 
						activitiesActionObject.dialog( "close" );
					}
				}
			],
  			modal: true,
  		});
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
