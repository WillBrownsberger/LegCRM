/*
*
* selectmenu.js
*
* supports the WIC_Control_Selectmenu control class
*
* creates a select like control that allows searching within the menu
*
* -- a cross between jquery UI selectmenu and jquery UI autocomplete
*
*/

jQuery( document ).ready( function($) { 
	/*
	*
	* the part of the control that is intended to take focus is .wic-selectmenu-input-display
	* but, while it has keyboard focus, .wic-selectmenu-options-layer is top-layer and responsive to mouse input
	*
	* need to keep focus status of display and show status of options layer in synch 
	* that means assuring consistency in 4 transitions
	*	(1) display gain focus by 
	*		click  -- on focus, show the menu
	*		or by tab enter -- on focus, show the menu
	*	(2) display lose focus by tab or keyboard out
	*		-- listen for blur
	*	(3) show options-layer 
	*		show options-layer only by focus -- no synch issue
	*	(4) hide options-layer by 
	*		leave -- on mouseleave, close (and then blur/tab)
	*		click/select -- after select, close (and then blur/tab)
	*
	* mouse position tracking (selectmenuMousePosition) supports desired read of mouseenter event (select menu item)
	*	while mouse is over the control, if open or close the options layer, this triggers enter/leave events for the layer
	*		in openSelectmenu, set mouse tracker to false so as not to trigger a false mouse enter which moves the selection
	*		in hideMenuLayer unbind event processed before close, so prevent false mouse leave event which would trigger repeat of the close process
	*	to preserve intended mouseenter actions, need to make tracker true immediately after open (after closed, not an issue)
	*		turn on mousetracker on the whole container, #wp-issues-crm -- mouse could be in or out of the control on open 
	*		turn it off on close menu
	*		theoretical possibility that mouse could be on boundary on open and trigger enter before trigger first move 
	*			-- user would get correct selected item by a further move	
	*
	* the wic-selected class identifies a list element as selected; maintained mostly for display
	*	only used for processing on handling of enter key
	*	
	*
	*/
	
	// on enter the display wrapper by click or tab to input, display the menu 
	$( "#wp-issues-crm" ).on( "focus", ".wic-selectmenu-input-display", wpIssuesCRM.openSelectmenu );
	
	// handle entry to the wrapper via the down arrow
	$( "#wp-issues-crm" ).on( "click", ".wic-selectmenu-input-wrapper .dashicons-arrow-down", function (event) { 
		$(event.target).prev().focus();
	})

});

