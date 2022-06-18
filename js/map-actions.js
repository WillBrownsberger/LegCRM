/*
*
*	map-actions.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 


});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	// timer for delay in idIncludedMarkers invocation
	var shapeTimer, markerTimer;
	// status counters
	wpIssuesCRM.pointsIncluded = 0; 
	wpIssuesCRM.constituentsIncluded = 0; 
	wpIssuesCRM.countConstituentsWICGoogle; // will be used as counter display div
	// cache of drawn shapes 
	wpIssuesCRM.shapeCache = [];
	// cache of constituent markers
	wpIssuesCRM.markerCache = [];
	
	/*
	*
	* create a MarkerCacheElement class
	*
	*/
	wpIssuesCRM.MarkerCacheElement = function ( marker, weight, isSelected ) {
		
		// to refer back to as variable
		var element = this;
		
		var elMarker, elWeight, elConstituentIDs;
		
		// set values on construct
		element.elMarker = marker;
		element.elWeight = weight;
		element.elIsSelected = isSelected;
		
	}
	// set up properties of the new markerCacheElement object
	wpIssuesCRM.MarkerCacheElement.prototype.elMarker = null;
	wpIssuesCRM.MarkerCacheElement.prototype.elWeight = null;
	wpIssuesCRM.MarkerCacheElement.prototype.elIsSelected = null;
	// set up getters of properties of the new markerCacheElement object
	wpIssuesCRM.MarkerCacheElement.prototype.getMarker = function() {
		return this.elMarker;
	};
	wpIssuesCRM.MarkerCacheElement.prototype.getIsSelected = function() {
		return this.elIsSelected;
	};
	wpIssuesCRM.MarkerCacheElement.prototype.getWeight = function() {
		return this.elWeight;
	};
	// setter 
	wpIssuesCRM.MarkerCacheElement.prototype.setIsSelected = function( isSelected ) {
		this.elIsSelected = isSelected;
		var iconType = isSelected ? 'S' : 'U';
		this.elMarker.setIcon ( wpIssuesCRM.icons[iconType] );
	};
	
	/*
	*
	*
	* button action functions
	*
	*
	*/
	wpIssuesCRM.sendMailMap = function () {
		if ( !wpIssuesCRM.constituentsIncluded ) {
			wpIssuesCRM.alert ( 'No constituents selected to mail to -- draw shapes to select.')
			return;
		}
		var tempInput = document.createElement('input');
		tempInput.value = 
			wpIssuesCRM.mapRequest.context + '_email_send,' +
			wpIssuesCRM.mapRequest.id + ',' +
			wpIssuesCRM.constituentsIncluded;
		wpIssuesCRM.handleComposeButton( 
			{
				target: tempInput
			}
		);
	}
	
	wpIssuesCRM.downloadListMap = function () {
		if ( !wpIssuesCRM.constituentsIncluded ) {
			wpIssuesCRM.alert ( 'No constituents selected for download -- draw shapes to select.')
			return;
		}
		var exportParameters;
		exportParameters = 'constituent,constituent,' + wpIssuesCRM.mapRequest.context + '_download,' + wpIssuesCRM.mapRequest.id;
		wpIssuesCRM.doMapDownload ( exportParameters );		
	}
	
	// dimensions of shapes
	wpIssuesCRM.showShapeStats = function () {

		var shapeCount = wpIssuesCRM.shapeCache.length;
		var statsList;
		var stat;
		if ( shapeCount ) {
			statsList = '<p><strong>Shapes currently drawn include:</strong></p><ol class = google-wic-maps-stats-list>';
			for (var j = 0; j < shapeCount; j++) {
				stat = '<li><strong>' + wpIssuesCRM.shapeCache[j].type.toUpperCase() + '</strong>';
				if ( wpIssuesCRM.shapeCache[j].type == 'polygon' ) {
					var path = wpIssuesCRM.shapeCache[j].overlay.getPath();
					var polyBounds = new google.maps.LatLngBounds;
					var countSides = 0;
					path.forEach( function (vertex ) {
						countSides++;
						polyBounds.extend(vertex)
					});
					stat = stat + ', ' + ( countSides ) + ' sides, ' + rectangleToDims ( polyBounds );
				} else if ( wpIssuesCRM.shapeCache[j].type == 'circle' ) {
					var radiusMeters = wpIssuesCRM.shapeCache[j].overlay.getRadius();
					var radiusMiles = 1/1609.344 * radiusMeters;
					stat = stat + ', radius ' + radiusMiles.toFixed(1) + ' miles (' + radiusMeters.toFixed(0) + ' meters)';
				} else if ( wpIssuesCRM.shapeCache[j].type == 'rectangle' ) {
					stat = stat + ', ' + rectangleToDims ( wpIssuesCRM.shapeCache[j].overlay.getBounds() );
				}
				stat = stat  + '</li>';
				statsList = statsList + stat;
			} 
			statsList = statsList + '</ul>';
		} else {
			statsList = '<p>No shapes drawn at this time.</p>';
		}

		wpIssuesCRM.alert ( statsList );
	}
	
	function rectangleToDims ( bounds ) {
		var north 	= bounds.getNorthEast().lat();
		var east 	= bounds.getNorthEast().lng();
		var south 	= bounds.getSouthWest().lat();
		var west 	= bounds.getSouthWest().lng();
		var northEast = new google.maps.LatLng({'lat': north, 'lng': east});
		var southEast = new google.maps.LatLng({'lat': south, 'lng': east});
		var northWest = new google.maps.LatLng({'lat': north, 'lng': west});
		var height = google.maps.geometry.spherical.computeDistanceBetween( southEast, northEast );
		var width = google.maps.geometry.spherical.computeDistanceBetween( northWest, northEast );
		var heightMiles =  1/1609.344 * height;
		var widthMiles =  1/1609.344 * width;	
		return 'height ' + heightMiles.toFixed(1)  + 'miles (' + height.toFixed(0) + ' meters), width ' + widthMiles.toFixed(1)  + 'miles (' + width.toFixed(0) + ' meters)';
	}

	/*
	* clearFeaturesMap is called only in response to direct user request -- not on close or open
	*
	* on closeMapPopup, do a clear features cache but do not follow with save shapes
	*/
	wpIssuesCRM.clearFeaturesMap = function () {
		wpIssuesCRM.shapeCache.forEach ( function(element) {
			element.overlay.setMap(null);
		});
		// shapes are still cached, just not showing so . . . clear caches
		wpIssuesCRM.shapeCache =[]; 
		// reset the saved version
		wpIssuesCRM.saveShapes();
		// free up the markers
		wpIssuesCRM.idIncludedMarkers();
	}

	/*
	*
	*	shape change listeners
	*
	*	on overlay complete, must update
	*		(1) the shapeCache (updates itself on shape edit);
	*		(2) the saved shape array on the server
	*		(3) the marker array
	*
	*   on any shape edit, must update 2 and 3, but do on delay
	*
	*/

	// fired on completion of a new feature
	wpIssuesCRM.overlayComplete = function (e) {
		// shift out of drawing mode
		wpIssuesCRM.drawingManager.setDrawingMode(null);
		// cache the type and object
		wpIssuesCRM.shapeCache.push(e); 
		// initially save the shape
		wpIssuesCRM.saveShapes();
		// do initial invocation of idIncludedMarkers with new included shape
		wpIssuesCRM.idIncludedMarkers();
		// add edit change listeners to new feature
		wpIssuesCRM.addEditListeners( e ) 
	}

	// listeners on each shape so that as it is updated, marker update (and shape save) sill occur
	wpIssuesCRM.addEditListeners = function ( e ) {
		if ( e.type == 'polygon' ) {
			e.overlay.getPath().addListener ( 'set_at', wpIssuesCRM.queueRecordKeeping );
		} else if ( e.type == 'circle' ){
			e.overlay.addListener ( 'radius_changed', wpIssuesCRM.queueRecordKeeping );
			e.overlay.addListener ( 'center_changed', wpIssuesCRM.queueRecordKeeping );
		} else if ( e.type == 'rectangle' ) {
			e.overlay.addListener ( 'bounds_changed', wpIssuesCRM.queueRecordKeeping );
		}
	}

	// restart timer to do recordkeeping -- 
	wpIssuesCRM.queueRecordKeeping = function() {
		/*
		* disable action controls and send shapes to server
		*
		* give the ajax saveShape a head start -- it should be fast to start, but slow to complete
		*/
		wpIssuesCRM.clearTimer( shapeTimer );
		shapeTimer = setTimeout ( wpIssuesCRM.saveShapes, 250 )
		/*
		* update all markers
		*
		*/
		wpIssuesCRM.clearTimer( markerTimer );
		markerTimer = setTimeout ( wpIssuesCRM.idIncludedMarkers, 255 )
	}

	// redo marker inclusion computations whenever shape is added or edited 
	wpIssuesCRM.idIncludedMarkers = function() { 
	
		// reset counters
		wpIssuesCRM.pointsIncluded = 0;
		wpIssuesCRM.constituentsIncluded = 0;
		markerCount = wpIssuesCRM.markerCache.length;
		shapeCount = wpIssuesCRM.shapeCache.length;

		for (var i = 0; i < markerCount; i++) {
			// presume point excluded
			pointIncluded = false;
			// loop through shapes until find one that includes point
			if ( shapeCount ) {
				for (var j = 0; j < shapeCount; j++) {
					// contains tests
					if ( 
						( // polygon
							wpIssuesCRM.shapeCache[j].type == 'polygon' && 
							google.maps.geometry.poly.containsLocation( wpIssuesCRM.markerCache[i].getMarker().getPosition(), wpIssuesCRM.shapeCache[j].overlay )
						) ||
						( // circle
							wpIssuesCRM.shapeCache[j].type == 'circle' && 
							google.maps.geometry.spherical.computeDistanceBetween( wpIssuesCRM.shapeCache[j].overlay.getCenter(), wpIssuesCRM.markerCache[i].getMarker().getPosition() ) < wpIssuesCRM.shapeCache[j].overlay.getRadius() 
						) ||
						( // rectangle	 
							wpIssuesCRM.shapeCache[j].type == 'rectangle' && 
							wpIssuesCRM.shapeCache[j].overlay.getBounds().contains( wpIssuesCRM.markerCache[i].getMarker().getPosition() )
						)
					) {	
						pointIncluded = true;
						break; // only need be included in one shape
					}
				}
			} // if shape count
			// outside the shape loop, inside the marker loop . . .
			if ( pointIncluded ) {
				wpIssuesCRM.pointsIncluded++;
				wpIssuesCRM.constituentsIncluded += parseFloat( wpIssuesCRM.markerCache[i].getWeight() );
				wpIssuesCRM.markerCache[i].setIsSelected ( true );			
			} else {
				wpIssuesCRM.markerCache[i].setIsSelected ( false );
			} // point not included
		} // marker count loop
		// when all the counting is done -- may be before counter control is loaded in dom
		wpIssuesCRM.countConstituentsWICGoogle.innerHTML = wpIssuesCRM.pointsIncluded + '/' + wpIssuesCRM.constituentsIncluded;
	}// idIncluded Markers

}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
