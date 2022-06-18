<?php
/**
*
* class-wic-admin-navigation.php
*
* called early on any online entry point (except for batch processing)
*
*
*
*/


class WIC_Admin_Navigation {

	/* 
	*
	*	All user/form requests to the plugin are routed through this navigation class
	*		GET Requests 
	*			menu options and page authorization checked here when body is loaded
	*				Page gets with additional parameters further controlled as to what parameters OK through do_page referencing $nav_array and check_security
	*			If page request includes attachment ID, it will get served off index.php to emit_stored_file before do_headers -- check_security checks capability and nonce
	*
	*		AJAX Requests to Entities run through check_security for both capability and nonce checking		
	*			AJAX Form Posts -- all forms are submitted via AJAX to lower Wordpress Admin overhead
	*			AJAX Requests -- specific actions below the entity level
	*			Only Entity Classes/methods are invoked by the AJAX endpoints -- check_security covers all entities
	*
	*		Special requests -- limited functions -- check security and nonce	
	*			Download Button submits(2)
	*			Upload requests (3)
	*
	*		Search box and autocomplete go straight to the database where user and office are checked for validity, bypassing check_security
	*
	*	THIS IS THE ONLY ROUTE TO EXECUTE APPLICATION PHP CODE
	*
	*	Since 4.5, WIC_Admin_Access contains check_security which now also enforces access limits to assigned records -- all accesses hit this (ex the above exceptions)
	*			Client side access controls are all spoofable; so test all ajax calls here on the server side, although various buttons are hidden or disabled to avoid annoyance of failed access
	*			Default in all tests is to disallow without administrator access.
	*
	*	AZURE AAD is foundation for identity and capabilities		
	*/

	// set up routing responses for each access type
	public function __construct() {
		// check if stored file conditions are met before doing anything else -- this object created before headers sent
		$this->emit_stored_file();
	}	

	/*
	*	
	*   Can't get to site at all except through Azure security
	*   
	*	This array has two main functions -- menu_setup and routing/access control to pages
	*		$nav_array is used in both and limits valid GET requests (otherwise, nav logic could attempt route to arbitrary class functions)	
 	* 			keys are pages as in add_menu_page or add_sub_menu_page; values are parameters governing the page display
 	*	
	*/
	private $nav_array = array (
		'wp-issues-crm-main' =>	array (
			'name'		=>  'Legislative CRM', 
			'default'	=>	array ( 'dashboard', 'dashboard' ),// default entity/action to invoke if $_GET does not contain permitted entity and actions
			'permitted'	=>	array ( 							// permissible entity/action pairs for the page on a GET request
								array (	'constituent',		'id_search'		),
								array (	'constituent',		'new_blank_form'),
								array (	'email_inbox',		'new_blank_form'),
								array (	'issue',			'id_search'		),
								array (	'issue',			'new_blank_form'),
								array (	'search_log',		'id_search'		),
								array (	'search_log',		'id_search_to_form'	),
								array (	'advanced_search',	'new_blank_form' ),
							),
			'mobile'	=>	true,								// works on small screens
			'security'	=>	''								// no special security level
			),
		'office-list'	=> array (
			'name'		=>  'Office', 
			'default'	=>	array ( 'office', 'list_offices' ),
			'permitted'	=>	array ( 
								array ( 'office', 'new_blank_form' ),
								array ( 'office', 'id_search' ),
							),
			'mobile'	=>	false,
			'security'	=>	'super'
			),
		);
	
	
	

	private $unassigned_message = 
		'<div id="wic-unassigned-message">
			<h3>You requested a record to which you have not been assigned or a function to which your role does not give you access.</h3>
			<p>If you need access, consult your supervisor and request that the record be assigned to you or that your role be upgraded to a level that has access to unassigned constituents and issues and/or to more functions.</p>
			<p><strong>WP Issues CRM &raquo; Configure &raquo; Security</strong> determines which user roles have access to unassigned constituents and issues.</p>
		</div>';

	// set up menu and submenus with required capability levels defined from $nav_array -- WP is checking security on the page access
	public function show_main_menu () {  
		global $current_user;
		echo '<div id="wic-main-menu">';
			echo '<ul>'; 
				// add menu links
				$current_page = isset( $_GET['page'] ) ? $_GET['page'] : 'wp-issues-crm-main';
				foreach ( $this->nav_array as $page => $parms ) { 
					if ( ! $current_user->current_user_authorized_to($parms['security']  ) ) continue;
					echo '<li' . ( $page == $current_page ? ' class="selected-main-menu-item"' : '' ) . '>&raquo <a href="/?page=' . $page . '"> ' . $parms['name'] .  '</a></li>';
				}
				echo  '<li>&raquo <a href="/.auth/logout">Sign out ' . $current_user->get_display_name() . '</a></li>'; // azure signout link
			echo '</ul>';
		echo '</div>';
	}

