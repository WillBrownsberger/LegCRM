/*
*
* wic-upload-upload.js
*
*/

// self-executing anonymous namespace
jQuery(document).ready(function($) { 

	// this fires before the uploader bound below
	$( "#wic_upload_select_button" ).on ( "click", function( e ) {
		if  ( wpIssuesCRM.formDirty ) {
			e.stopImmediatePropagation(); // don't do the upload
			wpIssuesCRM.confirm (
				function() {
					wpIssuesCRM.formDirty = false
					$( "#wic_upload_select_button" ).trigger ( "click" )
				},
				false,
				'Do you mean to start a file upload?  Data you have entered may not be saved.' 
			)  
		} else {
			wpIssuesCRM.cancelAll();	  	// cancel other pending requests before doing the upload (including an upload in progress)
			wpIssuesCRM.formDirty = false;  // unset formDirty flag
		}
	});

	// this is loaded with main wpIssuesCRM -- drives a top menu button
	wpIssuesCRM.initializeUploader(); 

	$( "#wp-issues-crm" ).on ( "initializeWICForm", function () { 
		if ( $ ( "#wic-form-upload" )[0] ) { 
			wpIssuesCRM.initializeUploadUploadForm() 
		}
	})


});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	var uploadID = ''; // upload id storage for tracking through upload chunking
	wpIssuesCRM.uploaderPopupTitleMessageBase = '';
	/*
	*	User initiates upload sequence by pressing  #wic_upload_select_button to initiate
	*		This is accessible on document ready, so uploader must be bound on document ready.
	*/
	wpIssuesCRM.initializeUploader = function () {
	
		wpIssuesCRM.uploader = new plupload.Uploader({
			runtimes : 'html5',
			browse_button: 'wic_upload_select_button', 
			url: wic_ajax_object.ajax_url,
			// Maximum file size
			max_file_size : '2000mb',
			// only want one file
			multi_selection: false,
			// set chunk size well below default max file size default of 2mb 
			// also below max packet size default of 1mb for mysql
			chunk_size: '512kb',
			// Specify what files to browse for
			filters: {
			  mime_types : [
				{ title : "Text data files", extensions : "txt,csv" }
			  ]
			},
			// Rename files by clicking on their titles
			rename: true,
			// Sort files
			sortable: true,
			// Enable ability to dragdrop files onto the widget (currently only HTML5 supports that)
			dragdrop: true,
			// Views to activate
			views: {
				list: true,
				thumbs: true, // Show thumbs
				active: 'thumbs'
			}
		});

		wpIssuesCRM.uploader.init();

		wpIssuesCRM.uploader.bind('FilesAdded', function(up, files) {

			if ( up.files.length > 1 ) {
				wpIssuesCRM.uploader.removeFile( up.files[0] );
			}

			// set parameters on file add, not on init, so that if redo upload without coming in to a new main page, get fresh upload id
			wpIssuesCRM.uploader.setOption ( 'multipart_params', {
				action: 'wp_issues_crm_upload', 
				wic_nonce: wic_ajax_object.wic_nonce,
				upload_id: ''	
			});	

			doUploaderProgressPopup();
			
			wpIssuesCRM.uploader.start();
			// load form directly, passing a dummy event with an empty preventDefault function
			// wpIssuesCRM.mainFormButtonPost ( $( "#wic_upload_select_button" )[0], { preventDefault : function(){} } );

		});

		wpIssuesCRM.uploader.bind('Error', function(up, err) {
			wpIssuesCRM.uploaderDialogObject.dialog( "option", "title", 'Error copying ' + wpIssuesCRM.uploader.files[0].name + ' -- ' + ' Error #' + err.code + ": " + err.message );
		});

		// handling response codes on chunk uploader
		wpIssuesCRM.uploader.bind( 'ChunkUploaded', function ( uploader, file, response ) {
			handleUploadProgress ( uploader, file, response )
		});

		// have to run the FileUploaded step b/c file.status != DONE on last chunk. Running through the error trapping logic too.
		wpIssuesCRM.uploader.bind( 'FileUploaded', function ( uploader, file, response ) {
			handleUploadProgress ( uploader, file, response )
		});

	}

	function handleUploadProgress ( uploader, file, response ) {
		try {	
			var decodedResponse = JSON.parse ( response.response );
			if ( ! decodedResponse.OK ) {
				wpIssuesCRM.uploaderDialogObject.dialog( "option", "title", 'Error copying ' + wpIssuesCRM.uploader.files[0].name + ' -- ' + decodedResponse.info );
				wpIssuesCRM.uploader.stop();
			} else {
				// ID always start as blank on first chunk, since upload button triggers new form
				if ( ! uploadID ) { 
					wpIssuesCRM.uploader.setOption ( 'multipart_params', {
						action: 'wp_issues_crm_upload', 
						wic_nonce: wic_ajax_object.wic_nonce,
						upload_id: decodedResponse.upload_id	
					});
					uploadID = decodedResponse.upload_id;
				}
				// report progress
				wpIssuesCRM.uploaderDialogObject.dialog( "option", "title", wpIssuesCRM.uploaderPopupTitleMessageBase + ' ('  + file.percent + '%)'  );
				$( "#wic-plupload-progress-bar" ).progressbar ( "value", file.percent );
				// note: cannot test file.percent == 100 to determine if done ( it is a rounded number )
				if ( file.status == plupload.DONE ) {
					var wicUploadSelectButton =  $( "#wic_upload_select_button" );
					wicUploadSelectButton.val( 'upload,id_search,' + uploadID );
					wpIssuesCRM.mainFormButtonPost ( wicUploadSelectButton[0], { preventDefault : function(){} } );
				}
			}
		} catch( err ) {
			if ( 'JSON' == err.message.substring( 0, 4 ) ) {
				responseError = 'Apparent server side error message (JSON could not parse): ' + response.response;
			} else {
				responseError = err.message;
			}
			wpIssuesCRM.alert ( 'Apparent server side error: ' + responseError );	
			wpIssuesCRM.uploader.stop();
		}
	
	} 

	wpIssuesCRM.initializeUploadUploadForm = function () {
		wpIssuesCRM.uploaderDialogObject.dialog( "close" ); 	// when the new form is loaded, get popup out of the way
		wpIssuesCRM.formDirty = true; // form remains dirty through upload complete
	}
	
	function doUploaderProgressPopup() {

		// open dialog popup
		wpIssuesCRM.uploaderPopupTitleMessageBase = 'Copying ' + wpIssuesCRM.uploader.files[0].name + ' (' + plupload.formatSize(wpIssuesCRM.uploader.files[0].size) + ') to server ';
		uploaderDialog = $.parseHTML ( 
			'<div id="upload-dialog" title="' + wpIssuesCRM.uploaderPopupTitleMessageBase + '">' +
				'<div id="wic-plupload-progress-bar"></div>' +
			'</div>'
		);
		wpIssuesCRM.uploaderDialogObject = $( uploaderDialog );
		wpIssuesCRM.uploaderDialogObject.dialog({
			appendTo: "#wp-issues-crm",
			closeOnEscape: true,
			close: function ( event, ui ) {
				wpIssuesCRM.uploaderDialogObject.remove();
				wpIssuesCRM.uploader.stop();
				uploadID = '';  // so looks like first run through if cancelled to repeat an upload
			},
			position: { my: "top", at: "top+200", of: "#wp-issues-crm" }, 	
			width: 960,
			height: 150,
			buttons: [
				{
					width: 200,
					text: "Cancel",
					click: function() {
						wpIssuesCRM.uploaderDialogObject.dialog( "close" ); 
					}
				}
			],
			modal: true 
		});
		
		// initialize progress bar within dialog
		$( "#wic-plupload-progress-bar").progressbar({
				value: 0
		});
	}

	wpIssuesCRM.initializeDocumentUploader = function() {
	
		var issue = 0;
		var constituent_id = 0;
		
		$( "#upload-document-button").on("click", function (event) {
			if ( wpIssuesCRM.isParentFormChanged() ) {
				wpIssuesCRM.alert ( 'Save main form changes before attempting to upload documents.' );
				event.stopImmediatePropagation()
			}
		});
		
		
		if ( 'constituent' == wpIssuesCRM.parentFormEntity ) {
				constituent_id = $( "#wic-form-constituent #ID").val() ;
		} else if ( 'issue' == wpIssuesCRM.parentFormEntity ) {
				issue = $( "#wic-form-issue #ID").val();  
		}
	
		wpIssuesCRM.documentUploader = new plupload.Uploader({
			runtimes : 'html5',
			browse_button: 'upload-document-button', 
			url: wic_ajax_object.ajax_url,
			// Maximum file size -- below default max file size default of 2mb  and max packet of 1mb
			max_file_size : wpIssuesCRMSettings.maxFileSize ? wpIssuesCRMSettings.maxFileSize : '1000000',
			// only want one file
			multi_selection: false,
			multipart_params: {
				action: 'wp_issues_crm_document_upload', 
				wic_nonce: wic_ajax_object.wic_nonce,
				constituent_id: constituent_id,
				issue: issue
			},
			// no chunk_size
			// no filters
			// Rename files by clicking on their titles
			rename: true,
			// Sort files
			sortable: true,
			// Enable ability to dragdrop files onto the widget (currently only HTML5 supports that)
			dragdrop: true,
			// Views to activate
			views: {
				list: true,
				thumbs: true, // Show thumbs
				active: 'thumbs'
			}
		});

		wpIssuesCRM.documentUploader.init();	
	
		wpIssuesCRM.documentUploader.bind('FilesAdded', function(up, files) {
			wpIssuesCRM.doUpdateInProgressPopup();
			wpIssuesCRM.documentUploader.start();
		});	
	
		wpIssuesCRM.documentUploader.bind('Error', function(up, err) {
			wpIssuesCRM.alert ( 'Error copying file: ' + err.code + ", " + err.message );
		});

		wpIssuesCRM.documentUploader.bind( 'FileUploaded', function ( uploader, file, response ) {
			handleDocumentUploadComplete ( uploader, file, response )
		});
		
	}

	function handleDocumentUploadComplete ( uploader, file, response ) {
		if ( wpIssuesCRM.updateInProgressPopupObject ) {
			wpIssuesCRM.updateInProgressPopupObject.remove();
		}
		try {	
			var decodedResponse = JSON.parse ( response.response );
			if ( ! decodedResponse.OK ) {
				wpIssuesCRM.alert ( 'Error copying file -- ' + decodedResponse.info );
				wpIssuesCRM.documentUploader.stop();
			} else {
				$( "#wic_activity_list" ).prepend ( decodedResponse.info  );
				// add listener to new line
				$( "#wic_activity_list li .document-link-button" ).first().on( "click", function ( event ) {
					wpIssuesCRM.doMainDownload( event )
				});
				$( "#wic_no_activities_message" ).hide(); // hide not found message in case first
				wpIssuesCRM.doTypeFilter(); 
				wpIssuesCRM.cacheActivityArea();
			} 
		} catch( err ) {
			if ( 'JSON' == err.message.substring( 0, 4 ) ) {
				responseError = 'Apparent server side error message (JSON could not parse): ' + response.response;
			} else {
				responseError = err.message;
			}
			wpIssuesCRM.alert ( 'Apparent server side error: ' + responseError );	
			wpIssuesCRM.documentUploader.stop();
		}
	}

	// similar function for attachments as for documents; duplicate with parameters varied for simplicity
	wpIssuesCRM.initializeAttachmentUploader = function( draft_id ) {

		wpIssuesCRM.attachmentUploader = new plupload.Uploader({
			runtimes : 'html5',
			browse_button: 'upload-attachment-button', 
			url: wic_ajax_object.ajax_url,
			// Maximum file size -- below default max file size default of 2mb  and max packet of 1mb
			max_file_size : wpIssuesCRMSettings.maxFileSize ? wpIssuesCRMSettings.maxFileSize : '1000000',
			// only want one file
			multi_selection: false,
			multipart_params: {
				action: 'wp_issues_crm_attachment_upload', 
				wic_nonce: wic_ajax_object.wic_nonce,
				draft_id: draft_id
			},
			// no chunk_size
			// no filters
			// Rename files by clicking on their titles
			rename: true,
			// Sort files
			sortable: true,
			// Enable ability to dragdrop files onto the widget (currently only HTML5 supports that)
			dragdrop: true,
			// Views to activate
			views: {
				list: true,
				thumbs: true, // Show thumbs
				active: 'thumbs'
			}
		});

		wpIssuesCRM.attachmentUploader.init();	
	
		wpIssuesCRM.attachmentUploader.bind('FilesAdded', function(up, files) {
			wpIssuesCRM.doUpdateInProgressPopup();
			wpIssuesCRM.attachmentUploader.start();
		});	
	
		wpIssuesCRM.attachmentUploader.bind('Error', function(up, err) {
			wpIssuesCRM.alert ( 'Error copying file: ' + err.code + ", " + err.message );
		});

		wpIssuesCRM.attachmentUploader.bind( 'FileUploaded', function ( uploader, file, response ) {
			handleAttachentUploadComplete ( uploader, file, response )
		});
		
	}

	function handleAttachentUploadComplete ( uploader, file, response ) {
		if ( wpIssuesCRM.updateInProgressPopupObject ) {
			wpIssuesCRM.updateInProgressPopupObject.remove();
		}
		try {	
			var decodedResponse = JSON.parse ( response.response );
			if ( ! decodedResponse.OK ) {
				wpIssuesCRM.alert ( 'Error copying file -- ' + decodedResponse.info );
				wpIssuesCRM.attachmentUploader.stop();
			} else {
				$( "#compose-attachment-list" ).append ( decodedResponse.info  );
				// add listener to new line for delete button - firist??
			} 
		} catch( err ) {
			if ( 'JSON' == err.message.substring( 0, 4 ) ) {
				responseError = 'Apparent server side error message (JSON could not parse): ' + response.response;
			} else {
				responseError = err.message;
			}
			wpIssuesCRM.alert ( 'Apparent server side error: ' + responseError );	
			wpIssuesCRM.attachmentUploader.stop();
		}
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