// namespace
( function( wpIssuesCRM, $, undefined ) {	

	var selectmenuMousePosition = { pageX: false, pageY: false }; // to control selectmenu mouse behavior
	var latestSearch = false; 	// to discard old autocomplete requests
	var acTimer = false; 		// timer for autocomplete delay 
	
	
	// handles click on the wrapper ( or focus on the input )
	wpIssuesCRM.openSelectmenu = function ( event ) {

		var displayWrapper = $( event.target ).parent()[0];
		var $value = $( displayWrapper ).find( ".wic-selectmenu-input" );
		var $label = $( displayWrapper ).find( ".wic-selectmenu-input-display" );

		// save the starting value/label
		var currentValue = $value.val();
		var currentLabel = $label.val(); 
		$.data( displayWrapper, 'value', currentValue );
		$.data( displayWrapper, 'label', currentLabel );

		// wipeout the values -- need to wipeout the display for searching; wipe out the input for consistency
		$value.val( '' )
		$label.val( '' )
		// get the wrapper parent
		var $parent = $( displayWrapper ).closest( ".wic-selectmenu-wrapper" );
		// get the menu values
		var $menuValues = $parent.find( ".wic-selectmenu-list-entry-value" );
		// set up menu
		if ( ! isAutocomplete( $parent ) ) {
			// start the menu legend fresh
			$parent.find( ".wic-selectmenu-search-legend" ).text('');
			$parent.find( ".wic-selectmenu-search-legend").hide();
			// highlight the selected value, reset others
			$menuValues.each(function() {
				var $this = $(this)
				var $listItem = $this.parent().parent(); // this is an li ( -> ui -> li )
				$listItem.removeClass( 'filtered ' ); // in case hidden by prior search
				if ( $this.text() == currentValue  ) {			
					$listItem.css( "font-weight",  'bold' )
					$listItem.addClass( "wic-selected" );
				} else {
					$listItem.css( "font-weight", 'normal' )
					$listItem.removeClass( "wic-selected" ); // could be selected from previous control open
				}
			})
		// if autocomplete at start spoof a backspace keyboard event to display blank menu with search legend
		} else {
			keyboardEvent = {
				target: $parent.find( ".wic-selectmenu-input-display" )[0],
				which: 8,
				type: 'keydown'	
			}
			handleSelectMenuKeyBoard (keyboardEvent);
		}
		// set the mousetracker to false so ignore entry event caused by open of the new layer
		selectmenuMousePosition.pageX = false;

		// show the menu
		$parent.find( ".wic-selectmenu-options-layer" ).addClass( "active-selectmenu" );
		// adjust scroll position in case item is out of view
		var $list = $parent.find( "ul.wic-selectmenu-dropdown-values" );
		var $selected	= $list.find( ".wic-selectmenu-list-entry.wic-selected" );
		wpIssuesCRM.scrollToShowSelected ( $list, $selected ); // will do nothing when none selected
		
		/* 
		* note -- critical to use a 'mousedown' for next listener, not a click
		* present function is fired on a delegated 'focus' listener, which occurs after mousedown -- mousedown/focus/mouseup/click
		* 	-- cannot stop propogation of the other events (since the object object is the 'focus') -- 
		* in chrome, the mouseup and click do not fire after the options layers is raised BUT
		* 	in firefox they do, and the delegated listener below will hear the click and reclose the menu
		* the mousedown which opened this function has already bubbled, so safe to add this listener without stopping propogation
		*
		* purpose of listener is to close the menu when click out side it
		*
		*/

		// bind the mousetracker to container to reset it true upon first mouse twitch while menu showing
		$( "#wp-issues-crm" ).on( "mousemove", moveMouse );

		// for autocomplete move the options layer down to expose the input -- necessary in Chrome where list had entries 
		if ( isAutocomplete ( $parent ) ) {
			$parent.find( ".wic-selectmenu-options-layer" ).css( "top",  $label.outerHeight() );
		}		
		// bind control event listeners to control wrapper (present for every instance of this control type)
		$parent.on( "mouseleave", 	".wic-selectmenu-options-layer", 	mouseLeaveSelectmenu )
			.on( 	"mouseenter", 	".wic-selectmenu-list-entry-label", mouseEnterSelectmenuItem )
			.on( 	"mousedown", 	".wic-selectmenu-list-entry-label", selectSelectmenuItemOnClick ) 
			.on ( 	"keydown",		".wic-selectmenu-input-display", 	handleSelectMenuKeyBoard )
			.on ( 	"keyup",		".wic-selectmenu-input-display", 	handleSelectMenuKeyBoard )
			.on ( 	"blur", 		".wic-selectmenu-input-display", 	closeSelectMenuBlur );
	}

	// elem can be any element within the selectmenu wrapper
	function closeSelectmenu ( elem, $advanceTab ) { 
		// restore the starting values
		var $parent 	= $(elem).closest( ".wic-selectmenu-wrapper" );
		var $inputCouple	= $parent.find (  ".wic-selectmenu-input-wrapper" );
		$inputCouple.find( ".wic-selectmenu-input" ).val( $.data( $inputCouple[0], 'value' ) );
		$inputCouple.find( ".wic-selectmenu-input-display" ).val( $.data( $inputCouple[0], 'label' ) );
		// hide the menu
		hideMenuLayer ( $parent, $advanceTab );		
	}

	function moveMouse ( event ) {
		selectmenuMousePosition = { pageX: event.pageX, pageY: event.pageY }
	}

	// highlight items on true mouse entry 
	// test pageX to avoid enter event caused by show of menu layer (on click)
	function mouseEnterSelectmenuItem ( event ) { 
		if ( selectmenuMousePosition.pageX ) { 
			wpIssuesCRM.mouseEnterItem ( event )
		}	
	}
	
	// also used by autocomplete
	wpIssuesCRM.mouseEnterItem = function ( event ) {
		$( event.target ).closest( ".wic-selectmenu-dropdown-values" ).find(".wic-selectmenu-list-entry" ).removeClass( "wic-selected" );
		$( event.target ).closest( ".wic-selectmenu-list-entry").addClass( "wic-selected" );	
	}
	
	// close select menu on leave -- if didn't click an item (restore values in case did some typing while menu open)
	function mouseLeaveSelectmenu (event) {
		// no need to check tracker on close since unbind mouseleave on close but also don't want to trigger leave on filter
		if ( selectmenuMousePosition.pageX ) { 
			closeSelectmenu( event.target, 0 );
		}
	}

	// 
	function closeSelectMenuBlur ( event ) {
		closeSelectmenu ( event.target, 0 ); // close menu, do not advance tab
	}

	// pass through
	function selectSelectmenuItemOnClick ( event ) {
		selectSelectmenuItem( event.target );
	} 

	// handle selection of item (by click or enter while selected)
	function selectSelectmenuItem ( clicked ) { 
		var $clicked  	= $( clicked );
		var clickedLabel = $clicked.text();
		var clickedValue = $clicked.prev().text();
		// consider -1 values to be a false submission
		if ( -1 == clickedValue ) {
			closeSelectmenu( clicked, 0 );
			return;
		}
		// find the input
		var $parent		= $clicked.closest( ".wic-selectmenu-wrapper" );		
		var $inputCouple = $parent.find (  ".wic-selectmenu-input-wrapper" );
		// store the values for future use 
		$.data( $inputCouple[0], 'label', clickedLabel );		
		$.data( $inputCouple[0], 'value', clickedValue );
		// set the value of the input field and display
		$inputCouple.find( ".wic-selectmenu-input-display" ).val( clickedLabel );
		$inputCouple.find( ".wic-selectmenu-input" ).val( clickedValue );
		// trigger change event on the main value		
		$inputCouple.find( ".wic-selectmenu-input" ).trigger( "change" );
		// hide the menu
		hideMenuLayer ( $parent, 1 ); // 1 is tab forward
	}

	// $advance tab is -1 tab back, 0 do nothing, 1 tab forward
	function hideMenuLayer( $parent, $advanceTab ) {

		$( "#wp-issues-crm" ).off( "mousemove", moveMouse );

		// unbind control action listeners from control wrapper
		$parent.off( "mouseleave", 	".wic-selectmenu-options-layer", 	mouseLeaveSelectmenu )
			.off( 	"mouseenter", 	".wic-selectmenu-list-entry-label", mouseEnterSelectmenuItem )
			.off( 	"mousedown", 	".wic-selectmenu-list-entry-label", selectSelectmenuItemOnClick )
			.off( 	"keydown",		".wic-selectmenu-input-display", 	handleSelectMenuKeyBoard )
			.off( 	"keyup",		".wic-selectmenu-input-display", 	handleSelectMenuKeyBoard )
			.off ( 	"blur", 		".wic-selectmenu-input-display", 	closeSelectMenuBlur );

		// hide the menu layer
		$parent.find(".wic-selectmenu-options-layer").removeClass( "active-selectmenu" );

		// go to next visible or blur if not found
		var $inputElem = $parent.find( ".wic-selectmenu-input-display" );
		// if ! advance tab, just blur; also blur if fail on advance tab, also blur on close autocomplete -- force refocus to clear value
		if ( !$advanceTab || !wpIssuesCRM.goToNextVisibleInput( $inputElem[0], $advanceTab ) || isAutocomplete ( $parent ) ) {
			$inputElem.blur();
		}

	}

	// generic next input function
	wpIssuesCRM.goToNextVisibleInput = function( inputElem, order ) {
		/*
		* go to next item 
		*
		* unfortunately, have to go start from the top looking for inputs 
		* current element may not be within a form -- no other approach safe
		*/
		var $inputs = $( inputElem ).closest ( "#wp-issues-crm" ).find( ":input" );
		if ( order < 0 ) {
			$inputs = $( $inputs.get().reverse() );
		}
		var countInputs = $inputs.length;
		// loop through inputs until find current, then look for visible
		var foundElement = false;
		var foundNext = false;
		var i = 0;
		for ( i = 0; i < countInputs; i++ ) {
			if ( foundElement ) {
				var $currentInput = $( $inputs[i] );
				var tabIndexInput = $currentInput.attr( "tabindex" ) ;
				if 	( 
					$currentInput.is( ":visible" ) && 
					( tabIndexInput > -1 || ! tabIndexInput ) 
					)
					{
					$currentInput.focus();
					foundNext = true;
					break;
				}
			}
			if ( $inputs[i] == inputElem ) {
				foundElement = true;
			}
		} 	
		return foundNext;

	}

	// handle keystrokes in display field
	function handleSelectMenuKeyBoard ( event ) {
		// get oriented
		var $typed 		= $( event.target );
		var testVal 	= $typed.val().toLowerCase(); 
		var $parent 	= $typed.closest( ".wic-selectmenu-wrapper" ); 
		var $list 		= $parent.find( "ul.wic-selectmenu-dropdown-values" );
		var $listElements = $list.children();
		var $selected	= $listElements.filter( ".wic-selected" );
		var $listLegend	= $parent.find( ".wic-selectmenu-search-legend" );
		var listLegendText; 
		// handle navigation keys
		if ( wpIssuesCRM.isNavigationKey ( event.which ) ) {
			if ( 'keydown' == event.type ) { 
				switch ( event.which ) {
					case 9 : // tab
						closeSelectmenu ( event.target, event.shiftKey ? -1 : 1 ); // true is advance tab
						event.preventDefault(); // will advance focus to next visible tabbable
						break;
					case 27: // escape
						event.stopPropagation();
						closeSelectmenu ( event.target, 0 );
						break;
					case 13: // enter
						selectSelectmenuItem( $selected.find( ".wic-selectmenu-list-entry-label" )[0] );
						// prevent submit action
						event.preventDefault();
						break;
					case 38: // up arrow
						$prev = $selected.prev().not( ".filtered" );
						if ( $prev.length ) {
							$prev.addClass( "wic-selected" );
							$selected.removeClass ("wic-selected");
							wpIssuesCRM.scrollToShowSelected ( $list, $prev );
						}
						break;
					case 40: // down arrow
						$next = $selected.next().not( ".filtered" );
						if ( $next.length ) {
							$next.addClass( "wic-selected" );
							$selected.removeClass ("wic-selected");
							wpIssuesCRM.scrollToShowSelected ( $list, $next );
						}
						break;			
				}
			}
			return; // discarding keyup on the navigation keys
		} else if ( wpIssuesCRM.isTypingKey ( event.which ) ) {
			if ( ! isAutocomplete ( $parent ) ) {
				// handle input keys: apply value as filter on label
				var countOptionsShowing  = 0;
				$listElements.each( function() { 
					if ( 0 > $(this).find( ".wic-selectmenu-list-entry-label" ).text().toLowerCase().indexOf( testVal ) ) {
						$(this).addClass( 'filtered' );
					} else {
						countOptionsShowing++;
						$(this).removeClass( 'filtered' );
					}
				});
				// determine the legend text
				// display keystrokes
				if ( 0 == countOptionsShowing ) {
					if ( testVal ) {
						listLegendText = '"' + testVal +'" not found.  Backspace to clear.';
					} else {
						listLegendText = 'No options set up for this field.';
					}
				} else {
					if ( testVal ) {
						listLegendText =  'Searched for "' + testVal + '".' ;
					} else {
						listLegendText = '';
					}
				}				
				updateMenu ( $list, $listLegend, listLegendText );
			} else { 
				// generate menu elements (filtered by autocomplete)
				if ( testVal.length < 1 ) {
					$list.children().remove();
					updateMenu ( $list, $listLegend, '' );
					wpIssuesCRM.clearTimer( acTimer );
				} else {
					// value always changes on key down, but not final until key up, better final than extra on autocomplete
					// also, don't want to double submit same search
					if ( 'keyup' == event.type && latestSearch != testVal ) {
						// also don't set a new search
						wpIssuesCRM.clearTimer( acTimer );
						latestSearch = testVal;
						// update menu but delay actual start of search
						updateMenu ( $list, $listLegend, '' );
						$typed.addClass ( "flash" );
						acTimer = setTimeout (  loadMenu, 600, isAutocomplete ( $parent ), testVal, $list, $listLegend  );
					}
				}			
			}
		}	
	} 

	/*
	* public keyboard interpretation functions ( also used in autocomplete )
	*
	* event.which returns javascript  key codes
	* https://www.cambiaresearch.com/articles/15/javascript-char-codes-key-codes
	* https://api.jquery.com/event.which/
	* 
	*/


	wpIssuesCRM.isNavigationKey = function ( which ) {
		return  $.inArray( which, [9,27,13,33,34,38,40] ) > -1 ;
	}

	wpIssuesCRM.isTypingKey = function ( which ) {
		return  ( $.inArray( which, [8,32] ) > -1 ) || 	// backspace, space bar
				( which > 45 && which < 91 ) || 	// main keyboard characters
				( which > 95 && which < 106 ) || 	// number pad
				( which > 185 && which < 193 ) ;
	}

	function loadMenu ( $source, testVal, $list, $listLegend ) {

		// do the autocomplete to create the list 
		wpIssuesCRM.ajaxPost( 'search_box', 'search', $source, testVal, function( response ) {
			if ( testVal == latestSearch ) {
				$list.parent().prev().children( '.wic-selectmenu-input-display' ).removeClass ( "flash" );
				// response is array of objects { value: ... , label: ... }
				$list.children().remove();
				if ( response.length ) {
					response.forEach( function ( element ) {
						$list.append( objectToMenuElement ( element ) );
					})
				} 
				updateMenu ( $list, $listLegend, '' );
			}
		})	
	}

	wpIssuesCRM.clearTimer = function ( timer ) {
		if ( timer ) {
			clearTimeout ( timer ); 
		}					
	}

	function updateMenu ( $list, $listLegend, listLegendText ) {
		if ( listLegendText > '' ) {
			$listLegend.show();
			$listLegend.text( listLegendText );
			// set the mousetracker to false to ignore possible leave event due to menu shrinking
			selectmenuMousePosition.pageX = false;
		} else {
			$listLegend.hide();
		}
		// after filter, select first element (no effect if list is empty)
		$listElements = $list.children();
		if ( $listElements.length > 0 ) {
			$listElements.removeClass( "wic-selected" );
			$selected = $list.find( ".wic-selectmenu-list-entry" ).not( ".filtered" ).first()
			$selected.addClass( "wic-selected" );
			wpIssuesCRM.scrollToShowSelected ( $list, $selected );
		}	
	}

	// $l = $(list); s = $(selecteditem)
	wpIssuesCRM.scrollToShowSelected  = function ( $l, $s )  {
		// do nothing on bad call ( may be autocomplete blank )
		if ( ! $s.length || ! $l.length ) {
			return;
		}	
		// set the mousetracker to false so ignore entry event caused by the scroll
		selectmenuMousePosition.pageX = false;	
		// set up vars and scroll		
		var sTop 	= $s.offset().top;
		var sHeight = $s.height();
		var lTop		= $l.offset().top;
		var lHeight		= $l.height();
		var $lScrollTop	= $l.scrollTop()
		if ( ( sTop + sHeight ) > ( lTop + lHeight	) ) { // scroll down on down mov
			$l.scrollTop( $lScrollTop + ( sTop + sHeight  - ( lTop + lHeight ) ) );
		} else if ( sTop < lTop )  { // scroll up on up move
			$l.scrollTop( $lScrollTop + ( sTop - lTop ) );
		}	
	}

	function  isAutocomplete( $parent ) {
		var source = $parent.find( ".wic-selectmenu-additional-values-source" ).text()
		return source > '' ? source : false;
	}
	
	// set val for Selectmenu control -- elem should be the hidden workingcontrol -- .wic-selectmenu-input )
	// can also handle other controls that only need standard jQuery val instantiation
	// newLabel is ignored for existing values
	wpIssuesCRM.setVal = function ( elem, newVal, newLabel ) { 
		var $input 	= $( elem );
		// set the input field value
		$input.val( newVal );
		// check the input field type -- if not a selectmenu, done
		if ( !$input.hasClass( "wic-selectmenu-input" ) ) {
			return true;
		}
		// check if value appears in the option set
		var $parent = $input.closest(".wic-selectmenu-wrapper");
		var valFound = false;
		$parent.find( ".wic-selectmenu-list-entry-value" ).each( function() {
			if ( $(this).text() == newVal ) {
				valFound = true;
				// found, plan to use the existing option string for the new label
				newLabel = $(this).next().text();
			}
		});
		// if not found, add it
		if ( ! valFound ) {
			// create new element
			newElem = objectToMenuElement ( { value: newVal, label: newLabel } );
			// insert it in alphabetical order ignoring first element
			var bypassedFirst = false;
			var insertedItem = false;
			var lowerLabel = newLabel.toLowerCase();
			$parent.find( ".wic-selectmenu-list-entry-label" ).each( function () {
				$this = $( this );
				if ( ! bypassedFirst ) { 
					bypassedFirst = true;
				} else if ( $this.text().toLowerCase() > lowerLabel ) { 
					$this.parent().parent().before( newElem );
					insertedItem = true;
					return false; // break
				}			
			});
			
			if ( ! insertedItem ) { 
				$parent.find( ".wic-selectmenu-dropdown-values" ).append( newElem );
			}
		}
		// set the display value (from existing option if found or from as called newLabel; fill in also for display in readonly
		$parent.find( ".wic-selectmenu-input-display, .wic-selectmenu-input-display-readonly" ).val(newLabel);		
	}

	function objectToMenuElement ( object ){
		return $.parseHTML ( 
			'<li class="wic-selectmenu-list-entry">' +
				'<ul>' +
					'<li class="wic-selectmenu-list-entry-value">' + object.value + '</li>' +
					'<li class="wic-selectmenu-list-entry-label">' + object.label + '</li>' +
				'</ul>' +
			'</li>'
		);	
	}

	// swap in option set where elem is the .wic-selectmenu-input component and optionsHTML is string of output from WIC_Control_Selectmenu::format_list_entry 
	wpIssuesCRM.setOptions = function ( elem, optionsHTML ) {
		$elem = $( elem );
		// traverse to the list
		$dropdownList = $elem.closest( ".wic-selectmenu-wrapper" ).find( ".wic-selectmenu-dropdown-values" );
		// insert the options
		$dropdownList.html( optionsHTML );
		// take the first entry value
		$firstEntryVal = $dropdownList.find( ".wic-selectmenu-list-entry-value" ).first();
		// populate the input fields
		$elem.val( $firstEntryVal.text() )
		$elem.next().val( $firstEntryVal.next().text() );
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	