	public  function title_legend() {
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$main_page = 'wp-issues-crm-main';
		$title = $this->nav_array[$main_page]['name'];
		$sub_title = isset ( $this->nav_array[$page] ) ? $this->nav_array[$page]['name'] : '';
		$title .= ( $page == $main_page ? '' : ( ' &raquo; ' . $sub_title ) );
		return $title;
	}


	// format a page from menu ( or direct $_GET in which case, invoke entity/action based on $_GET; if not permitted entity/action pair route to default )
	// wordpress is doing the security checking on a GET
	public function do_page (){ 
		global $current_user;
		// set page to main if coming in new
		$page 	= WIC_Admin_Setup::get_page();
		// handle bad page request
		$parms  = isset( $this->nav_array[$page] ) ? $this->nav_array[$page] :  $this->nav_array['wp-issues-crm-main'];

		// determine framing for responsive and non-responsive forms
		if ( ! $parms['mobile'] ) {
			$small_screen_plug 	= '<h3 id = "wp-issues-crm-small-screen-size-plug">' . $parms['name'] . ' not available on screens below 960px in width.' . '</h3>';
			$full_screen		= 'full-screen';
		} else {
			$small_screen_plug 	= '';
			$full_screen		= '';
		}

		// emit page
		echo $small_screen_plug;
		echo '<div class="wrap ' . $full_screen .'" id="wp-issues-crm" ><div id="wp-issues-crm-google-map-slot"></div>';
			echo '<h1 id="wic-main-header">' . $parms['name'] . '</h1>';
			// processing allowed get strings or defaults for pages
			if ( 0 < count ( $_GET ) || $page = 'wp-issues-crm-main' ) { // can have blank get on startup
				// if fully defined and OK string for page
				if ( isset ( $_GET['entity'] ) && isset ( $_GET['action'] ) && isset ( $_GET['id_requested'] )  ) {
					// filter allowed get actions. here is the page security check from $nav_array
					if ( in_array ( array ( $_GET['entity'] , $_GET['action'] ), $parms['permitted'] ) ) { 
						$class_short = $_GET['entity'];
						$action = $_GET['action'];
						$id = $_GET['id_requested'];
						$args   = array( 'id_requested' => $id ); // removed get path to user
					}
				} 
				// if not fully defined or not OK
				if ( !isset ( $class_short ) || !$class_short ) { 
					$class_short = $parms['default'][0];
					$action = $parms['default'][1];
					$args	= array(); 
					$id		= '';
				}

				// cosmetics
				if ( 'wp-issues-crm-main' == $page ) { 
					$this->show_top_menu ( $class_short, $action );
				} 
				$showing_email = '';
				if ( isset( $_GET['entity'] ) ) {
					if ( 'email_inbox' == $_GET['entity'] ) {
						$showing_email = 'showing-email';
					}
				}
				
				echo '<div id="wic-main-form-html-wrapper" class="' . $showing_email . '">';	
					// if user does not have this authorization for this type of page access, so advise 
					if ( ! $current_user->current_user_authorized_to( $parms['security'] ) ) {
						echo $this->unassigned_message;					
					// main action -- get page is OK (final check is on the )
					} else {	
						if ( $class_short ) {  
							if ( WIC_Admin_Access::check_security ( $class_short, $action, $id, '', false ) ) { // false means no nonce to check
								$class 	= 'WIC_Entity_' . $class_short; 
								$class_action = new $class ( $action, $args );
							} else {
								echo $this->unassigned_message;
							}
						} 
					}
				echo '</div>';
			}

		echo '</div>';
	} 

