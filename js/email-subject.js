/*
*
* email-subject.js
*
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	// used across keyup key down functions
	var timeOutTimerObject;

	wpIssuesCRM.loadSubjectListeners = function() {
		/*
		*	Handle search triggering on typing on subject page
		*/
		$( "#wic-email-manage-subjects-content" ).on( "keyup", "#search_subjects_phrase", function () {
			// first impose delay before any action
			timeOutTimerObject = setTimeout ( wpIssuesCRM.loadSubjectList, 300 );
		})
		.on( "keydown", "#search_subjects_phrase", function () {
			clearTimeout ( timeOutTimerObject ); 
		})
		// new subject button ( on process email )		
		.on ( "click", "#add-new-subject-button", function () {
			newSubjectIssueWindow()
		});
	}

	/*
	*
	*
	*/
	wpIssuesCRM.loadSubjectList = function() {
		$( "#subject-list-ajax-loader" ).show();
		var dataObject = {
			search_string: $( "#search_subjects_phrase" ).val()
		};
		wpIssuesCRM.ajaxPost( 'email_subject', 'show_subject_list',  0, dataObject,  function( response ) {
			$ ( "#subject-list-ajax-loader" ).hide();
			$ ( "#subject-list-inner-wrapper" ).html( response );
			$ ( ".incoming-email-subject-list-item" ).tooltip( {show: false, hide: false });
			$ ( ".wic-subject-delete-button" ).click ( function() {
				var deletedRow = $ ( this ).parent().parent();
				wpIssuesCRM.ajaxPost( 'email_subject', 'delete_subject_from_list',  0, $( this ).val(),  function( response ) {  
					deletedRow.hide('fast', function(){ deletedRow.remove(); })
				});
			});
		});		

	}

	// function used in process email 
	function newSubjectIssueWindow ( searchLogId ) {

		// define dialog box
		var divOpen 				= '<div id="new_subject_issue_popup" title="Create a new subject line to issue mapping." class="ui-front"><form id="new-subject-form" >';
		var editSubject				= '<input id="incoming-subject" placeholder="Incoming Subject" value=""/>';
		var editIssue				= $( "#wic-inbox-work-area #issue" ).closest( ".wic-selectmenu-wrapper" )[0].outerHTML.replace( /issue/g, "subject_issue" );
		var editProCon				= $( "#wic-inbox-work-area #pro_con" ).closest( ".wic-selectmenu-wrapper" )[0].outerHTML.replace( /pro_con/g, "subject_pro_con" );
		var legend					= '<div id="wic-subject-editor-legend">' + $ ( "#wic_subject_editor_legend" ).html() + '</div>';
		var divClose 				= '</form></div>';
		dialog 				= $.parseHTML( divOpen + editSubject + editIssue + editProCon + legend + divClose );
		
		// show the dialog
		replyViewEditObject = $( dialog );
  		replyViewEditObject.dialog({
  			appendTo: "#wp-issues-crm",
  			closeOnEscape: true,
  			close: function ( event, ui ) {
					replyViewEditObject.hide('slow', function(){ replyViewEditObject.remove(); })						// cleanup object
  				},
			width: 960,
			height: 800,
			show: { effect: "fadeIn", duration: 800 },
			buttons: [
				{
					width: 100,
					text: "Save",
					click: function() {
						var subjectLineObject = {
							subject: 		$ ( "#incoming-subject" ).val(),
							issue:			$ ( "#subject_issue" ).val(),
							proCon:			$ ( "#subject_pro_con" ).val()
						}						
						if ( ! subjectLineObject.issue  ) {
							wpIssuesCRM.alert ( 'Please select an issue.' )
						} else if ( subjectLineObject.subject.trim().length < 2 ) {
							wpIssuesCRM.alert ( 'Searched subject must have at least 2 characters.' )  
						} else {
							wpIssuesCRM.ajaxPost( 'email_subject', 'manual_add_subject',  0, subjectLineObject,  function( response ) {
								replyViewEditObject.dialog( "close" );
								wpIssuesCRM.loadSubjectList();
							});
						}
					}
				},
				{
					width: 100,
					text: "Cancel",
					click: function() {
						replyViewEditObject.dialog( "close" );
					}
				}
			],
  			modal: true,
  		});
	}

	
} ( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
