/*
*
* dashboard.js
*
*/

jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on ( "initializeWICForm", function () {
		if ( $ ( "#dashboard_overview" )[0] ) {
			wpIssuesCRM.loadDashboard();
		}
	});

	// initialize directly (normally ajax triggered) in case access by get
	if ( $ ( "#dashboard_overview" )[0] ) {
		wpIssuesCRM.loadDashboard();
	}

});


// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.loadDashboard = function () {
		$( "#dashboard-sortables" ).sortable({
			stop: function (event,ui) {
				wpIssuesCRM.saveDashboardLayout ();
			},
			cursor: "move",
			cursorAt: { left: 5 },
			delay: 150,
			forcePlaceholderSize: true,
			handle: ".wic-dashboard-title", 
			opacity: 0.7,
			tolerance: "pointer"
		});

		wpIssuesCRM.initializeActivitiesTypeMenu();

		// only open what has been set as tall
		$( ".wic-dashboard.wic-dashboard-tall" ).each( function () { 
			wpIssuesCRM.loadDashboardPanel ( $( this ).attr("id") );
		});

		// click on drag handle opens panel ( or toggles it closed)
		$( ".wic-dashboard-title.wic-dashboard-drag-handle" ).on ( "click", function () {
			var currentTile = $( this ).parent();
			if ( currentTile.hasClass("wic-dashboard-tall") ) {
				 currentTile.removeClass( "wic-dashboard-tall" );
  				 wpIssuesCRM.saveDashboardLayout();
			} else {
				$( ".wic-dashboard").removeClass ( "wic-dashboard-tall" );
				currentTile.addClass ( "wic-dashboard-tall" );
				wpIssuesCRM.loadDashboardPanel ( currentTile.attr("id") );
			}
		});	

		// click on drop down handle opens panel and closes others
		$( ".wic-dashboard" ).on( "focus", ".wic-selectmenu-input-display", function () { 
			var currentTile = $( this ).parent().parent().parent();
			if ( ! currentTile.hasClass("wic-dashboard-tall") ) {
				$( ".wic-dashboard").removeClass ( "wic-dashboard-tall" );
				currentTile.addClass ( "wic-dashboard-tall" );
				wpIssuesCRM.loadDashboardPanel ( currentTile.attr("id") );
				// dashboard layout will be saved on load
			}
		});	

		// for drop downs, selection triggers refresh 
		$( ".wic-dashboard .wic-selectmenu-input").on( "change", function() {
			wpIssuesCRM.loadDashboardPanel ( $( this ).parent().parent().parent().attr("id") );
		});

		$( ".wic-dashboard-refresh-button" ).on ( "click", function () {
			wpIssuesCRM.loadDashboardPanel ( $( this ).parent().attr("id") );
		});
		
	}

	// includes same action as saveDashboardLayout on server side
	wpIssuesCRM.loadDashboardPanel = function ( panel ) {
		wpIssuesCRM.ajaxPost(  'dashboard', panel, '', assembleDashboardStatus(), function( response ) {
			$( "#" + panel ).children( ".wic-inner-dashboard").html( response );

			// special initialization terms for particular panels
			if ( 'dashboard_searches' == panel ) {
				wpIssuesCRM.initializeSearchLogTooltips();
			} 

			// save state to allow return to same panel
			wpIssuesCRM.saveState ( 'dashboard', 'dashboard' );
			
		});	
	}

	wpIssuesCRM.saveDashboardLayout = function ()  {
		wpIssuesCRM.ajaxPost(  'dashboard', 'save_dashboard_preferences', '', assembleDashboardStatus() ,  function( response ) {
		});
	}

	assembleDashboardStatus = function() {
	
		// set open item
		var varTall = [];
			$( '.wic-dashboard-tall' ).each ( function () {
			varTall.push ( $( this ).attr("id") );
		});
		
		// set sort order
		var varSort =  $( "#dashboard-sortables" ).sortable( "toArray");
		
		// set activity vars
		var varPreID;
		var includedTypes=[];
		$( "#filter-activities-menu .wic-input-checked" ).each ( function () {
			if ( $( this ).prop("checked") ) {
				varPreID =  $(this).attr("id").substr(14);
				includedTypes.push ( varPreID.substr(0, varPreID.length -1 ) );
			}
		});
		var activityVars = {
			date_range: 		$( "#dashboard\\[date_range\\]\\[date_range\\]" ).val(),
			included: 	includedTypes
		}		
		
		// set activity_type vars
		var activityTypeVars = {
			date_range: 		$( "#dashboard\\[date_range_t\\]\\[date_range\\]" ).val(),
		}
		
		// set cases vars
		var casesVars = {
			case_assigned: 		$( "#dashboard\\[case_assigned\\]\\[case_assigned\\]" ).val(),
			case_status:		$( "#dashboard\\[case_status\\]\\[case_status\\]" ).val(), 
		}		
		
		// set issues vars
		var issuesVars = {
			issue_staff: 		$( "#dashboard\\[issue_staff\\]\\[issue_staff\\]" ).val(),
			follow_up_status:	$( "#dashboard\\[follow_up_status\\]\\[follow_up_status\\]" ).val(), 
		}	
		
		var dashboardConfig = { 
			sort: varSort,
			tall: varTall,
			dashboard_activity: activityVars,
			dashboard_activity_type: activityTypeVars, 
			dashboard_cases: casesVars,
			dashboard_issues: issuesVars
		}	

		return dashboardConfig;
		
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	