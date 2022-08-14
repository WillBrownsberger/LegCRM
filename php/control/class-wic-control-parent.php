<?php
/*
* class-wic-control-parent.php
*
* WIC_Control_Parent is extended by classes for each of the field types  
* 
* Multivalue is the most significant extension -- from the perspective of the top form,
* a multivalue field like address (which includes multiple rows with multiple fields in each)
* is just another control like first name.  
*
*
*
*/

/****
*
*  WIC Control Parent
*
****/
abstract class WIC_Control_Parent {
	//$field value
	protected $value;
	// control properties at the control level
	// field grouping and ordering for forms live in the forms objects
	// formatting and sorting rules live in the list objects
	protected $entity_slug 	= '';
	protected $field_slug  	= '';
	protected $field_slug_base; // invariant as control slug is further qualified in multivalue situations
	protected $field_type; 		// this is control class always set on initialization
	protected $type 		= 'text'; // this is the value used to determine the html type -- defaults set to text, but may be overriden by subclasses
	protected $field_label 	= '';
	protected $required	   	= '';
	protected $dedup		= 0;	
	protected $readonly		= 0;
	protected $hidden		= 0;
	protected $field_default= '';
	protected $transient	= 0;
	protected $length 		= 200;
	protected $placeholder	= '';
	protected $option_group	= '';
	protected $input_class  = 'wic-input';
	protected $label_class  = 'wic-label';
	protected $field_slug_search = '';
	protected $entity_slug_search = '';
	/***
	*
	*		Field child classes may override validation, sanitization
	*		Any field specific validation, sanitization, formatting ( and default ) are supplied from the relevant object
	*			Functions of the form WIC_Entity_{entity}::{field_slug_base} . {one of the following  _formatter, _sanitizor, _validator}
	*
	*/

	// initialize object properties
	public function initialize_default_values ( $entity, $field_slug, $instance ) {
		$this->entity_slug = $entity;
		// note that the entity name for a row object in a multivalue field is the same as the field_slug for the multivalue field
		// if this is an instance set slug up for it to be handled as a subarray in $_POST
		$this->field_slug_base = $field_slug;
		// Note the following line is OK in 8.0 because $instance is always a string -- SEE WIC_Control_Multivalue:set_value
		$this->field_slug  = '' == $instance ? $field_slug : $entity . '[' . $instance . ']['. $field_slug . ']'; 
		$this->field_slug_css = str_replace( '_', '-', $field_slug );
		$this->field_slug_search = $field_slug; // copy modifiable for advanced search field swapping
		$this->entity_slug_search = $entity;// copy modifiable for advanced search field swapping
		$this->field_slug_update = $field_slug; // copy invariant from initial load for use in update
		// get field properties from owning entity
		$entity_class = 'WIC_Entity_' . $entity;
		$entity_dictionary = $entity_class::get_entity_dictionary();
		foreach ( $entity_dictionary[$field_slug] as $key => $value ) {
			$this->$key = $value;
		}
		// initialize the value of the control ( if form is non-blank, value will be further set )
		if ( $this->field_default > '' ) {
			$this->value = $this->field_default; 
		} else {
			$this->reset_value(); // may reset as an array
		}
	}

	/******
	*
	* setters_getters
	*
	***/
	
	public function set_value ( $value ) {
		$this->value = $value;	
	}
	
	// display: none;
	public function set_input_class_to_hide_element() { 
		$this->input_class .= ' hidden-element ';
		$this->label_class .= ' hidden-element ';
	}	

	// visibility:hidden -- hidden,but takes space
	public function set_input_class_to_make_element_invisible() {
		$this->input_class .= ' invisible-element ';
		$this->label_class .= ' invisible-element ';
	}

	public function override_readonly( $true_or_false ) {
		$this->readonly = $true_or_false;	
	}

	public function get_value () {
		return $this->value;	
	}
	
	public function reset_value() {
		$this->value = '';	
	}

	public function get_label() {
		return ( $this->field_label );	
	}

	public function get_control_type() {
		return ( $this->field_type );	
	}	

	public function override_create_type( $type ) {
		$this->type = $type;
	}

	public function get_field_slug() {
		return ( $this->field_slug );	
	}
	
	public function is_read_only () {
		return ( $this->readonly );	
	}
	

	

	// used in advanced search when must create control with entity/field rules, but give it a new identity as a row element (do after init);
	public function set_default_control_slugs ( $entity, $field_slug, $instance ) {
		$this->field_slug = $entity . '[' . $instance . ']['. $field_slug . ']';
		$this->field_slug_css =  str_replace( '_', '-', $field_slug ) . ' ' . $this->field_slug_css;
		$this->field_slug_search 	= $field_slug; 
		$this->entity_slug_search 	= $entity;

	}
	public function set_default_control_label ( $label ) {
		$this->field_label = $label;
	}

	/******
	*
	* methods for basic forms -- single control type, since not working around readonly on search forms
	*
	***/

	public function form_control () { 
		return ( static::create_control( get_object_vars ( $this ) )  );	
	}

