/*
*
*	multivalue.js -- handles row add and delete buttons for multivalue fields
*
*	triggers any handlers serving adds and deletes for particular row types
*
*/
jQuery( document ).ready( function($) { 

	$( "#wp-issues-crm" ).on( "click", ".row-add-button", function ( event ) { 
		var nextID = $( this ).next().attr("id")
		base = nextID.substring(0, nextID.indexOf( '[' ) );
		wpIssuesCRM.moreFields ( base );
	})
	
	.on( "click", ".wic-input-deleted", function ( event ) {
		var parentID = $( this ).parents( ".visible-templated-row" ).attr("id");
		wpIssuesCRM.hideSelf ( parentID );
	});
});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	// add new visible rows by copying hidden template for multi value fields
	wpIssuesCRM.moreFields = function ( base ) {

		// counter always unique since gets incremented on add, but not decremented on delete
		var counter = document.getElementById( base + '-row-counter' ).innerHTML;
		counter++;
		document.getElementById( base + '-row-counter' ).innerHTML = counter;
	
		var newFields = document.getElementById( base + '[row-template]' ).cloneNode(true);
	
		/* set up row paragraph with  id and class */
		newFields.id = base + '[' + counter + ']' ;
		newFieldsClass = newFields.className; 
		newFields.className = newFieldsClass.replace('hidden-template', 'visible-templated-row') ;

		/* walk child nodes of template and insert current counter value as index*/
		replaceInDescendants ( newFields, 'row-template', counter, base);	

		/* insert the row with all controls having correct attributes */
		var insertBase = document.getElementById( base + '[row-template]' );
		var insertHere = insertBase.nextSibling;
		insertHere.parentNode.insertBefore( newFields, insertHere );
		
		/* execute any new row handles for the row type */
		$( newFields ).trigger ( "addedWICRow" ); 

	}

	// supports moreFields by walking node tree for whole multi-value group to copy in new name/ID values
	function replaceInDescendants ( template, oldValue, newValue, base  ) {
		var newField = template.childNodes;
		var newFieldLength = newField.length;
		if ( newFieldLength > 0 ) {
			for ( var i = 0; i < newFieldLength; i++ ) {
				var theName = newField[i].name;
				if ( undefined != theName) {
					newField[i].name = theName.replace( oldValue, newValue );
				}
				var theID = newField[i].id;
				if ( undefined != theID)  {
					newField[i].id = theID.replace( oldValue, newValue );
				} 
				var theFor = newField[i].htmlFor;
				if ( undefined != theFor)  {
					newField[i].htmlFor = theFor.replace( oldValue, newValue );
				} 
				replaceInDescendants ( newField[i], oldValue, newValue, base )
			}
		}
	}

	// screen delete rows in multivalue fields
	 wpIssuesCRM.hideSelf = function( rowname ) {

	 	// change the class to hide the row
		var row = document.getElementById ( rowname );
		rowClass =row.className; 
		row.className = rowClass.replace( 'visible-templated-row', 'hidden-template' ) ;

		// trigger any handlers for the deleted row
		$( row ).trigger ( "deletedWICRow" )
	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
