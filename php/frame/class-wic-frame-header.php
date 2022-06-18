<?php
/*
*
* assemble header elements
*
*/
Class WIC_Frame_Header {

	public function __construct() {

		global $wic_admin_navigation;
		echo '
			<!DOCTYPE html>
			<html lang="en-US">
				<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<title>' . $wic_admin_navigation->title_legend() . '</title>';

				$wic_admin_setup = new WIC_Admin_Setup();

				echo '</head>';
	}
}