<?php
/*
*
* entry point for GET requests;
*
*/

// Absolute path to the root directory. 
define( 'WWWROOT', __DIR__ . '\\' );

// main load
require( WWWROOT . 'php\\admin\\load.php');

// before emitting headers, see if this is a stored file request
global $wic_admin_navigation;
$wic_admin_navigation->emit_stored_file();

// load header, loading scripts and styles
$header = new WIC_Frame_Header();

// load body (two parts: menu and working page)
$body = new WIC_Frame_Body(); // runs through wic_admin_navigation for security

// close the connection when done
legcrm_finish();