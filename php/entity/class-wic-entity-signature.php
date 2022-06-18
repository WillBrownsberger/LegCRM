<?php
/*
*
*	wic-entity-signature.php
*
*  this is a shell to allow definition of control for signature subform
*/

class WIC_Entity_signature extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'signature';
	} 

	protected static $entity_dictionary = array(
        'signature'=> array(
            'entity_slug' =>  'user',
            'hidden' =>  '0',
            'field_type' =>  'textarea',
            'field_label' =>  '',
            'required' =>  '',
            'dedup' =>  '0',
            'readonly' =>  '0',
            'field_default' => '',
            'transient' =>  '0',
            'placeholder' =>  '',
            'option_group' =>  '',),
	);


	public static $option_groups = array(
   
    );
}