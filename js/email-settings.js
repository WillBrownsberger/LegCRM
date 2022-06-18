/*
*
* email-settings.js
*
*
*/

// self-executing anonymous namespace
( function( wpIssuesCRM, $, undefined ) {

	// enclosed variable storing all the set values of the processing settings form 
	var processingOptionsObject = {};

	// form initialization -- 
	wpIssuesCRM.loadSettingsForm = function () {
		// load settings form fresh 
		var $loader = $( '#settings-ajax-loader' );
		$loader.show();
		wpIssuesCRM.ajaxPost( 'email_process', 'setup_settings_form',  0, '',  function( response ) {

			$( "#wic-load-settings-inner-wrapper").html( response );
			$loader.hide();
			
			// change tracker for form fields
            $(" #wic-form-email-settings ").on( "keydown change spinchange", ":input", function ( event ) {
 				if ( 'signature' != $( this).attr("id")  ) { 
 					$(".wic_save_email_settings").text( "Save unsaved options").addClass( "wic-button-unsaved");
 				}            
 			})
			
			$(".wic_save_email_settings").on( "click", function () {
				saveProcessingOptions();
			})
			
			// save button for signature
			$( "#wic_save_current_user_sig" ).on( "click",  function ( event ) {
				$( event.target ).text( "Saving . . .").attr( "disabled", true);
				wpIssuesCRM.ajaxPost( 'user', 'set_wic_user_preference_wrap',  'signature', $( "#signature" ).val(),  function( response ) {
					$( event.target ).text( "Saved signature").attr( "disabled", false).removeClass( "wic-button-unsaved");
				});
			});

			$("#wic-form-tabbed").tabs({
				heightStyle: "content",
			});	
			// remove any extant tinymce instances -- even when form area is reloaded, a conflicting instance object persists -- note: this prior code did not accomplish same: tinymce.EditorManager.editors = []
			tinymce.remove();
			// new tinymce instance for the signature field 
			wpIssuesCRM.tinyMCEInit ( "signature", false, false, false, function() {
				$( "#wic_save_current_user_sig" ).text( "Save unsaved signature").addClass( "wic-button-unsaved"); // change function
			});

			// tinymce instance for the autoreply field	
			wpIssuesCRM.tinyMCEInit ( "non_constituent_response_message", false, false, false, function() { // change function
				$("#non_constituent_response_message").trigger("change");
			});	
		
 		});		

		
	}


	// for saving processing options on change (excluding signature which is user specific and updated on startup
	function saveProcessingOptions (  ) {

		// logic to protect against unintended auto replies
		var responder = $( "#use_non_constituent_responder" );
		var rules = $( "#use_is_my_constituent_rules" );

		// if do not have a reasonable subject line and reply or if no constituent logic, disable reply
		if ( responder.val() > 1 ) {
			if ( 
				$ ( "#non_constituent_response_subject_line" ).val().length < 5 ||
				$ ( 
					 $.parseHTML(
						$( "#non_constituent_response_message" ).val()
					 ) 
				   )
					.text()
					.length < 20
			   ) {
				wpIssuesCRM.alert ( '<p>Reply cannot be enabled without at least 5 characters of subject line and 20 characters of message content.</p>')
				return; 
			}
		}
	
		// manage forget date setting
		if( '' == $( "#wic-form-email-settings #forget_date_interval" ).val() ) { 
			$( "#wic-form-email-settings #forget_date_interval" ).val(60)
		 } else {
			if ( parseFloat( $( "#wic-form-email-settings #forget_date_interval" ).val() ) > 10000) {
				$( "#wic-form-email-settings #forget_date_interval" ).val(10000)
			}
			else if ( 
				! Number.isInteger( parseFloat( $( "#wic-form-email-settings #forget_date_interval" ).val() ) ) ||
				parseFloat($( "#wic-form-email-settings #forget_date_interval" ).val()) < 1
			) {
				wpIssuesCRM.alert(
					'<p>Forget date interval must be a positive whole number.</p>' 
				);
			return;
			}
		 }
		 
		// loop through inputs, pack them into object
		$ ( "#wic-form-email-settings :input:not(:button)" ).not( "#wp_issues_crm_post_form_nonce_field, .wic-selectmenu-input-display, .signature" ).each( function () {

			inputElement = $ ( this );
			if ( 'checkbox' == inputElement.attr('type')  ) {
				processingOptionsObject[inputElement.attr("id")] = inputElement.prop("checked" ) // attr is initial value only
			} else {
				processingOptionsObject[inputElement.attr("id")] = inputElement.val();
			}
		});
		// do the save
		$(".wic_save_email_settings").text( "Saving . . .").attr( "disabled", true);
		wpIssuesCRM.ajaxPost( 'email_process', 'save_processing_options',  0, processingOptionsObject,  function( response ) {
			$(".wic_save_email_settings").text( "Saved").attr( "disabled", false).removeClass( "wic-button-unsaved");
		});	
	}






}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end  namespace enclosure	
