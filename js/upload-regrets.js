/*
*
* upload-regrets.js
*
*/
jQuery(document).ready(function($) { 
	
	$( "#wp-issues-crm" ).on ( "click", "#wic-upload-backout-button", function () {
			wpIssuesCRM.doBackoutPopup() 
	});

});
// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {
	
	wpIssuesCRM.doBackoutPopup = function() {
	
		uploadID	 		=	$( "#ID" ).val();
		uploadStatus		= 	$( "#upload_status" ).val();
		uploadFile			= 	$( "#upload_file" ).val();
		uploadParameters 	=	JSON.parse ( $( "#serialized_upload_parameters" ).val() )  	

		express				= 	uploadStatus.indexOf ( 'express' ) > -1;


		// now proceed to dialog
		backoutDialog = $.parseHTML ( 
			'<div id="backout-dialog" title="Backing out updates from ' + uploadFile + '">' +
				'<div id="wic-backout-progress-bar"></div>' +
				'<div id="backout-info">' +
					$( "#backout_legend" ).html() +
				'</div>' +
			'</div>'
		);
		wpIssuesCRM.backoutDialogObject = $( backoutDialog );
		wpIssuesCRM.backoutDialogObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: false,
			close: function ( event, ui ) {
				wpIssuesCRM.backoutDialogObject.remove();						// cleanup object
				},
			position: { my: "left top", at: "left top", of: "#wic-upload-backout-button" }, 	
			width: 960,
			height: 480,
			buttons: [
				{
					width: 200,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.backoutDialogObject.dialog( "close" );
					}	
				},
				{
					width: 200,
					text: "Backout",
					click: function() {
						$( "#wic-backout-progress-bar" ).show();
						$( "#wic-backout-progress-bar" ).progressbar ({ value : false });			 
						$(".ui-dialog-buttonpane button").attr("disabled", true).addClass("ui-state-disabled");
						wpIssuesCRM.formDirty = true;
						var data = {
							table: uploadParameters.staging_table_name,
							express: express
						}; 
						wpIssuesCRM.ajaxPost( 'upload_regrets', 'backout_new_constituents', uploadID, data,  function( response ) {		
							wpIssuesCRM.formDirty = express; // true if going back to parse step; false if returning to download page
							wpIssuesCRM.loadUploadSubform ( express ? 'map' : 'download' ); 
							wpIssuesCRM.backoutDialogObject.dialog( "close" );
						});	
					}
				}
			],
			modal: true
		});

	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	