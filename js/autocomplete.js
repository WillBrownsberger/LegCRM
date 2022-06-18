/*
*
* autocomplete.js
*
* supports the WIC_Control_Autocomplete control class ( also triggered to support WIC_Control_Multi_Email )
*
* support simple autocomplete select
*
*/

jQuery( document ).ready( function($) { 
	/*
	*
	* on focus bind listeners;
	* 	on blur, unbind listeners
	* 
	* keystrokes, while focused, add or remove menu layer or replace contents
	*
	* on mouse enter layer, behave as a selectmenu (hover like add class)
	* 	but arrows can move in and out of menu (unlike selectmenu)
	* on click menu item: select, close menu and unbind (both also triggered by blur ); 
	*	on enter key menu item: select, just close menu, no unbind
	*/
	
	// on enter the display wrapper by click or tab to input, bind listeners 
	$( "#wp-issues-crm" ).on( "focus", ".wic-autocomplete", wpIssuesCRM.setUpAutocomplete )
	
});

// namespace
( function( wpIssuesCRM, $, undefined ) {	

	var latestSearch = false; 	// to discard old autocomplete requests
	var acTimer = false; 		// timer for autocomplete delay 
	var menuOffset = {};		// location to position menu
	var menuWidth  = false;		// css width for menu
	var menuMargin = false;		// css margin for menu
	
	// bind control event listeners to control wrapper (and set position variables)
	wpIssuesCRM.setUpAutocomplete = function ( event ) {
		// if shifting fields, reset timers
		wpIssuesCRM.resetAutoCompleteVars();
		// identify autocomplete field
		var $target = $( event.target );
		// mark control active
		if ( ! $(event.target).hasClass ("wic-autocomplete-active") ) { 
			$target.addClass( 'wic-autocomplete-active')
			// bind keyboard/blur listeners
			$( event.target ) 
				.on ( 	"blur", 	wpIssuesCRM.unbindAutocompleteMenu )
				.on (	"wicemail_blur", wpIssuesCRM.unbindAutocompleteMenu )
				.on ( 	"keydown",	handleSelectMenuKeyBoard )
				.on (  	"keyup",	handleSelectMenuKeyBoard )
		}
	}

	wpIssuesCRM.resetAutoCompleteVars = function() {
		latestSearch = false; 	// to discard old autocomplete requests
		acTimer = false; 		// timer for autocomplete delay 
	}


	//locate and size menu (position with respect to wrapper when handling constituent email)
	function setMenuParms( $input ) {
		// locate and size menu
		if ( $input.hasClass( "constituent-email") ) {
			menuOffset = $input.parent().parent().position(); // top/left vs offset parent
			menuOffset.top = $input.position().top + $( $input ).outerHeight();
			menuWidth  = $input.parent().parent().css( "width" ); // exclude margin
			menuMargin = $input.parent().parent().css( "margin-left" );			
		} else {
			menuOffset = $( $input ).position(); // top/left vs offset parent
			menuOffset.top = menuOffset.top + $( $input ).outerHeight();
			menuWidth  = $( $input ).css( "width" ); // exclude margin
			menuMargin = $( $input ).css( "margin-left" );		
		}
	}


	// unbind listeners and close menu ( event.target is always the input )
	wpIssuesCRM.unbindAutocompleteMenu = function ( event ) { 
		if ( $( event.target ).hasClass( "constituent-email") && event.type != "wicemail_blur" ) {
			return;
		}

		// unmark control
		$( event.target ).removeClass( "wic-autocomplete-active" );
		// unbind listeners
		$( event.target )
			.off ( 	"blur", 	wpIssuesCRM.unbindAutocompleteMenu )
			.off (	"wicemail_blur", wpIssuesCRM.unbindAutocompleteMenu )
			.off ( 	"keydown", 	handleSelectMenuKeyBoard )
			.off (  "keyup",	handleSelectMenuKeyBoard );
		// remove the menu
		wpIssuesCRM.closeAutocompleteMenu( event );
	}

	// event.target has to be the input control -- blur or keyboard
	wpIssuesCRM.closeAutocompleteMenu  = function  ( event ) { 
		wpIssuesCRM.resetAutoCompleteVars(); // this has effect of rejecting late returning ajax menu lists
		$( event.target ).next( ".wic-selectmenu-options-layer" ).remove();
		$( event.target ).removeClass ( "flash" );
	}

	// handle selection of item (by click or enter while selected)
	function selectItem ( event ) {

		// set up values
		var $clicked = $( event.target );
		var $clickedLabel = $clicked.text();
		var $clickedValue = $clicked.prev().text();
		var $clickedEntityType = $clicked.next().text();
		var $clickedEmailName = $clicked.next().next().text();
		var $clickedLatestEmailAddress = $clicked.next().next().next().text();
		var $input = $clicked.closest( ".wic-selectmenu-options-layer" ).prev();
		var derivedEvent = { target: $input[0] }

		// remove the menu layer -- selection is complete and we are oriented for the next actions ( $input identified )
		wpIssuesCRM.closeAutocompleteMenu ( derivedEvent );
		
		if ( -1 != $clickedValue ) {
			// if not the not found item use it
			$input.val( $clickedLabel )
			// also trigger the select event on the target with the data from the option list for email and search box
			.trigger( 'wicAutocompleteSelect', [$clickedValue, $clickedEntityType, $clickedEmailName, $clickedLatestEmailAddress] ) ;
		}
	}

	// pass through to allow first prevent default on menu mouse click -- can't put in main function b/c enter is a keyboard event
	function selectItemByMouse ( event ) {
		event.preventDefault();
		selectItem(event)
	}

	// handle keystrokes in display field
	function handleSelectMenuKeyBoard ( event ) {

		// get oriented
		var $typed 		= $( event.target );
		var testVal 	= $typed.val().toLowerCase(); 
		// get the drop down ul list (only child of the layer in autocomplete);
		var $list = $typed.next( ".wic-selectmenu-options-layer" ).children();

		// if no string, close menu and dismiss any pending search and done
		if ( ! testVal ) {
			if ( $list.length ) {
				$list.parent().remove(); // take out the whole menu layer
			}
			wpIssuesCRM.clearTimer( acTimer );
			return;
		} 

		// handle navigation keys
		if ( wpIssuesCRM.isNavigationKey ( event.which ) ) {
			// navigate only if something to navigate
			if ( $list.length ) {
				var $listElements = $list.children();
				var $selected	= $listElements.filter( ".wic-selected" );
				if ( 'keydown' == event.type ) { 
					switch ( event.which ) {
						case 9 : // tab will trigger blur
							break;
						case 27: // escape -- close menu, but not unbind listener
							wpIssuesCRM.closeAutocompleteMenu ( event )
							// do not allow event to propagate -- avoid double close
							event.stopPropagation();
							break;
						case 13: // enter
							if ( $selected.length ) {
								// don't to submit form (enter)
								event.preventDefault();
								event.stopImmediatePropagation(); // do not allow multi-email.js key handler to also respond
								// select selected
								selectItem( { target: $selected.find( ".wic-selectmenu-list-entry-label" )[0] } );
							}
							// if no element selected, just assume is intended as submit
							break;
						case 38: // up arrow ( allow move back to input -- never actually left )
							if ( $selected.length ) {
								$prev = $selected.prev();
								if ( $prev.length ) {
									$prev.addClass( "wic-selected" );
									wpIssuesCRM.scrollToShowSelected ( $list, $prev );
								}
								$selected.removeClass ("wic-selected");
							}
							break;
						case 40: // down arrow (allow select first on down arrow from input -- not actually leaving)
							if ( $selected.length ) {
								$next = $selected.next( ".wic-selectmenu-list-entry" );
								if ( $next.length ) {
									$next.addClass( "wic-selected" );
									$selected.removeClass ("wic-selected");
									wpIssuesCRM.scrollToShowSelected ( $list, $next );
								}
							} else {
								$listElements.first().addClass( "wic-selected" );
							}
							break;			
					}
				}
				return; // discarding keyup on the navigation keys
			}
		} else if ( wpIssuesCRM.isTypingKey ( event.which ) ) {
			// value always changes on key down, but not final until key up, better final than extra on autocomplete
			// also, don't want to double submit same search
			if ( 'keyup' == event.type && latestSearch != testVal ) {
				// also don't set a new search
				wpIssuesCRM.clearTimer( acTimer );
				latestSearch = testVal;
				$typed.addClass ( "flash" );
				// var delay (time for buffering key strokes)is longer for email lookups and  main search box which assemble more data
				var delay = 10; // set to low level as experiment in azure -- /s be no db queuing
				if ( $typed.hasClass ( "wic-main-search-box" ) ) {
					 delay = 20;
				} else if ( $typed.hasClass("constituent-email") ) {
					delay = 20;
				} 
				acTimer = setTimeout (  loadMenu, delay, $typed, testVal  );
			}			
		}	
	} 
	
	// do the search to create the list 
	function loadMenu ( $input, testVal ) {
		// parms
		var searchClass, searchMethod, searchType;
		if ( 'wic-main-search-box' == $input.attr("id") ) {
			searchClass 	= 'search_box';
			searchMethod	= 'search';
			searchType		= 'both';
		} else if ( $input.hasClass("constituent-email")) {
			searchClass		= 'search_box';
			searchMethod	= 'search';
			searchType		= 'constituent_email';
		} else {
			searchClass 	= 'autocomplete';
			searchMethod	= 'db_pass_through';
			searchType		= $input.attr( "id" );		
		}
		
		wpIssuesCRM.ajaxPost( searchClass, searchMethod, searchType, testVal, function( response ) {
			 // testing to make sure search is still the latest and element has not been unbound
			if ( testVal == latestSearch && $input.hasClass( "wic-autocomplete-active" ) ) {
				setMenuParms( $input );
				$input.removeClass ( "flash" );
				$currentMenu = $input.next( ".wic-selectmenu-options-layer" );
				if ( response.length ) {
					if ( $currentMenu.length ) {
						$currentMenu.replaceWith ( responseToMenu( response ) );
					} else {
						$input.after( responseToMenu( response ) );
					}
					$input.next() // safely refers to the options layer ( no explicit unbind since always remove )
						.on ( 	"mouseenter", 	".wic-selectmenu-list-entry-label", wpIssuesCRM.mouseEnterItem )
						.on ( 	"mouseleave", 	wpIssuesCRM.closeAutocompleteMenu ) // the whole menu, not within the wrapper elements
						.on ( 	"mousedown", 	".wic-selectmenu-list-entry-label", selectItemByMouse ) 
				} else {
					if ( $currentMenu.length ) {
						$currentMenu.remove();
					}
				}
			}
		})	
	}

	function responseToMenu ( response ){
		// response is array of objects { value: ... , label: ... }
		var menuHTML = '<div class="wic-selectmenu-options-layer"><ul class="wic-selectmenu-dropdown-values">';
		response.forEach( function ( element ) {
			if ( element.label ) {
				menuHTML +=
				( 
				'<li class="wic-selectmenu-list-entry">' +
					'<ul>' +
						'<li class="wic-selectmenu-list-entry-value">' + element.value + '</li>' +
						'<li class="wic-selectmenu-list-entry-label">' + element.label + '</li>' +
						'<li class="wic-selectmenu-list-entry-type">' + element.entity_type + '</li>' +
						'<li class="wic-selectmenu-list-entry-email-name">' + element.email_name + '</li>' +
						'<li class="wic-selectmenu-list-entry-latest-email-address">' + element.latest_email_address + '</li>' +

					'</ul>' +
				'</li>' 
				)
			} else {
				menuHTML += '<li> . . . </li>';
			}
		})
		menuHTML += ( '</ul></div>' );
		return $( $.parseHTML ( menuHTML ) ).offset( menuOffset ).css( { display: "block", width: menuWidth, 'margin-left': menuMargin } );	
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	