/*
*
* multi-email.js handles multi_email processing
*
* within a div list there is an autocomplete email input item and a set of completed addresse tiles (divs)
*  
* case breakdown as follows:
*    input gets focus initially
*    	user types into field 
*			transition keystrokes (Tab,;:<>[]\) are discarded if there is no input, including tab
*			if there is valid input, autocomplete will be initiated
*				if a selection is made, menu is closed and selection forms tile (selectEmailAddressee)
*					reset search parms to assure that don't recreate menu from late ajax returns
*						( this wasn't necessary in other autocomplete b/c if leave field, going to new field and ajax return would ignore old field because active class gone )
*				if hit transition key, same, but input forms tile(s) (useInputAddressee ) ()
*	permutations to manage/test (keep coupling as loose as possible) 
*		Destination/Search End: 
*			Selection/TransitionKey 
*			Action within Multi field: Click Other Tile/Delete/inMultinotInput
*			Action Outsside Multifield: OtherBlur x all previous in another multield 
*			menu-Closed/menu-Open x ajax-Pending/ajax-Back x (mouse or enter selection for selects)
*
*	NOTE: the sortable ui appears to do a preventDefault on the mousedown event on the list tiles -- the related blur does not get timely triggered
*	
*/
// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	// email validator derived from https://www.regular-expressions.info/tutorial.html
	var emailReg = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
	/*
	*  regex for name words followed by an email in tag brackets
	*
	* the following regex crashes on longer strings! /^ *((?:\w*[ ,]*)+)< *([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}) *> *$/;
	* https://regex101.com/r/aW7kA7/1 shows that it crashes only in javascript flavor of regex.
	* solution appears to be the following -- generous, but finite counts for the terms -- especially words in the capturing group, but more limits contribute to further efficiency
	*/
	var anglesReg =  /^ {0,5}((?:\w{1,50}[ ,]{0,5}){1,4})< {0,5}([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}) {0,5}> {0,5}$/;
	wpIssuesCRM.initializeMultiEmailcontrols = function ( envelopeAreaId ) { 
		//initialize sortables
		$( "#" + envelopeAreaId + " .multi-email-wrapper" ).sortable({
			helper: "clone",
			cursor: "move",
			connectWith: ".multi-email-wrapper",
			// activate gets executed on each wrapper upon activation of any of them; close open menus to avoid confusion after drag, but don't change focus
			activate: function( event, ui ) { 
				var mailInput = $(this).find( ".constituent-email.wic-autocomplete")[0];
				var derivedEvent = { target: mailInput }
				wpIssuesCRM.closeAutocompleteMenu( derivedEvent );
			}
		});
		// while window including the envelope area is in dom, these listeners remain active		
		$( "#" + envelopeAreaId )
			.on( 'wicAutocompleteSelect', ".constituent-email.wic-autocomplete", selectEmailAddressee ) 	// selection from menu
			.on( 'click', ".constituent-email-item .dashicons-no-alt", handleDeleteRequest ) 				// click tile delete x
			.on( 'click', ".constituent-email-item .constituent-email-item-text", selectEmailTileForEdit )	// click tile text
			.on( 'click', ".multi-email-wrapper", handleOffListClick )										// click empty space within multi-email-wrapper
			.on( 'keyup keydown', ".constituent-email.wic-autocomplete", preHandleKeys )					// handle non-email-character keystrokes
			.on( 'focusout', ".constituent-email.wic-autocomplete", cleanUp ) 								// focusout -- note that focusout event in autocomplete excludes the constituent-email fields
			.on( 'change', '#add_cc', addOrRemoveCC );														// check box
		$( "#" + envelopeAreaId ).find(  "input" ).prop( 'disabled', false );

	}

	
	wpIssuesCRM.deinitializeMultiEmailcontrols = function( envelopeAreaId ) {
		// reverse initialization
		if ( $( "#" + envelopeAreaId + " .multi-email-wrapper" ).sortable( "instance" ) ) {
			$( "#" + envelopeAreaId + " .multi-email-wrapper" ).sortable( "destroy" ) 
		};
		$( "#" + envelopeAreaId ).off();
		$( "#" + envelopeAreaId ).find(  ":checked" ).prop( 'checked', false );
		$( "#" + envelopeAreaId ).find(  "input" ).prop( 'disabled', true );
		// remove any input
		$( "#" + envelopeAreaId ).find( ".constituent-email-item" ).remove();
		
	}


	// use data from menu selection to create a new email tile (only invoked by wicAutocompleteSelect)
	// event is the wicAutocompleteSelect and the target is the input
	// input retains focus throughout
	function selectEmailAddressee( event, entityValue, entityType, entityEmailName, entityLatestEmailAddress ) {
		// reset the input field
		$(event.target).val('');
		// add the selected data
		entityValue = wpIssuesCRM.validateEmail ( entityLatestEmailAddress, false ) ? entityValue : -1;
		inputToNewTile ( event, entityValue, entityType, entityEmailName, entityLatestEmailAddress ) 
		// in case the input has been moved by selectEmailTileForEdit, move it back to the end
		moveInputToEnd( $(event.target) );
	}

	// use data in the input field to create a new email tile (parallels selectEmailAddressee in steps )
	function useInputAddressee ( event ) { 
		/*
		* first do steps that were taken care of for selectEmailAddressee by the autocomplete select action
		*/
		var $input = $( event.target )
		// cache the input value
		var inputVal = $input.val();
		/* 
		* close the autocomplete menu ( if open ), but do not unbind autocomplete listeners
		* 	( in parallel case of selectEmailAddressee, this is done by the act of selection
		*	 closeAutocompleteMenu also has effect of rejecting late ajax menu returns)
		*/
		wpIssuesCRM.closeAutocompleteMenu ( event )	
		// clear the input (first step of selectEmailAddressee )
		$input.val('');
		
		/*
		* now use the value ( second step of selectEmailAddressee )
		*/
		// set up working variables
		var entityValue, entityType = 'constituent_email', entityEmailName, entityLatestEmailAddress; 
		// test for angles notation and use if correct or if not, use the pieces
		var match = anglesReg.exec ( inputVal )
		if ( match ) {
			entityEmailName = match[1].trim();
			entityLatestEmailAddress = match[2].trim();
			entityValue = 0; // valid but not known email
			inputToNewTile ( event, entityValue, entityType, entityEmailName, entityLatestEmailAddress ); 
		} else {		
			splitInputVal = inputVal.split( /[,;:\\\[\]]+/ ); 
			splitInputVal.forEach(function (element) {
				element = element.replace( /[<>]/, '').trim();
				if ( element ) {
					entityEmailName = element;
					entityLatestEmailAddress = element
					entityValue = wpIssuesCRM.validateEmail ( element, false ) ? 0 : -1;
					inputToNewTile ( event, entityValue, entityType, entityEmailName, entityLatestEmailAddress );
				}
			});
		}
		
		//  ( third step of selectEmailAddressee )
		moveInputToEnd( $input ); 	
	}
	
	function inputToNewTile ( event, entityValue, entityType, entityEmailName, entityLatestEmailAddress ) {
		// create a new div tile with the information in it
		var tileClass = '';
		if ( -1 == entityValue ) {
			tileClass = 'bad-email-address'; 	
		} else if ( 0  < entityValue ) {
			tileClass = 'found-email-address' 
		}		
		var newEmailEntry = $.parseHTML ( 
			'<div class="constituent-email-item ' + tileClass + '" title="' + entityEmailName + '<' + entityLatestEmailAddress + '>">' +
				'<span class="dashicons dashicons-no-alt"></span>' +
				'<span class ="constituent-email-item-text">' + ( entityEmailName > '' ? entityEmailName : entityLatestEmailAddress ) + '</span>' +
			'</div>'
		);
		// tuck the necessary data into it
		$( newEmailEntry ).data( "emailParms", { id: entityValue, name: entityEmailName, address: entityLatestEmailAddress } )
		// insert it before the input field
		$( event.target ).parent().before( newEmailEntry );	
	}

	// treat non-email characters as indicating a new email address WHEN AND IF keyed (avoid problems in case name has these inadvertently)
	// do not handle possibility of validating these characters within quotations
	function  preHandleKeys( event ) {
		// first process for resizing of input field, 10 ACCOMMODATES CAPS
		$currInput = $(event.target);
		currVal = $currInput.val()
		idealWidth = Math.min( Math.max( currVal.length * 10, 200 ), $currInput.parent().parent().width() - 80 );
		$currInput.css( "width", idealWidth );		
	
		var falseShiftTransitionKeyCodes = [
			186, 	// semi-colon 
			188, 	// comma
			219,	// open bracket
			220,	// \ 
			221,	// close bracket
		]; 
		var trueShiftTransitionKeyCodes = [
			186, 	// colon
			188, 	// left angle bracket
			190,	// right angle bracket
		]; 		
	 	if (
	 		( -1 < trueShiftTransitionKeyCodes.indexOf( event.which ) && event.shiftKey ) ||
	 		( -1 < falseShiftTransitionKeyCodes.indexOf( event.which ) && ! event.shiftKey ) 
	 	) {
	 		// second level test for presence of character assures that will be used ( on key up ) if keyed into middle of field
			if (  /[,;:\\\[\]]+/.test( currVal ) || wpIssuesCRM.validateEmail( currVal, true) ) {
				useInputAddressee( event ); // same if keyed at end or edited into middle
			}	
		} else if ( ( 0 == $(event.target).next( ".wic-selectmenu-options-layer" ).length ) && 13 == event.which & 'keydown' == event.type ) {
			// only want this response if intended (tested presence of options menu for autocomplete )
			event.preventDefault();
			event.stopImmediatePropagation();
			useInputAddressee( event ); 
		}
	}

	// call only on blank input after closing by selection or when using address
	function moveInputToEnd( $input ) {
		var $list = $input.parent().parent();
		if ( ! $list.children().last().hasClass( "multi-email-input-item" ) ) {
			// directly remove autocomplete class and listeners ( do not use close routine to avoid loop; have already used contents )
			$input.trigger( "wicemail_blur" );
			// move the input the end 
			$input.parent().appendTo( $list )
			// if user did not seek a new field, no field now has focus (eliminates conflicting focus directions across multi-fields)
		} 
	}
	
	// only invoked on click of the text in the tile
	function selectEmailTileForEdit ( event ) {
		// set up values
		var $tile = $( event.target ).parent();
		emailParms = $.data( $tile[0], 'emailParms');
		// only show bracketed name if had both name and address seperately
		var display = emailParms.name == emailParms.address ?  emailParms.address : ( emailParms.name + ' <' + emailParms.address + '>' );
		var $list = $tile.parent()
		var $input = $list.find( 'input' ); 
		// want to clean up data, menu and listeners before moving
		closeInput( $input );
		// swap in the control tile, populate it with the address from the div tile and focus on it, rebinding autocomplete
		$tile.replaceWith ( $input.parent() ) // replaceWith may trigger focusout on the input (not triggered on mousedown because of sortable ), but repeat of closeInput is no harm
		// refind the new input field, install data and focus and spoof a space key to trigger lookup of email
		$list.find( 'input' ).val( display ).focus().trigger(  {
			which: 32,
			type: 'keyup'	
		} ); 
	}

	/*
	* save data in the input, do a delete and return focus to input
	* handle similarly to tile click for edit
	*/
	function handleDeleteRequest ( event ) { 
		var $tile = $( event.target ).parent();
		var $list = $tile.parent()
		var $input = $list.find( 'input' ); 
		// this should be triggered by focusout, but do it just in case 
		closeInput( $input );
		$tile.remove();
		$input.focus();
	}
	
	// return focus to input if click input area, not one of the tiles or input
	function handleOffListClick( event ) {
		if ( $( event.srcElement ).hasClass ( "multi-email-wrapper" ) ) {
			$input = $( event.target ).find ( 'input' );
			// this should be triggered by focusout, but do it just in case 
			closeInput( $input );			
			$input.focus();
		}
	}
	
	// pass jquery input object
	function closeInput( $input ) {
		// note that if moved position to last, will go around this loop, but only once b/c move happens before call to this and status is checked
		// want to execute this even on blank values b/c includes move to end of list
		var derivedEvent = { target: $input[0] }
		useInputAddressee ( derivedEvent );
		// close and unbind the menu (the plain blur event triggered on the input (secondary to the click) was disregarded by logic in autocomplete.js )
		$input.trigger( "wicemail_blur" ); 
	}
	
	wpIssuesCRM.validateEmail = function ( string, withName ) { 
		testReg = withName ? anglesReg : emailReg;
		return testReg.test(string);
	}

	// residual blur catch -- may duplicate closeInput on remove
	function cleanUp ( event ) {
		closeInput( $( event.target ) );
	}
	
	// expects array of arrays ( array[0] = name and array[1] = email_address ); target is input element to insert tile(s) behind
	wpIssuesCRM.address_array_to_email_tile = function( address_array, target ) {
		// test for undefined value to avoid error on undefined address_array passed when click on message not on folder
		if ( undefined == address_array ) {
			return;
		}
		// proceed
		address_array.forEach( function (element ) {
			var entityEmailName = element[0].trim(),
				entityLatestEmailAddress = element[1].trim(), 
				entityValue = element[2], 
				entityType = '',
				entityEmailNameClean = '';
			if ( entityEmailName ) {
				entityEmailNameClean = entityEmailName.replace( /[<>,;:\\\[\]]/, ' ').trim()
			} else {
				entityEmailNameClean = entityLatestEmailAddress;
			}
			inputToNewTile ( { target: target }, entityValue, entityType, entityEmailNameClean, entityLatestEmailAddress ) 
		});
	}
	
	// target is an input element within a multi-email field
	wpIssuesCRM.email_tile_to_address_array = function ( target ) {
		var newArray = [];
		$items = $( target ).parent().parent().children ( ".constituent-email-item");
		$items.each( function() {
			var emailParms =  $( this ).data( "emailParms" );
			newArray.push( [emailParms.name == emailParms.address ? '' : emailParms.name , emailParms.address, emailParms.id ] );
		});
		return newArray;
	}
	
	
	function addOrRemoveCC ( event ) {
		if ( $ ( event.target ).prop( "checked" ) ) {
			wpIssuesCRM.address_array_to_email_tile( wpIssuesCRM.allAddressees, $( event.target ).closest ( ".envelope-edit-wrapper" ).find( "#message_cc" )[0] )
		} else {
			var flatAddressList = [];
			wpIssuesCRM.allAddressees.forEach( function ( element )  {
				flatAddressList.push( element[1])
			});
			$( event.target ).closest ( ".envelope-edit-wrapper" ).find( "#message_cc" ).parent().parent().children(  ".constituent-email-item" ).each( function () {
				if ( -1 < flatAddressList.indexOf ( $( this ).data( "emailParms").address ) ) {
					$( this ).remove();
				}
			});		
		
		}
	
	} 
	
	
	
}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	