	// button navigation for wp-issues-crm-main page
	private function show_top_menu ( $class_requested, $action_requested ) {  

		echo '<form id = "wic-top-level-form" method="POST" autocomplete = "off" action="ajax.php">';
			// go to home
			$this->a2b ( 	array ( 'dashboard', 	'dashboard',	'<span class="dashicons dashicons-admin-home"></span>', 'Dashboard.', '', false ) );
			// search box
			$search_box = WIC_Control_Factory::make_a_control ( 'autocomplete' );
			$search_box->initialize_default_values(  'list', 'wic-main-search-box', '' );
			echo ( $search_box->form_control() );

			// go to email processing
			$this->a2b ( array ( 'email_inbox', 	'new_blank_form',	'<span class="dashicons dashicons-email-alt"></span>', 		'Process Email', 	'wic_email_access_button', false ) );		
			// go to map link
			$this->a2b ( array ( 'main_map',		'main_map',				'<span class="dashicons dashicons-location-alt"></span>', 	'Main Map',	 			'show_main_map_button', false ) );			
			// go to constituent add
			$this->a2b ( array ( 'constituent', 	'new_blank_form',	'<span class="dashicons dashicons-smiley"></span>' ,		'New Constituent', '', false ) ); // new
			// go to issue add
			$this->a2b ( array ( 'issue', 			'new_blank_form',	'<span class="dashicons dashicons-format-aside"></span>', 	'New Issue', 		'', false ) );
			// complex search
			$this->a2b ( array ( 'advanced_search', 'new_blank_form',	'<span class="dashicons dashicons-search"></span>', 		'Advanced Search', 	'', false ) );
			// go to uploads ( but wrap in div)
			echo '<div class="wic-upload-button-wrapper">';
			$this->a2b ( array ( 'upload', 			'new_blank_form',	'<span class="dashicons dashicons-upload"></span>', 		'Upload Files', 		'wic_upload_select_button', false ) );		
			echo '</div>';
			// go to documentation link
			$this->a2b ( array ( 'help', 			'link',				'<span class="dashicons dashicons-book"></span>', 	'Documentation/Contact',	 			'wic_manual_button', false ) );		
			// nonce field
			echo WIC_Admin_Setup::wic_nonce_field();
			// hidden button populated as needed by jQuery to post as a top form using the routine wpIssuesCRM.mainFormButtonPost 
			echo WIC_Form_Parent::create_wic_form_button( array ( 'id' => 'wic_hidden_top_level_form_button' ) );
			// hidden input control used to pass values for exports -- note that all submittable buttons in the top level form have name wic_form_button and so go through wpIssuesCRM.mainFormButtonPost
			// the download method is via a submit (so don't have to parse attachment headers out of an AJAX response ) -- do_download runs on every post it filters out wic_form_button
			$export_parameters = WIC_Control_Factory::make_a_control ( 'text' );
			$export_parameters->initialize_default_values(  'list', 'wic-export-parameters', '' );
			echo ( $export_parameters->form_control() );
		echo '</form>';		
	}

	// a2b (array to button) 
	private function a2b ( $top_menu_button ) {
		global $current_user;
		if ( !$current_user->current_user_authorized_to ('all_crm' ) ) {
			if ( in_array ( $top_menu_button[0], array ( 'issue', 'advanced_search', 'upload') ) ) {
				return false;
			}
		}
	
	
		$button_args = array (
				'entity_requested'		=>  $top_menu_button[0],
				'action_requested'		=>  $top_menu_button[1],
				'button_class'			=>  'wic-form-button wic-top-menu-button ', 	
				'button_label'			=>	$top_menu_button[2],
				'title'					=>	$top_menu_button[3],
				'id'					=>	$top_menu_button[4],
				'name'					=>  $top_menu_button[4] > '' &&  $top_menu_button[4] != 'wic_email_access_button'  ? $top_menu_button[4] : 'wic_form_button',
				'type'					=>	$top_menu_button[4] > '' ? 'button' : 'submit',
				'disabled'				=>	$top_menu_button[5],
			);
		echo WIC_Form_Parent::create_wic_form_button( $button_args );
	}


	/* routes request based on $_POST[action] */
	public function choose_ajax_router() {  
		// check if is a download (which looks like a form request)
		try{
			$this->do_download();
		} catch (Throwable $e ) {
			$stamp = date('Y-m-d h:i:s');
			error_log ( "Incident reported at $stamp: " . $e->__tostring() );
			legcrm_finish(
			"<div id='ajax-form-error'><h3>Sorry!  The server returned an error while attempting a download. </h3>  
			<p>We're on it.  We've logged this incident for review with the following timestamp: $stamp.</p>
			<p>Use the browser back button to return to your prior work.</div>"
			);
		}
		$ajax_routing_array = array(
			'wp_issues_crm_form'              =>'route_ajax_form',   // AJAX Form posts
			'wp_issues_crm'                   =>'route_ajax',        // AJAX Requests
			'wp_issues_crm_upload'            =>'route_ajax_upload', // PL Upload Requests
			'wp_issues_crm_document_upload'   =>'route_ajax_document_upload', // PL Upload Document Uploader 
			'wp_issues_crm_attachment_upload' =>'route_ajax_attachment_upload', // PL Upload Attachment Uploader
		 );
		 
		 if ( isset( $_POST['action'] ) && isset ( $ajax_routing_array[$_POST['action']] ) ) {
			$this->{$ajax_routing_array[$_POST['action']]}();
		 } else {
			legcrm_finish ( $this->unassigned_message );
		 }
	}


