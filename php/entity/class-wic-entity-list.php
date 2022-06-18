<?php
/*
*
*	wic-entity-list.php
*
*  this is a shell to allow definition of controls for lists
*/

class WIC_Entity_List extends WIC_Entity_Parent {

	protected function set_entity_parms( $args ) { // 
		// accepts args to comply with abstract function definition, but as a parent does not process them -- no instance
		$this->entity = 'list';
	} 

	protected static $entity_dictionary = array(
		'found_count'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'list_page_offset'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'retrieve_limit'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'search_id'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'share_name'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '0',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Enter a name to share this search . . .',
			'option_group' =>  '',),
		'wic-export-parameters'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '1',
			'field_type' =>  'text',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '1',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  '',),
		'wic-main-search-box'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '0',
			'field_type' =>  'autocomplete',
			'field_label' =>  '',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  'Quick search for constituents and issues',
			'option_group' =>  '',),
		'wic-post-export-button'=> array(
			'entity_slug' =>  'list',
			'hidden' =>  '0',
			'field_type' =>  'select',
			'field_label' =>  'Type',
			'required' =>  '',
			'dedup' =>  '0',
			'readonly' =>  '0',
			'field_default' => '',
			'transient' =>  '0',
			'placeholder' =>  '',
			'option_group' =>  'download_options',),

	);


	public static $option_groups = array(
		'download_options'=> array(
		  array('value'=>'emails','label'=>'Export Emails',),
			  array('value'=>'','label'=>'Export',),
			  array('value'=>'addresses','label'=>'Export Addresses',),
			  array('value'=>'phones','label'=>'Export Phones',),
			  array('value'=>'dump','label'=>'Export Dump',)),
	   
	  );
}