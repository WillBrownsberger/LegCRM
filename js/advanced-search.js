/*
*
*	advanced_search.js 
*
*	
*
* Advanced Query functions -- these functions support intuitive display of advanced search query terms
*
* Within search term rows:
* 	(1) primary field selected determines control to be used for value input ( and type fields and comparison values available )
*		-- whole row set by do_field_interaction_rules on server side
*		-- on change selected field, get new whole row subform
*	(2) if change comparison operator, may change control for value input
*		-- on issues, will swap issue autocomplete in and out based on comparison
*		-- on other fields, should hide input control if comparison does not require input (e.g., is null)
*	(3) if change aggregator in having row, may override input field (show count field; hide field selector)
*		-- fires on control change or initialization (1) or on change aggregator
* Show/hide combination terms based on whether have >1 rows to combine
* Show/hide having row depending on whether doing constituent or activity search
*
*/

// add delegated listeners to the container on ready
jQuery( document ).ready( function($) { 

	// row add handler
	$( "#wp-issues-crm" ).on ( "addedWICRow",  "[id^=advanced_search_] .visible-templated-row", function (e) {
		wpIssuesCRM.ShowHideAdvancedSearchCombiners();
	})  

	// row delete handler
	.on ( "deletedWICRow", "[id^=advanced_search_] .hidden-template", function (e) {
		$( this ).remove();
		wpIssuesCRM.ShowHideAdvancedSearchCombiners();
	})  

	// manage display of amounts
	.on( "blur", ".wic-input.activity-amount", function ( event ) {
		wpIssuesCRM.standardizeAmountDisplay ( this );
	})
 
 	// set date picker -- done in constituent.js for all dates (except the activity popup which is not in #wp-issues-crm 
	
	// handler for changes to field selector in constituent/activity rows (replace whole row)
	.on( "change", 	"#wic-form-advanced-search [id*='activity_field'], " + 
					"#wic-form-advanced-search [id*='constituent_field']," + 
					"#wic-form-advanced-search [id*='issue_field']", 
		function ( event ) {
			var changedRow = $( this ).parents( ".visible-templated-row" )[0];
			var data = wpIssuesCRM.extractBlockVars ( changedRow )
			wpIssuesCRM.ajaxPost( 'advanced_search', 'make_blank_row', data.rowFieldId , data, function( response ) {
				// replace row with the response
				var newRow = $.parseHTML( response );
				var newRowObject = $( newRow );
				$( changedRow ).replaceWith ( newRow );
		});
	})

	// handler for changes to field selector in having rows (replace only value field) 
	.on( "change", "#wic-form-advanced-search [id*='having_field']", function ( event ) {
		var changedRow = $( this ).parents( ".visible-templated-row" )[0];
		wpIssuesCRM.replaceHavingField ( changedRow )
	})


	// handler for change comparison operators (not having row); 
	.on( "change", 
			"#wic-form-advanced-search [id*='activity_comparison']," + 
			" #wic-form-advanced-search [id*='constituent_comparison'], " +
			" #wic-form-advanced-search [id*='issue_comparison'] ",
		function ( event ) {
			var changedBlock = $( this ).parents( ".wic-multivalue-block" )[0];
			wpIssuesCRM.changeComparison( changedBlock );
		})
	
	// handler for change to constituent having aggregator
	.on( "change", ".constituent-having-aggregator .wic-selectmenu-input", function ( event ) { 
		var changedBlock = $( this ).parents( ".wic-multivalue-block.advanced_search_constituent_having" )[0];
		wpIssuesCRM.changeHavingAgg( changedBlock )
	})

	// set up delegated event listener for changes to constituent or activity choice
	.on( "change", "#primary_search_entity", function () {
		wpIssuesCRM.showHideHavingClauses() 
	})

	// listener for inspection button
	.on( "click", "#search_inspection_button", function() {
		wpIssuesCRM.alert ( $(this).val() );
	})

	// set up listener to trigger form initialization
	.on ( "initializeWICForm", function () {
		wpIssuesCRM.initializeAdvanced() 
	});

	// initialize directly on ready in case of get access
	wpIssuesCRM.initializeAdvanced();
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.initializeAdvanced = function() {
		if (  $( "#wic-form-advanced-search" )[0] ) { // run on advanced search	
			// initialize combiners display
			wpIssuesCRM.ShowHideAdvancedSearchCombiners();
			// initialize having clause display
			wpIssuesCRM.showHideHavingClauses();
		} else if ( $( "#wic-post-list" )[0]  && ! $( "#dashboard-sortables" )[0]  ) {  // run on lists except on dashboard lists
			wpIssuesCRM.constituentExportButton();
			wpIssuesCRM.shareName();
			// bind scroll event to list (which is overflow-y: scroll); scroll does not bubble, so must initialize each time
			wpIssuesCRM.scrollCallOutstanding = 0;
			$( "#search_item_list" ).scroll( function(){ 
				wpIssuesCRM.doScrollCall ();
			});
			wpIssuesCRM.doScrollCall(); // start the recursion before first scroll to fill in a very tall screen
		}			
	}; 

	// handle advanced search list button
	wpIssuesCRM.constituentExportButton = function() {
		downloadButton = $( "#wic-post-export-button" )
		if ( 1 == downloadButton.length ) {
			// eliminate shadow copy of button
			if (  1 == $( "#wic-post-export-button-button" ).length  ) {
				$( "#wic-post-export-button-button" ).remove();
			}
			downloadButton.selectmenu(); 
			downloadButton.on( "selectmenuselect", function( event, ui ) {
				menuButton = $( this );
				if ( menuButton.val() > '' )  {
					$( "#wic-export-parameters" ).val( 'constituent,constituent,' + menuButton.val() + ',' + $( "#search_id" ).val() )
					$( "#wic-top-level-form" ).submit();
				}
			}); 

		}
	}

	// handle advanced search list name field
	wpIssuesCRM.shareName = function () {
		shareNameField = $( "#share_name" );
		shareNameField.on ( "change", function () {
			$( "#share_name_save_flag" ) .show();
			wpIssuesCRM.ajaxPost( 'search_log', 'update_name',  $( "#search_id" ).val(), shareNameField.val(), function( response ) {
				$( "#share_name_save_flag" ).hide();
			});
		});
	}

	wpIssuesCRM.doScrollCall =function () { 
		// scroll can be outstanding when user branches from list to an individual on list, so, before iterating further, check
		if ( !  $( "#wic-post-list" )[0] ) {
			return false;
		}
		// otherwise proceed				
		var retrieveLimit = Number( $( "#retrieve_limit" ).val() );
		var nextOffset = Number( $( "#list_page_offset" ).val() ) + retrieveLimit; 
		var foundCount = Number ( $("#found_count").val() );
		if ( 
			(  
				$( ".wic-post-list-line:last" ).offset().top < 
				( 
					$( "#search_item_list" ).offset().top + 
					$( "#search_item_list" ).height() + 
					+ 2000 
				) 
			) && // keep from getting close to bottom of page
			0 == wpIssuesCRM.scrollCallOutstanding && // there is not already a call pending
			nextOffset < foundCount // there are more items to get
		) {
			wpIssuesCRM.scrollCallOutstanding = 1;
			$( "#list-ajax-scroll" ).show(); 
			wpIssuesCRM.ajaxPost( 'advanced_search', 'get_search_page', $( "#search_id" ).val() , nextOffset, function( response ) {
				$( "#list-ajax-scroll" ).hide(); 
			 	$( "#search_item_list" ).append (response);
				$( "#list_page_offset" ).val ( nextOffset );
				wpIssuesCRM.scrollCallOutstanding = 0;
				if ( nextOffset + retrieveLimit < foundCount ) {
					wpIssuesCRM.doScrollCall();  //keep getting more until bottom no longer visible 
				} else {
					$( "#list_terminator" ).html( '<span class="dashicons dashicons-yes"></span><em> List fully loaded -- all ' + foundCount + ' items viewable.</em>' )
				} 
			});
		}
	}

	// utility for parsing values for a row
	wpIssuesCRM.extractBlockVars = function ( fieldMultivalueBlock ) {
		var currentBlock 	= $( fieldMultivalueBlock );
		var newLabel 		= currentBlock.find( "[id*='_field']").first().next().val();
		var	valueFieldID	=  currentBlock.find( "[id*='_value']" ).attr( "id" ); // more than one on multiselect, but first is correct
		// set up standard row data object	
		var data = { 
			valueFieldID:	valueFieldID,
			comparison: 	currentBlock.find( "[id*='_comparison']" ).first().val(),			// value of the comparison field
			entity: 		valueFieldID.substring( 0, valueFieldID.indexOf( '[' ) ),	// entity of the row -- advanced_search_constituent, advanced_search_activity, advanced_search_constituent_having
			field_slug: 	valueFieldID.substring( valueFieldID.lastIndexOf( '[' ) + 1, valueFieldID.lastIndexOf ( ']' ) ), // field_slug for the value field -- constituent_value
			instance:		valueFieldID.substring( valueFieldID.indexOf ( '[' ) + 1, valueFieldID.indexOf( ']' ) ), // row number for the entity
			rowFieldId:		currentBlock.find( "[id*='_field']" ).first().val(),	// data dictionary id of the value field currently selected for row
			rowFieldSlug:	newLabel.substring( newLabel.indexOf( ':' ) + 1,newLabel.indexOf(' ')), // data dictionary field_slug of of value field currently selected for row
			aggregatorIsCount:  ( 'COUNT' == $( currentBlock ).find( ".constituent-having-aggregator .wic-selectmenu-input" ).val() )
		}
		return data;
	}

	/*
	* 3 functions serving change of comparison
	*	- changeComparison -- router 
	*	- replaceIssueControl
	*	- showHideValueFieldonBlank
	*/
	// router -- no cases where having rows affected
	wpIssuesCRM.changeComparison = function ( fieldMultivalueBlock ) {
		var blockVars = wpIssuesCRM.extractBlockVars ( fieldMultivalueBlock ); 
		// change issue control on change comparison unless both already multi_select category control and new comparison is a category value
		//	if ( 'issue' == blockVars.rowFieldSlug ) { 
		//		if ( 0 == $( fieldMultivalueBlock ).find ( ".wic_multi_select" ).length || 'cat' != blockVars.comparison.substr( 0, 3 ) ) { 
		//		replaceIssueControl ( fieldMultivalueBlock );				
		//		} 
		//	} else {
			showHideValueFieldonBlank ( fieldMultivalueBlock );
		//}
	} 
	/*swap issue control and manage show hide of autocomplete
	function replaceIssueControl( fieldMultivalueBlock ) { 
		// set up data object 		
		var data = wpIssuesCRM.extractBlockVars ( fieldMultivalueBlock )
		wpIssuesCRM.ajaxPost( 'advanced_search', 'make_blank_control', data.rowFieldId , data, function( response ) {
			// make a control in the current document context from the response
			var newControl = $.parseHTML( response );
			var newControlObject = $( newControl );
			var oldControl = $( document.getElementById ( data.valueFieldID ) );
			if ( oldControl.hasClass( 'wic-selectmenu-input' ) ) {
				oldControl = oldControl.parent().parent();
			}
			oldControl.replaceWith ( newControl );
		});	
	}*/
	// show hide field on blank -- have to repeat this server logic on client side b/c may not involve a control swap
	function showHideValueFieldonBlank ( advancedSearchMultivalueBlock) {
		var rowComparisonOperator = $( advancedSearchMultivalueBlock ).find( "[id*='_comparison']" ).val();
		var $valueField = $( advancedSearchMultivalueBlock ).find( "[id*='_value']" );
		if ( $valueField.hasClass( 'wic-selectmenu-input' ) ) {
			$valueField = $valueField.parent().parent();
		}
		if ( -1 < $.inArray ( rowComparisonOperator, ["BLANK", "NOT_BLANK", "IS_NULL"] ) ) { 
			$valueField.hide()
		} else {
			$valueField.show()
		}
	} 

	/*
	* two functions to support having row changes
	*	- changeHavingAgg -- router and show/hide
	*	- replaceHavingField -- field swap
	*/
	// activate this when aggregator changes (not on startup -- row comes in properly set
	wpIssuesCRM.changeHavingAgg = function( changedRow ) {
		var blockVars = wpIssuesCRM.extractBlockVars ( changedRow );
		currentFieldObject = $( changedRow ).find( ".constituent-having-field" );
		if ( blockVars.aggregatorIsCount ) {
			wpIssuesCRM.replaceHavingField ( changedRow );
			// hide field selector but leave in place for spacing
			currentFieldObject.css( "visibility" , "hidden" ) 
		} else {
			// show field selector
			currentFieldObject.css( "visibility" , "visible" ) 
			// restore value input field if changing from count field (otherwise unnecessary, already good)
			if (  $( document.getElementById ( blockVars.valueFieldID ) ).hasClass( "having-count" ) ) {  
				wpIssuesCRM.replaceHavingField( changedRow )
			}
		} 
	}
	// having replace field -- looks like issue field replace, but separate function for clarity
	wpIssuesCRM.replaceHavingField = function ( changedRow ) {
		var data = wpIssuesCRM.extractBlockVars ( changedRow )
		wpIssuesCRM.ajaxPost( 'advanced_search', 'make_blank_control', data.rowFieldId , data, function( response ) {
			// make a control in the current document context from the response
			var newControl = $.parseHTML( response );
			var newControlObject = $( newControl );
			var oldControl = $( document.getElementById ( data.valueFieldID ) );
			if ( oldControl.hasClass( 'wic-selectmenu-input' ) ) {
				oldControl = oldControl.parent().parent();
			}			
			oldControl.replaceWith ( newControl );
		});
	}

	// show hide combination options as appropriate -- this is aesthetics ( server parses appropriately regardless )
	wpIssuesCRM.ShowHideAdvancedSearchCombiners = function() {
	
		// combinations of constituent conditions
		if ( $( "#advanced_search_constituent-control-set" ).children( ".visible-templated-row" ).length > 1 ) {
			$( "#wic-control-constituent-and-or" ).children().show();
		} else {
			$( "#wic-control-constituent-and-or" ).children().hide();
			$( "#constituent_and_or" ).val("and");
		}
	
		// combinations of activity conditions
		if ( $( "#advanced_search_activity-control-set" ).children( ".visible-templated-row" ).length > 1 ) {
			$( "#wic-control-activity-and-or" ).children().show();
		} else {
			$( "#wic-control-activity-and-or" ).children().hide();
			$( "#activity_and_or" ).val("and");
		}

		// combinations of issue conditions
		if ( $( "#advanced_search_issue-control-set" ).children( ".visible-templated-row" ).length > 1 ) {
			$( "#wic-control-issue-and-or" ).children().show();
		} else {
			$( "#wic-control-issue-and-or" ).children().hide();
			$( "#issue_and_or" ).val("and");
		}

		// combinations of constituent_having conditions
		if ( $( "#advanced_search_constituent_having-control-set" ).children( ".visible-templated-row" ).length > 1 ) {
			$( "#wic-control-constituent-having-and-or" ).children().show();
		} else {
			$( "#wic-control-constituent-having-and-or" ).children().hide();
			$( "#constituent_having_and_or" ).val("and");
		}

/*		// combinations of activity and constituent conditions
		if ( 	$( "#advanced_search_activity-control-set" ).children( ".visible-templated-row" ).length > 0 && 
				$( "#advanced_search_constituent-control-set" ).children( ".visible-templated-row" ).length > 0 ) {
			$( "#wic-control-activity-and-or-constituent" ).children().show();
		} else {
			$( "#wic-control-activity-and-or-constituent" ).children().hide();
			$( "#activity_and_or_constituent" ).val("and");
		}*/
	}

	wpIssuesCRM.showHideHavingClauses = function() { 
		// only show having block if have chosen constituent or issue as search mode
		if (
			'constituent' == $( "#primary_search_entity" ).val() || 
			'issue' == $( "#primary_search_entity" ).val()) 
		{ 
			$( "#wic-field-group-search_constituent_having" ).show();	
		} else {
			$( "#wic-field-group-search_constituent_having" ).hide();
		}
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
