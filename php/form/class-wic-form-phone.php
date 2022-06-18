<?php
/*
*
*  class-wic-form-email.php
*
*/

class WIC_Form_Phone extends WIC_Form_Multivalue  {

    public static $form_groups = array(
        'phone_row'=> array(
           'group_label' => 'Phone Row',
           'group_legend' => '',
           'initial_open' => '1',
           'sidebar_location' => '0',
           'fields' => array('ID','screen_deleted','is_changed','constituent_id','phone_type','phone_number','extension','OFFICE')),
    );
}