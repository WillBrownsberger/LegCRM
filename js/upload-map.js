/*
*
* upload-map.js
*
*/

jQuery(document).ready(function($) { 
	
	$( "#wp-issues-crm" ).on ( "initializeWICForm initializeWICSubForm", function () {
		if ( $ ( "#wic-form-upload-map" )[0]  ) { 
			wpIssuesCRM.initializeMapping() 
		}
	});

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	var wicColumnMap; // master object synched to screen and database
	var wicSaveMapMessage; // save slot for the welcome message so it can be restored easily after save information shown
	var initialUploadStatus; // loaded to form at outset

	wpIssuesCRM.initializeMapping = function()  {

		var mapType = false;
		if ( $( "#wic-form-upload-map" ).length > 0 ) {
			mapType = 'upload';
		} else if ( $( "#field-map-popup" ).length > 0 ) {
			mapType = 'form';
		}

		// do form initializations for upload mapping
		if ( 'upload' == mapType ) {
			// save initial message with file identifier for later restoration
			wicSaveMapMessage = $ ( "#post-form-message-box" ).text();
	
			initialUploadStatus = $( "#initial-upload-status" ).text();
		}

		// make field labels draggable (same in both uses of the function)
		$( ".wic-draggable" ).draggable({
			disabled: true, 					// do not enable until columns loaded by AJAX;
			revert: "invalid", 					// revert to start column if not dropped; change to false on dropped, so no revert back to droppable 
			stack: ".wic-draggable", 			// keep the moving item on top
			start: function ( event, ui ) { 	// restore original message -- drop any "saved" notation from previous drags
				if ( 'upload' == mapType ) { $ ( "#post-form-message-box" ).text( wicSaveMapMessage ) }; 
				$( this ).addClass ( 'moving-draggable' );	
			},
			stop: function ( event, ui ) {
				// no longer using tooltips, so no tooltip to close -- error fixed in 3.5.
				// effect revert to starting column for dropped items that are undropped
				// if not dropped ( i.e., in an invalid position ) and also net set to revert anyway, 
				// animate it slowly back to top of column of pending items 
				// note that revert is true only when starting from initial position in column of pending items			
				if ( ! $( this ).hasClass ( 'wic-draggable-dropped') && false === $( this ).draggable( "option", "revert" ) ) {
					// note where it is and where it is going
					var draggableOffset = ui.offset;	
					var destinationOffset  = $( "#wic-draggable-column" ).offset() ;
					// attach it back to column, where it is going (mirrors actions 4 through 6 of drop event )
					$( this ).detach();
					$( this ).prependTo( "#wic-draggable-column" );
					// but smooth the perceived motion -- keep the actual offset the same and then animate back into place  
					$( this ).css( "top" , draggableOffset.top - destinationOffset.top );
					$( this ).css( "left" , draggableOffset.left - destinationOffset.left );
					$( this ).animate ( {
						top: 0,
						left: 0
					});
				} 		
				$( this ).removeClass ( 'moving-draggable' );
			}
		});
		
		// set up target fields as droppable
		$( ".wic-droppable" ).droppable({
			activeClass: 	"wic-droppable-highlight",
			hoverClass: 	"wic-droppable-hover",
			accept:			".wic-draggable",
			tolerance: 		"pointer",
			drop: function( event, ui ) {
				var dropped = ui.draggable;
				draggableID = dropped.attr( "id" );
				droppableID = $( this ).attr( "id" );
				// function effects 6 additional changes to the draggable and 2 to the droppable 
				wpIssuesCRM.dropEventDetails ( draggableID, droppableID ); 
				// update array
				updateMap ( mapType, wicParseIdentifier( draggableID ),  wicParseIdentifier ( droppableID ) );
			},
			out: function( event, ui ) {
				var movingOut = ui.draggable;
				marker = $( this ).attr( "id" );
				// if moving out of droppable that have been dropped into, reverse actions taken by function dropEventDetails	
				if ( movingOut.hasClass ( marker ) ) {
					// (1) remove marker from draggable
					movingOut.removeClass ( marker )
					// (2) remove dropped state indicator from draggable
					movingOut.removeClass ( "wic-draggable-dropped" );
					// no need to repeat drop action (3) -- revert stays false; 
					// don't know where we are headed -- reverse drop actions (4)-(6) when dropped or if not dropped, when stopped
					$( this )
						// show droppable as open
						.removeClass ( 'wic-state-dropped ')
						// accept any draggable
						.droppable ( "option", "accept", ".wic-draggable" ); 
					// update the array to show unassigned
					updateMap ( mapType, wicParseIdentifier( movingOut.attr( "id" ) ),  '' );		
				} // else do nothing -- just passing over
			} 
		});
		
		// loadMap
		loadMap( mapType );
		
	}
	
	/*
	*
	* keep column_map object synchronized to both  database and screen
	*
	*/
	
	function updateMap ( mapType, dragObject, dropObject ) {
		if ( 'upload' == mapType ) {
			wicUpdateColumnMap( dragObject, dropObject )
		} else if ( 'form' == mapType ) {
			wpIssuesCRM.updateFormFieldMap( dragObject, dropObject );
		}	
	}
	
	function loadMap ( mapType ) {
		if ( 'upload' == mapType ) {
			loadColumnMap();
		} else if ( 'form' == mapType ) {
			wpIssuesCRM.loadFormFieldMap();
		}	
	}
	
	
	
	// on initial load, get column from database and move draggables into place
	function loadColumnMap() {
		
		wpIssuesCRM.ajaxPost( 'upload_map', 'get_column_map',  $('#ID').val(), '', function( response ) {
			// calling parameters are: entity, action_requested, id_requested, data object, callback
			wicColumnMap = response;
			// loop through the response dropping upload-fields into targets
			for ( x in wicColumnMap ) {
				if ( wicColumnMap[x] > '' ) {
					draggableID = "wic-draggable___" + x ;
					droppableID =  "wic-droppable" + '___' + wicColumnMap[x].entity + '___' + wicColumnMap[x].field ;
					// drop the draggable upload field into the droppable
					wpIssuesCRM.dropEventDetails ( draggableID, droppableID ) ;
				}
			}	
			// enable the draggables, but only if haven't already started the upload
			if ( initialUploadStatus != 'completed' && initialUploadStatus != 'started' && initialUploadStatus != 'reversed' ) {
				$( ".wic-draggable" ).draggable( "enable" );
			}
			// check for pending errors and set CONSTRUCTED_STREET_ADDRESS by running a save 
			wpIssuesCRM.ajaxPost( 'upload_map', 'update_column_map',  $('#ID').val(), wicColumnMap, function( response ) {
				handleMapSaveResponse ( response, true ) 						
			});		
		});
	}
	
	// based on a drag action, update column map, both in browser and on server
	function wicUpdateColumnMap ( upload_field, entity_field_object ) {
		// in possible excess of caution, disable draggable during update process; it does not disable the moving item
		// this should be blindingly fast, but in server outage, this might let user know of problem 		
		$ ( "#post-form-message-box" ).text( wicSaveMapMessage + " Saving field map . . . ")
		$( ".wic-draggable" ).draggable( "disable" );	

		// update column map in browser
		wicColumnMap[upload_field] = entity_field_object;

		// send column map on server
		wpIssuesCRM.ajaxPost( 'upload_map', 'update_column_map',  $('#ID').val(), wicColumnMap, function( response ) {
			// reenable draggables after update complete 
			$( ".wic-draggable" ).draggable( "enable" );
			handleMapSaveResponse ( response, false ) 
		});
		// also send the particular map update to the server for learning, but only for non-generic column titles
		if ( upload_field.slice( 0 , 7 ) != 'COLUMN_' || upload_field.length < 7 ) {
				wpIssuesCRM.ajaxPost( 'upload_map', 'update_interface_table',  upload_field, entity_field_object, function( response ) {
				});  
		}	
	
	}
	
	// handle response from save ajax call with two cases -- initial load and not initial load
	function handleMapSaveResponse ( response, init ) {
		// restore to initial if no errors found
		if ( '' == response ) {
			if ( ! init ) {
				$ ( "#post-form-message-box" ).text(  "Field map saved.")
				$ ( "#post-form-message-box" ).removeClass( 'wic-form-errors-found' )
				$ ( "#wic-upload-validate-button" ).prop("disabled",false)
				$ ( "#wic-upload-express-button" ).prop("disabled",false)
			}
		} else {
			if ('EXPRESS_INELIGIBLE' != response ) {
				$ ( "#post-form-message-box" ).text( "Field map saved with errors: " + response );
				$ ( "#post-form-message-box" ).addClass( 'wic-form-errors-found' )
				$ ( "#wic-upload-validate-button" ).prop("disabled",true)
			} else {
				$ ( "#post-form-message-box" ).removeClass( 'wic-form-errors-found' )
				$ ( "#post-form-message-box" ).text( "Field map saved OK, but cannot run express with ID or any activity field mapped." );
				$ ( "#wic-upload-validate-button" ).prop("disabled",false)
			}
			$ ( "#wic-upload-express-button" ).prop("disabled",true)
		}
	}
	
	
	// extract field and entity from droppable entity OR extract upload_field from draggable entity
	function wicParseIdentifier( identifier ) {
		// three underscores is separator
		var first___ 	= identifier.indexOf( '___' );
		var second___ 	= identifier.lastIndexOf ( '___');
		if ( second___ === first___ ) {  // can handle upload field (draggable) identifiers with one separator 
			var uploadField 		= identifier.slice ( second___ + 3 );
			return ( uploadField );		
		} else {									 // can also handle database field with two which return as objects
			var entity 		= identifier.slice ( first___ + 3, second___ );
			var field 		= identifier.slice ( second___ + 3 );
			return_object 	= {
				'entity' : entity,
				'field'	: field
			}  
			return ( return_object ) ;
		}	
	}
	
	// used in dropEvent and also in initial load
	wpIssuesCRM.dropEventDetails = function ( draggableID, droppableID ) {
		escDropped =  wpIssuesCRM.jq ( draggableID ) 
	    escDroppable = wpIssuesCRM.jq( droppableID )
		// get the objects from the identifiers
		wicDropped = $( escDropped );
		// make sure droppable still exists, don't want to populate droppable with a ghost
		if ( 0 == wicDropped.length ) {
			return false;
		}
		wicDroppable = $( escDroppable );
		// no take six actions as to the dropped item
		// (1) mark dropped with an identifier from the droppable
		wicDropped.addClass( droppableID );
		// (2) mark dropped as in the dropped state
		wicDropped.addClass( "wic-draggable-dropped" );
		// (3) prevent dropped from reverting to here 
		wicDropped.draggable( "option", "revert", false ); // if remains true will revert to this place on next move
		// (4) detach dropped from draggable column or from previous droppable
		wicDropped.detach();
		// (5) append dropped to the droppable
		wicDropped.appendTo( wicDroppable );
		// (6) reset css to pre-drag relative position, zero
		wicDropped.css( "top" , "0" );
		wicDropped.css( "left" , "0" );
		// now take two actions as to the droppable
		wicDroppable
			// show this droppable as occupied
			.addClass( "wic-state-dropped" ) // change css
			// change accept parameter to only the current draggable (note that can't just accept nothing b/c won't register the out event)				
			.droppable ( "option", "accept", "." + droppableID ); 
	}

	// escapes characters that are css reserved
	// https://learn.jquery.com/using-jquery-core/faq/how-do-i-select-an-element-by-an-id-that-has-characters-used-in-css-notation/
	wpIssuesCRM.jq = function ( myid ) {
 	    return "#" + myid.replace( /(:|\.|\[|\]|,|=)/g, "\\$1" );
 	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	