	/*
	*	AJAX Routing functions -- 
	*		FORM
	*		Plain request
	*
	*   Both are limited to Entity Classes
	*	Check security requires raised security for admin classes (identified in nav_array)
	*/

	private function route_ajax_form() {
	
		WIC_Admin_Setup::user_setup();

		$control_array = explode( ',', $_POST['wic_form_button'] );
		
		// define terms		
		$entity = $control_array[0];
		$action = $control_array[1];
		$id_requested = $control_array[2];
		$class 	= 'WIC_Entity_' . $entity;
		$args = array (
			'id_requested'			=>	$id_requested,
		);

		// check_security and legcrm_finish or do the request
		try {
			ob_start();
			if ( WIC_Admin_Access::check_security ( $entity, $action, $id_requested, '' ) ) {
				$new_entity = new $class ( $action, $args );
			} else {
				legcrm_finish( $this->unassigned_message ); // no further state processing -- leave client page unaltered
			}
			$output = ob_get_clean();
		}  catch ( Throwable $e ){
			$stamp = date('Y-m-d h:i:s');
			error_log ( "Incident reported at $stamp: " . $e->__tostring() );
			$output = "<div id='ajax-form-error'><h3>Sorry!  The server returned an error. </h3>  
				<p>We're on it.  We've logged this incident for review with the following timestamp: $stamp.</p></div>";
		}
		/*
		* response is a state instruction to the client
		*	return_type 	= full_form or error_only
		*	state_action	= push or replace
		*	state			= new URL to show_source
		*	state_data		= new form response to show
		*/

		// set up default push state instructions
		$response				= new StdClass();
		$response->return_type 	= 'full_form'; // not returning validation errors alone, but allowing for future;
		$response->state_action = 'pushState';
		$response->state_data  	= $output;
		$resource				= '&entity=' . $entity . '&action=' . $action . '&id_requested=' . $id_requested; 
		
		
		if ( isset($new_entity) ) { // no push adjustments in exception case
			// adjust the push state instructions for special cases
			if ( 'form_save_update' == $action ) {
				// do not change state on error messages
				if ( false === $new_entity->get_outcome() ) {
					$response->state_action = ''; 
				// on success shift resource to updated id search -- back goes to the result but not the pre-update form
				} else {
					$response->state_action = 'replaceState';
					$resource				= '&entity=' . $entity . '&action=id_search&id_requested=' . $new_entity->get_ID();
				}
			} elseif ( 'form_search' == $action ) {
				$resource 					= '&entity=search_log&action=id_search&id_requested=' . $new_entity->get_search_log_id ();
			}
		}
		// complete the push state url by adding in requesting page
		$requesting_page = false;
		foreach ( $this->nav_array as $page => $parms ) {
			foreach ( $parms['permitted'] as $permitted ) {
				if ( $entity == $permitted[0] ) {
					$requesting_page = $page;
					break(2);
				}
			}
		}
		if ( false === $requesting_page ) {
			$requesting_page = 'wp-issues-crm-main';
		} 
		$response->state = WIC_Admin_Setup::root_url() . '?page=' . $requesting_page . $resource;

		// send AJAX response
		legcrm_finish( json_encode ( $response ) );
	}


	private function route_ajax () { 

		/**
		*	
		*
		* on client side, sending:
		*	var postData = {
		*		action: 'wp_issues_crm', 
		*		wic_nonce: wic_ajax_object.wic_nonce,
		*		entity: entity,
		*		sub_action: action,
		*		id_requested: idRequested,
		*		wic_data: JSON.stringify( data )
		*		};
		*		 
		*/	
		$entity = $_POST['entity'];
		$class 	= 'WIC_Entity_' . $entity; 
		$method = $_POST['sub_action'];
		$id 	= $_POST['id_requested'];
		$data	= json_decode ( $_POST['wic_data'] ); 

		if ( !$this->is_json_already($entity,$method) ){
			// json returning methods do office/security check on db side
			WIC_Admin_Setup::user_setup();

			// check security 
			if ( !WIC_Admin_Access::check_security ( $entity, $method, $id, $data ) ) {
				legcrm_finish ( $this->unassigned_message );
			} 
		}

		try { 
			$method_response = $class::$method( $id, $data  );
			legcrm_finish (
				$this->is_json_already( $entity, $method ) ?
					$method_response :
					json_encode ( (object)  $method_response ) 
			);
		} catch (Throwable $e) {
			$stamp = date('Y-m-d h:i:s');
			error_log ( "Incident reported at $stamp: " . $e->__tostring() );
			legcrm_finish(
				json_encode(
					(object) array ( 
						'response_code' => false, 
						'output' => 
							"<p>Sorry!  We'll check it out. We've logged the error for review with the following time stamp: $stamp.</p>"
					)
				)
			);
		} 


	}	

