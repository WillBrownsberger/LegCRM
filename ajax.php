<?php
/*
*
* Entry point for $_POST calls -- AJAX only: a $_POST to index PHP will be treated as a blank $_GET
*    
*/

// Absolute path to the root directory. 
define( 'WWWROOT', __DIR__ . '/' );
// load config and define autoloader
include WWWROOT ."legcrm-config.php";

// load global functions
require_once WWWROOT . 'php/function/global_functions.php';

// set up navigation 
WIC_Admin_Setup::navigation_setup();

// set up database connection
global $sqlsrv;
$sqlsrv = new WIC_DB_SQLSRV();

// follow $POST['action'] for allowed ajax actions
global $wic_admin_navigation;
$wic_admin_navigation->choose_ajax_router();

// user, settings not loaded from db unless needed

// close the connection when done 
// should not be able to reach this on ajax call -- should have died already
legcrm_finish();