	protected static function create_control ( $control_args ) { // basic create text control, accessed through control methodsabove

		extract ( $control_args, EXTR_OVERWRITE );  
		
		$value = ( '0000-00-00' == $value ) ? '' : $value; // don't show date fields with non values; 
		
		 $class_name = 'WIC_Entity_' . $entity_slug; 
		 $formatter  = $field_slug_base . '_formatter';
		if ( method_exists ( $class_name, $formatter ) ) { 
			$value = $class_name::$formatter ( $value );
		} elseif ( function_exists ( $formatter ) ) {
			$value = $formatter ( $value );		
		}

		$readonly = $readonly ? ' readonly ' : '';

		// allow extensions to set field type, but if hidden, is hidden		
		$type = ( 1 == $hidden ) ? 'hidden' : $type;  // $type, as opposed to $field_type
		 
		$control = ( $field_label > '' && ! ( 1 == $hidden ) ) ? '<label class="' . safe_html ( $label_class ) .
				 ' ' . safe_html( $field_slug_css ) . '" for="' . safe_html( $field_slug ) . '">' . safe_html( $field_label ) . '</label>' : '' ;
		$control .= '<input autocomplete="NoThankYou" class="' . safe_html( $input_class ) . ' ' .  safe_html( $field_slug_css ) . '" id="' . safe_html( $field_slug )  . 
			'" name="' . safe_html( $field_slug ) . '" type="' . $type . '" maxlength="' . $length . '" placeholder = "' .
			 safe_html( $placeholder ) . '" value="' . safe_html ( $value ) . '" ' . $readonly  . '/>'; 
			
		return ( $control );

	}


	/*****
	*
	* control sanitize -- will handle all including multiple values -- generic case is string
	*
	*/
	public function sanitize() {  
		$class_name = 'WIC_Entity_' . $this->entity_slug;
		$sanitizor = $this->field_slug_base . '_sanitizor';
		if ( method_exists ( $class_name, $sanitizor ) ) { 
			$this->value = $class_name::$sanitizor ( $this->value );
		} else { 
			// default hard sanitize to word characters and apostrophes, spaces, colon, #, forward slash and hyphens
			$this->value = preg_replace( '~[^-/&:# \'\w]~', '', $this->value );		
		} 
	}

	/******
	*
	* control validate -- will handle all including multiple values -- generic case is string
	* here, rather than directly in entity to support multiple values
	*
	******/
	public function validate() { 
		$validation_error = '';
		$class_name = 'WIC_Entity_' . $this->entity_slug;
		$validator = $this->field_slug_base . '_validator';
		if ( method_exists ( $class_name, $validator ) ) { 
			$validation_error = $class_name::$validator ( $this->value );
		}
		return $validation_error;
	}

	/******
	*
	* report whether field should be included in deduping.
	*
	******/
	public function dup_check() {
		return $this->dedup;	
	}
	/******
	*
	* report whether field is transient
	*
	******/
	public function is_transient() {
		return ( $this->transient );	
	}
	/******
	*
	* report whether field is multivalue
	*
	******/
	public function is_multivalue() {
		return ( $this->field_type == 'multivalue' );	
	}
	/******
	*
	* report whether field fails individual requirement
	*
	******/
	public function required_check() { 
		if ( "individual" == $this->required && ! $this->is_present() ) {
			return ( sprintf ( ' %s is required. ', $this->field_label ) ) ;		
		} else {
			return '';		
		}	
	}

	/******
	*
	* report whether field is present as possibly required -- note that is not trivial for multivalued
	*
	******/
	public function is_present() {
		$present = ( '' < $this->value && !is_null( $this->value )); 
		return $present;		
	}
	
	/******
	*
	* report whether field is required in a group 
	*
	******/
	public function is_group_required() {
		$group_required = ( 'group' ==  $this->required ); 
		return $group_required;		
	}


	/******
	*
	* create where/join clause components for control elements in generic wp form 
	*
	******/
	public function create_search_clause () {
		
		// values passed to this appear to be all strings, so no 8.0 issue.
		if ( '' == $this->value || 1 == $this->transient ) {
			return ('');		
		}

		$query_clause =  array ( // double layer array to standardize a return that allows multivalue fields
				array (
					'table'	=> $this->entity_slug_search,
					'key' 	=> $this->field_slug_search,
					'value'	=> $this->value,
					'compare'=> '=',
				)
			);
		
		return ( $query_clause );
	}
	
	/******
	*
	* create set array or sql statements for saves/updates 
	*
	******/
	public function create_update_clause () { 
		if ( 
			( ( ! $this->transient ) && ( ! $this->readonly ) ) 
			|| 'ID' == $this->field_slug 
			 ) {
			// exclude transient and readonly fields.   ID as readonly ( to allow search by ID), but need to pass it anyway.
			// ID is a where condition on an update in WIC_DB_Access_WIC::db_update
			$update_clause = array (
					'key' 	=> $this->field_slug_update,
					'value'	=> $this->value,
			);
			
			return ( $update_clause );
		}
	}


	
}
