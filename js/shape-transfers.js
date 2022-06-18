/*
*
*	shape-transfers.js 
*
*	handles conversions back and forth from array saved as json on server to live features in the map
*
*/
// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	// convert array of shape descriptions back to live map features and cache them
	wpIssuesCRM.loadSTA = function( shapeTransferArray ) {

		var feature;

		// reverse the create process, but need to be actually creating map objects as well as cache elements
		shapeCount = shapeTransferArray.length;
		if ( shapeCount ) {
			for (var j = 0; j < shapeCount; j++) { 
				feature = {};
				feature.type = shapeTransferArray[j].type
				if ( shapeTransferArray[j].type == 'polygon' ) {
					feature.overlay = new google.maps.Polygon();
					feature.overlay.setPath ( shapeTransferArray[j].geometry.path )
				} else if ( shapeTransferArray[j].type == 'circle' ) {
					feature.overlay = new google.maps.Circle();
					feature.overlay.setCenter ( shapeTransferArray[j].geometry.center );
					feature.overlay.setRadius ( shapeTransferArray[j].geometry.radius ); 
				} else if ( shapeTransferArray[j].type == 'rectangle' ) {
					feature.overlay = new google.maps.Rectangle
					feature.overlay.setBounds ( shapeTransferArray[j].geometry );
				}
				feature.overlay.setOptions ( wpIssuesCRM.stdOptions );
				feature.overlay.setMap( wpIssuesCRM.map );
				wpIssuesCRM.addEditListeners( feature )
				wpIssuesCRM.shapeCache.push ( feature );
			
			}
			// with shape array reloaded as features, do the markers -- make sure all map controls are loaded first
			google.maps.event.addListenerOnce( wpIssuesCRM.map, 'idle', function() {
				wpIssuesCRM.idIncludedMarkers();
			});
		} 
	}	
	
	
	// convert features on map to descriptions and save on server
	wpIssuesCRM.saveShapes = function () {

		// disable action controls 
		document.getElementById('wic-google-select-controls-list').removeEventListener( "click", wpIssuesCRM.selectControlCallBacks );

		var shapeTransferArray = [];
		var transferObject = {}
		
		shapeCount = wpIssuesCRM.shapeCache.length;
		if ( shapeCount ) {
			for (var j = 0; j < shapeCount; j++) {
				transferObject = {}
				transferObject.type =  wpIssuesCRM.shapeCache[j].type;
				if ( wpIssuesCRM.shapeCache[j].type == 'polygon' ) {
					var pathArray = [];
					wpIssuesCRM.shapeCache[j].overlay.getPath().forEach( function ( element, index ){
						pathArray[index] = { 'lat' : element.lat(), 'lng': element.lng()}
					});
					transferObject.geometry = { 
						'path': pathArray, 
					}
				} else if ( wpIssuesCRM.shapeCache[j].type == 'circle' ) {
					transferObject.geometry = { 
						'center': wpIssuesCRM.shapeCache[j].overlay.getCenter(), 
						'radius': wpIssuesCRM.shapeCache[j].overlay.getRadius()
					}
				} else if ( wpIssuesCRM.shapeCache[j].type == 'rectangle' ) {
					var bounds = wpIssuesCRM.shapeCache[j].overlay.getBounds();
					transferObject.geometry = {
						'north' : bounds.getNorthEast().lat(),
						'east' 	: bounds.getNorthEast().lng(),
						'south' : bounds.getSouthWest().lat(),
						'west' 	: bounds.getSouthWest().lng()
					}
				}
				shapeTransferArray.push ( transferObject );
			}
		} 
		wpIssuesCRM.ajaxPost( 'geocode', 'save_shapes',  wpIssuesCRM.mapRequest, shapeTransferArray,  function( response ) {
			document.getElementById('wic-google-select-controls-list').addEventListener( "click", wpIssuesCRM.selectControlCallBacks );
		});	  

	}

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	