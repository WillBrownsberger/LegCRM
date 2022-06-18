<?php
/**
*
* class-wic-admin-setup.php
*
* outputs scripts (including local variables) and styles into header; includes nonce definition 
*
*/

class WIC_Admin_Setup {

	private $legCRM_version;

	public static function http_mode() {
		return SITE_USING_SSL ? "https://" : "http://";
	}

	public static function root_url() {
		return self::http_mode() . SITE_DOMAIN . '/'; 
	}

	public static function get_page() {
		return (isset( $_GET['page'] ) && $_GET['page'] )  ? $_GET['page'] : 'wp-issues-crm-main';
	}
	

	// instead of registering actions, doing actions
	public function __construct() {  

		// force js and style refresh when developing
		$this->legCRM_version = defined( 'LEGCRM_VERSION' ) ? LEGCRM_VERSION : time();

		// load styles
		self::load_wic_styles();
		self::load_legacy_wordpress_styles();

		//	load scripts 
		self::load_script_variables();
		self::load_wic_scripts();
		
	}

	private function load_wic_styles() {


		// enqueue wp issues crm styles
		$page_css_map = array(
			'wp-issues-crm-main' => array ( 
				'activity', 
				'advanced-search',
				'buttons',
				'constituent',
				'dashboard', 
				'email',
				'google-maps',
				'issue', 
				'list',
				'main',
				'multi-email',
				'search', 
				'selectmenu',
				'upload',
			),
			'office-list' => array ( 
				'buttons',
				'office', 
				'list',
				'main', 
				'selectmenu',
			),
		);

		$page = WIC_Admin_Setup::get_page();

		/* echo header lines for wic styles */
		foreach ( $page_css_map[$page] as $style) {
			echo '<link rel="stylesheet" href="' . self::root_url() . 'css/' . $style  . '.css?ver=' . $this->legCRM_version . '">' . "\n";
		} 

		// add themeroller custom jquery ui style;
		echo  '<link rel="stylesheet" href="' . self::root_url() . 'css/jquery-ui-1.11.4.custom/jquery-ui.min.css">' . "\n";

	}

	private function load_legacy_wordpress_styles(){
		// legacy wordpress styles
		$legacy_wordpress_admin_styles = array(
			'common',
			'forms',
		//	'admin-menu',
		//	'dashboard',
		//	'list-tables',
		//	'nav-menus',
			'dashicons',
			'buttons',
		);

		/* echo header lines for styles */
		foreach ( $legacy_wordpress_admin_styles as $style ) {
			echo '<link rel="stylesheet" href="' . self::root_url() . 'css/wp/' . $style  . '.min.css?ver=' . $this->legCRM_version . '">' . "\n";
		} 

	}

	// load scripts and styles
	private function load_wic_scripts () {
	
		/* load jquery and jquery ui -- note that later versions of jquery (and poss jquery ui) involve breaking changes; 
		// wordpress a/o 2109 still using jquery 1.12.4*/
		echo '<script src="' . self::root_url() . 'js/jquery-1.12.4.min.js"></script>' . "\n";
		echo '<script src="' . self::root_url() . 'js/jquery-ui-1.11.4/jquery-ui.min.js"></script>' . "\n";
			
		/* load plupload */
		echo '<script src="' . self::root_url() . 'js/plupload/moxie.min.js?ver=1.3.5"></script>' . "\n";
		echo '<script src="' . self::root_url() . 'js/plupload/plupload.min.js?ver=1.3.5"></script>' . "\n";
		
		/*
		*	each javascript module loaded for main contains own document.ready adding delegated listeners to a single container
		*  	+ no modules use the public javascript namespace for any variables
		*	+ all used in main build on object wpIssuesCRM; non-main use anonymous namespaces
		*	+ always load all scripts even though not all necessary on the occasional pages
		*/
		$wic_scripts = array ( 
		
		);
		$page_modules_map = array(
			'wp-issues-crm-main' => array ( 
				'activity', 
				'advanced-search',
				'ajax', 
				'autocomplete', 
				'constituent', 
				'dashboard',
				'email-deliver',
				'email-blocks', 
				'email-inbox', 
				'email-message',
				'email-process',
				'email-send',
				'email-settings',
				'email-subject',
				'google-maps',					
				'help',
				'issue', 
				'main',
				'map-actions',
				'shape-transfers',
				'multi-email',
				'multivalue',
				'search', 
				'search-log',
				'selectmenu', 
				'upload-complete',
				'upload-details',
				'upload-download',
				'upload-map',
				'upload-match',
				'upload-regrets', 
				'upload-set-defaults',
				'upload-upload',
				'upload-validate', 
			),
			'office-list' => array ( 
				'ajax',
				'office',
				'multivalue',
				'main',
				'selectmenu', 
			),
		);

		/* echo header lines for scripts */
		$page = WIC_Admin_Setup::get_page(); 
		foreach ( $page_modules_map[$page] as $module ) {
			echo '<script src="' . self::root_url() . 'js/' . $module  . '.js?ver=' . $this->legCRM_version . '"></script>' . "\n";
		} 

		/* load tiny mce */
		echo '<script src="' . self::root_url() . 'js/tinymce/js/tinymce/tinymce.min.js?ver=' . $this->legCRM_version . '"></script>' . "\n";


	}

