<?php
/*
*
* assemble frame elements
*
*/

class WIC_Frame_Body {

    public function __construct() {

        global $wic_admin_navigation;
        echo '<body><div id="wic-total-wrap">';
            $wic_admin_navigation->show_main_menu();     
            $wic_admin_navigation->do_page();
        echo '</div></body>';
    }
}