<?php
/*
*
* load elements for $_GET (index.php)  ($_POST is more selective through AJAX routing in WIC_Admin_Navigation)
*
*/

// load config and define autoloader
include WWWROOT ."legcrm-config.php";

// global functions
require_once WWWROOT . 'php/function/global_functions.php';

// set up database object
global $sqlsrv;
$sqlsrv = new WIC_DB_SQLSRV();

// load current user and user array
WIC_Admin_Setup::user_setup();

// set up navigation 
WIC_Admin_Setup::navigation_setup();