	private function load_script_variables() {
		global $current_user;	
		// initialize array of variables to pass to javascript
		$script_variables = array (
			'wic_ajax_object' =>
				array( 
					'ajax_url' 			=> self::root_url() . 'ajax.php',
					'wic_nonce' 		=> self::wic_create_nonce (), 
				),
		);
	
		// add option array to variables array
		$script_variables['wpIssuesCRMSettings'] = 
			array( 
				'financialCodesArray'	=> WIC_Entity_Activity::$financial_types,
				'maxFileSize' 			=> WIC_Entity_Upload_Upload::get_safe_file_size(),
				'dearToken' 			=> WIC_Entity_Email_Send::dear_token,
				'canViewAllEmail' 		=> $current_user->current_user_authorized_to ('all_email'),
				'canSendEmail' 			=> $current_user->current_user_authorized_to ('all_email'),
				'canViewOthersAssigned'	=> $current_user->current_user_authorized_to ('all_crm'),
			); 
					

		// add loader for repeated use to variables array
		$script_variables['wpIssuesCRMLoader'] =
			'<div class = "wic-loader-image">' .
				'<em>Loading . . .  </em>
				<img src="' . self::root_url() . 'img/ajax-loader.gif">' . 
			'</div>'; 
		
		// out put the special variables array -- all will be added to global scope
		foreach ( $script_variables as $var => $assigned_value ) {
			echo '<script>' . "\n" . 'var ' . $var . ' = ' . json_encode ( $assigned_value ) . ";\n</script>\n";
		}
		
	} // close load_script_variables


	// user setup includes checking that Azure authentication is in place
	public static function user_setup () {
		// set up user
 		global $current_user;
		if ( empty ( $current_user ) ) {
	 		$current_user = new WIC_Entity_User ( 'load', array());
		} 
		if ( ! $current_user->get_id() ) 	 {
			legcrm_finish (  
				'<div id="wic-unassigned-message">
					<h3>Based on your logged in email address, '. $_SERVER['REMOTE_USER'] . ', you have been permitted to reach the legislative CRM, but you have not been set up as a user in the CRM.</h3>
					<p>If you need access, consult your supervisor and request to be set up.</p>
					<p>If you are sure you are already set up and this response persists when you retry after a minute or two, contact information services. The response may be due to a database misconfiguration or outage.</p>
				</div>'
			);
		}
	}


	// navigation essential for auth checking 
	public static function navigation_setup () {
		// set up nav
 		global $wic_admin_navigation;
		if ( empty ( $wic_admin_navigation ) ) {
	 		$wic_admin_navigation = new WIC_Admin_Navigation;
	 	} 	
	}


	/*
	* create "nonce" for form and request embedding that covers all actions and lasts the duration of the current AZURE session 
	*	only usable by the same session, so time limited.
	*/
	public static function wic_create_nonce( $attachment_id=false ) {
		
		// check for nonce key 
		if ( !defined('NONCE_KEY') || !NONCE_KEY ) {
			legcrm_finish( 'NONCE_KEY Undefined -- contact system administrator to complete configuration' );
		}

		if ( defined('OVERRIDE_AZURE_SECURITY_FOR_TESTING') && OVERRIDE_AZURE_SECURITY_FOR_TESTING ){
			return 'NONCE123';
		}

		// session and user variables tested at startup
		return substr( hash_hmac( 'md5', $_COOKIE['AppServiceAuthSession'] . $_SERVER['REMOTE_USER'] . ( $attachment_id ? $attachment_id : ''),  NONCE_KEY ), -25, 20 );

	}

	public static function wic_check_nonce( $attachment_id = false ) {

		$nonce =  isset($_REQUEST['wic_nonce']) ? $_REQUEST['wic_nonce'] : 'NONONCE';
		if ( defined('OVERRIDE_AZURE_SECURITY_FOR_TESTING') && OVERRIDE_AZURE_SECURITY_FOR_TESTING ){
			return $nonce == 'NONCE123';
		}

		return (hash_equals( $nonce, self::wic_create_nonce ( $attachment_id ) ) ); // hash_equals tests string equality without betraying time to find first difference
	
	}

	// always include WIC_Admin_Setup::wic_nonce_field
	public static function wic_nonce_field() {
		$nonce_field = '<input type="hidden" id="wic_nonce" name="wic_nonce" value="' . self::wic_create_nonce() . '" />';
		return $nonce_field;
	}
// close class WIC_Admin_Setup	 
}