	// these class methods do own security check on db side and return json
	private function is_json_already( $entity, $method ) { 
		$is_json_already = array(
			'search_box' 	=> array( 'search'),
			'autocomplete'	=> array( 'db_pass_through' ),
		);
		return isset( $is_json_already[$entity]) && in_array( $method,  $is_json_already[$entity] );
	}

	/* handler for calls from plupload */
	private function route_ajax_upload() {

		WIC_Admin_Setup::user_setup();


		if( !WIC_Admin_Access::check_security( 'upload','','','' ) ) {
			legcrm_finish ( $this->unassigned_message );
		} else {
			WIC_Entity_Upload_Upload::handle_upload();
		}
	}
	private function route_ajax_document_upload() {

		WIC_Admin_Setup::user_setup();

		
		// set values
		$constituent_id = isset ( $_REQUEST['constituent_id'] ) ? $_REQUEST['constituent_id'] : 0 ;
		$issue = isset ( $_REQUEST['issue'] ) ? $_REQUEST['issue'] : 0;
		
		if ( !WIC_Admin_Access::check_security ( $constituent_id ? 'constituent' : 'issue', 'id_search', $constituent_id ? $constituent_id : $issue , '' ) )	{
			legcrm_finish ( $this->unassigned_message );
		} else {
			WIC_Entity_Upload_Upload::handle_document_upload( $constituent_id, $issue );	
		}
	}
	private function route_ajax_attachment_upload() {

		WIC_Admin_Setup::user_setup();


		if ( !WIC_Admin_Access::check_security( 'email_send', 'update_draft','','' ) ) {
			legcrm_finish ( $this->unassigned_message );
		} else {
			$draft_id = $_REQUEST['draft_id']; 	
			WIC_Entity_Upload_Upload::handle_attachment_upload( $draft_id ); // may not know constituent	
		}
	}

	
	/*
	*
	* action to intercept press of download button before any headers sent 
	* 
	* intended to fire whenever top level form is posted, but not if posted (through wpIssuesCRM.mainFormButtonPost) with a wic_form_button button in $_POST
	* 
	* supports activity, constituent and document downloads; does not support email attachments which are supported by emit_stored file
	*/ 
	public function do_download () {  
	
		// don't fire if doing a routine button submission or if not actually the top level form 
		if ( isset ( $_POST['wic_form_button'] ) || ! isset ( $_POST['wic-export-parameters'] ) ) {
			return;
		}
		WIC_Admin_Setup::user_setup();
		// check access level to function
		$parameters = explode (  ',', $_POST['wic-export-parameters'] );
		$class 		= 'WIC_List_' . $parameters[0] . '_Export';
		$method 	= 'do_' 	  . $parameters[1] . '_download';
		$type		= $parameters[2];
		$id_data	= $parameters[3];

		if (! WIC_Admin_Access::check_security ( 'document' == $parameters[0] ? 'activity' : 'download', 'document', $id_data,'' ) ) { // will not see $method in check_security for download (no array)
			legcrm_finish( $this->unassigned_message ) ;
		} else {
			$class::$method ( $type, $id_data );
		}

 	}
	/*
	*
	* action to capture get requests for image and email attachment downloads
	* 
	*/
	public function emit_stored_file () {

		// check get string for applicability and completeness 
		if ( 
			!isset ( $_GET['page'] ) || 
			!( 'wp-issues-crm-main' == $_GET['page'] ) || 
			!isset( $_GET['entity'] ) || 
			!( 'email_attachment' == $_GET['entity'] ) || 
			!isset( $_GET['attachment_id'] ) || 
			!isset ( $_GET['message_id'] ) 
			) {   
			return;
		}

		$message_in_outbox = isset( $_GET['message_in_outbox'] ) ? $_GET['message_in_outbox'] : 0;

		if (! WIC_Admin_Access::check_security ( 'email_message','load_full_message', $_GET['message_id'], $message_in_outbox ) ) {
			legcrm_finish( $this->unassigned_message ) ;
		} else {
			WIC_Entity_Email_Attachment::emit_stored_file ( $_GET['attachment_id'],  $_GET['message_id'], $message_in_outbox );
		}
	
				
	}

	

}