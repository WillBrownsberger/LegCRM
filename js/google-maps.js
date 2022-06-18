/*
*
* google-maps.js
*
*/
jQuery(document).ready(function($) { 
	
	$( "#wp-issues-crm" ).on( "click", "#show_map_button, .map-individual-address-button, #show_main_map_button", function() {
		wpIssuesCRM.doListMapPopup ( $(this).val() ); 
	})

});
// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {
	
	var buttonVals;
	var layerPointer = 0; // the next layer to be shown
	// need to be able to reference some elements as variables while still not added to dom; google controls load async		
	var wicGoogleMaps,layerControlDiv, mapTitleDiv, selectControlsDiv; 
	
	wpIssuesCRM.isMapsApiLoaded = false; 	// avoid reloads

	// shared objects within wpIssuesCRM
	wpIssuesCRM.map = false;
	wpIssuesCRM.drawingManager = false;
	wpIssuesCRM.mapRequest = {
		context: '', // show_map (advanced search), show_issue_map, show_point, main_map
		id: 0 // search_id, issue_id, lat for point
	};
	
	// Selected, Unselected, AddressOnly ( = U)
	 wpIssuesCRM.icons = {
		S: {
			url: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png"
		},
		U: {
			url: "http://maps.google.com/mapfiles/ms/icons/yellow-dot.png"
		},
		addressOnly: {
			url:  "http://maps.google.com/mapfiles/ms/icons/yellow-dot.png"
		}
	  };


	/*
	*
	* show map window?
	*
	*/
	wpIssuesCRM.doListMapPopup = function( buttonVal ) {

		layerPointer = 0;
		buttonVals = buttonVal.split(',');
		// define map context if from a saved search of constituents
		if ( ['show_map', 'show_issue_map'].indexOf( buttonVals[0] ) > -1  ){
			wpIssuesCRM.mapRequest.context = buttonVals[0];
			wpIssuesCRM.mapRequest.id = buttonVals[1]; // search id or issue id ( or irrelevant)
		} else {
			wpIssuesCRM.mapRequest = {}
		}
		
		// don't try to display bad point
		if ( 'show_point' == buttonVals[0] ) {
			if ( 0 == parseFloat( buttonVals[1] ) ) {
				wpIssuesCRM.alert ( 'Address not geocoded.  Either address not yet saved or geocoder not functioning.' );
				return;
			} else if ( 99 == parseFloat( buttonVals[1] ) ) {
				wpIssuesCRM.alert ( 'Geocode tried but could not locate address.');
				return;
			}
		} 

  		$( "#wp-issues-crm-google-map-slot").show();
  		if ( wpIssuesCRM.isMapsApiLoaded ) {
  			initMap(); // will check for already existing map within initMap
  		} else {
			wpIssuesCRM.ajaxPost( 'geocode', 'get_map_parameters',  '', '',  function( response ) {
				if ( !response['apiKey'] ) {
					return wpIssuesCRM.Alert( "Missing Google Maps API Key.");
				} else {
					wicGoogleMaps = response;
					$.ajax({
						// loading api with geometry, places and drawing libraries added in
						url: 'https://maps.googleapis.com/maps/api/js?key=' + wicGoogleMaps.apiKey + "&libraries=geometry,places,drawing",
						dataType: 'script',
						success: initMap,
						async: true
					});
				}
			});
		}
	}


	/*
	*
	* close map window -- discarding points and shapes from map AND map cache, but not from server record of point or shapes
	*
	*/
	wpIssuesCRM.closeMapPopup = function() {
		// strip points
  		togglePoints( false );
  		// clear marker and shape cache -- not retained across accesses -- always reloaded when reopen (if needed)
  		wpIssuesCRM.markerCache =[];
  		// strip shapes
  		wpIssuesCRM.shapeCache.forEach ( function(element) {
			element.overlay.setMap(null);
		});
		// clear cache
		wpIssuesCRM.shapeCache = [];
  		// remove show points checkbox -- may not apply next time back to map
  		var element = $( '#wic-google-found-points' );
  		element.parent().remove();
  		// hide, do not discard the window -- saves huges memory leak issue to reuse map
  		$( "#wp-issues-crm-google-map-slot").hide();
	}


	/*
	*
	* initialize map
	*
	*/
	function initMap () { 
	
		// define variations
		var infoWindow, titleDiv, legendControlDiv, exitDiv, centerControlDiv, centerControl, layerControl;
		// note that  layerControlDiv, mapTitleDiv, selectControlsDiv are defined one level up to persist


		// actually load the map
		if ( ! wpIssuesCRM.isMapsApiLoaded ) { 
			// don't load again
			wpIssuesCRM.isMapsApiLoaded = true;



			// setup the map
			wpIssuesCRM.map = new google.maps.Map(document.getElementById( 'wp-issues-crm-google-map-slot' ), {
				center: new google.maps.LatLng( wicGoogleMaps.latCenter, wicGoogleMaps.lngCenter ), // default -- may be moved
				zoom: 13, // default -- will show last on revisit
				zoomControl: true,
				mapTypeControl: true,
				mapTypeControlOptions: {
              		style: google.maps.MapTypeControlStyle.DEFAULT,
              		position: google.maps.ControlPosition.LEFT_BOTTOM,
              		index: 5,
          		},
				scaleControl: false,
				streetViewControl: true,
				rotateControl: true,
				fullscreenControl: true
			});			

			// add feature mouseover listener to highlight districts
			wpIssuesCRM.map.data.addListener('mouseover', function(event) {
				wpIssuesCRM.map.data.revertStyle();
				fillOpacity = .06;
				wpIssuesCRM.map.data.overrideStyle(event.feature, {fillOpacity: fillOpacity});
			
			});

			// set up window that will be updated on click
			infoWindow = new google.maps.InfoWindow();
			wpIssuesCRM.map.data.addListener('click', function(event) {
		
				layerID = event.feature.getProperty('layerId');
				currentLayer = getLayer ( layerID );
				featureTitle = event.feature.getProperty ( currentLayer['featureTitle']);
				if ( featureTitle ) {
					var fullTitle = currentLayer['featureTitle'] + ': ' + featureTitle;
					legend = event.feature.getProperty ( currentLayer['legend']);
					link =  event.feature.getProperty( currentLayer['link'] );
					var infowincontent = document.createElement('div');
					infowincontent.classList.add ('layer-label-infowindow');
					// create a link to the layer object or just a title 
					var strong = document.createElement('strong');
					if ( link ) {
						var a = document.createElement('a');
						var linkText = document.createTextNode( fullTitle );
						a.appendChild(linkText);
						a.title = "More about " +  fullTitle;
						a.href = link
						a.target = "_blank";
						strong.appendChild(a);
					} else {
						var noLinkText = document.createTextNode( fullTitle );
						strong.appendChild(noLinkText)
					}
					infowincontent.appendChild(strong);
			
					if ( legend ) {
						infowincontent.appendChild(document.createElement('br'));
						var text = document.createElement('text');
						text.textContent =  currentLayer['legend'] + ': ' + legend;
						infowincontent.appendChild(text);
					}
			
					infoWindow.setContent(infowincontent);
					var latLng = event.latLng;
					infoWindow.setPosition(latLng);
					infoWindow.open(wpIssuesCRM.map);
				}
			});

			// add drawing manager (TOP_LEFT)
			addDrawingManager()

			// add drawing select controls
			selectControlsDiv = document.createElement('div');
			selectControls( selectControlsDiv );
			selectControlsDiv.index = 2;
			wpIssuesCRM.map.controls[google.maps.ControlPosition.TOP_LEFT].push(selectControlsDiv);

			// add place find control
			placeControlDiv = document.createElement('div');
			placeControl( placeControlDiv );
			wpIssuesCRM.map.controls[google.maps.ControlPosition.TOP_CENTER].push(placeControlDiv);

			// add legend
			legendControlDiv = document.createElement('div');
			legendControl( legendControlDiv );
			wpIssuesCRM.map.controls[google.maps.ControlPosition.BOTTOM_LEFT].push(legendControlDiv);

			// add exit button
			exitDiv = document.createElement('div');
			exitControl( exitDiv )
			wpIssuesCRM.map.controls[google.maps.ControlPosition.TOP_RIGHT].push(exitDiv);

			// add center control
			centerControlDiv = document.createElement('div');
			centerControl = new CenterControl(centerControlDiv, wpIssuesCRM.map, wpIssuesCRM.map.getCenter() )
			centerControlDiv.index = 1;
			centerControlDiv.style['padding-top'] = '10px';
			wpIssuesCRM.map.controls[google.maps.ControlPosition.BOTTOM_CENTER].push(centerControlDiv);

			// add layer control
			layerControlDiv = document.createElement('div');
			layerControlDiv.id = 'wic-google-layers-control';
			layerControl = new LayerControl(layerControlDiv, wpIssuesCRM.map )
			wpIssuesCRM.map.controls[google.maps.ControlPosition.LEFT_TOP].push(layerControlDiv);

		} 

		// show points and show drawing and select controls if searching constituents, otherwise hide controls		
		if ( wpIssuesCRM.mapRequest.context ) { 
			wpIssuesCRM.countConstituentsWICGoogle.innerHTML = '0/0'; // reset before add points in case had some on a prior load, but none being added
			addListPoints();
			wpIssuesCRM.drawingManager.setMap(wpIssuesCRM.map);	
			selectControlsDiv.style.display = "block"
		} else {
			wpIssuesCRM.drawingManager.setMap( null );	
			selectControlsDiv.style.display = "none"		
		}
		
		// handle single point map
		if ( 'show_point' == buttonVals[0] ) {
			addSinglePoint( parseFloat(buttonVals[1]), parseFloat(buttonVals[2]), buttonVals[3] ) ;
		} 

	}   // init map



	function updateBaseStyles() {
		// set styles to include current layer
		wpIssuesCRM.map.data.setStyle(function(feature) {
			currentLayer = getLayer (  feature.getProperty( 'layerId') );
			var layerCheck = $( ".layer-list #" + feature.getProperty( 'layerId') )
			styleObject = {
				visible: layerCheck.length ? layerCheck.prop( "checked" ) : false,
				fillOpacity: 0,
				fillColor: 		currentLayer['strokeColor' ] ? currentLayer['strokeColor' ]: 'red',
				strokeColor:  	currentLayer['strokeColor' ] ? currentLayer['strokeColor' ]: 'red',
				strokeWeight: 	currentLayer['strokeWeight' ] ? currentLayer['strokeWeight' ]  : 1,
				strokeOpacity: 	currentLayer['strokeOpacity' ] ? currentLayer['strokeOpacity' ]  : .5				
			}; 
			return styleObject;
		});
	}

	function addSinglePoint( singleLat, singleLng, title ) { 
		if ( undefined == singleLat || undefined == singleLng ) {
			console.log ( 'Attempted to add undefined point; aborted.');
			return;
		}
		var singlePoint = new google.maps.LatLng(
			singleLat,
			singleLng 
		); 
		wpIssuesCRM.map.setCenter(singlePoint);
		wpIssuesCRM.map.setZoom(15);
		var singleIcon = wpIssuesCRM.icons['addressOnly'];
		var singleMarker = new google.maps.Marker({
			map: wpIssuesCRM.map,
			position: singlePoint,
			icon:singleIcon,
			title: title
		});
		// cache marker
		wpIssuesCRM.markerCache.push( new wpIssuesCRM.MarkerCacheElement ( singleMarker, 1, false ) );
		var infoWindow = new google.maps.InfoWindow;
		//create a window content div
		var infowincontent = document.createElement('div');
		// create a link to the constituent
		var text = document.createElement('text');
		text.textContent = title
		infowincontent.appendChild(text);
		infoWindow.setContent(infowincontent);
		// define marker look
		// add listener to display in the info div
		singleMarker.addListener('click', function() {
			infoWindow.open(wpIssuesCRM.map, singleMarker);
		}); 
		infoWindow.open(wpIssuesCRM.map, singleMarker);

		return;	
	}


	function addListPoints() {
		// issue process requests
		wpIssuesCRM.ajaxPost( 'geocode', 'prepare_list_points',  buttonVals[0], buttonVals[1],  function( response ) {

 			var infoWindow = new google.maps.InfoWindow;
 			var viewFrame = new google.maps.LatLngBounds;
  			// attempt to load shape array with or without points (the without case occurs if issue with no activities)
 			if ( response['shapeArray'] ) {
 				wpIssuesCRM.loadSTA (  response['shapeArray'] );
 			}
 			// done if no points
 			if ( !response['points'] ) {
 				return;
 			}
 			
			// response['points'] is array of objects
            Array.prototype.forEach.call( response['points'], function( markerElem ) {
				var cid = markerElem.cid;
				var name = markerElem.name;
				var others = markerElem.others;
				var address = markerElem.address;
				var email = markerElem.email_address;
				var phone = markerElem.phone_number;
				var markerWeight = markerElem.address_count;
				
				var point = new google.maps.LatLng(
				  parseFloat(markerElem.lat),
				  parseFloat(markerElem.lon)
				);

				viewFrame.extend (point);

				// add the point to the heatmap layer data; 
				// heatMapData.push ({ location: point, weight: markerWeight });

				// then add to the main layer;
				//create a window content div
				var infowincontent = document.createElement('div');
				// create a link to the constituent
				var a = document.createElement('a');
				var linkText = document.createTextNode( name  +  ( markerElem.party ? ( ' (' + markerElem.party + ')' ) : '' ) );
				a.appendChild(linkText);
				a.title = "More about " + name;
				a.href = response['constituentSearchUrl'] + cid;
				a.target = "_blank";
				var strong = document.createElement('strong');
				strong.appendChild(a);
				infowincontent.appendChild(strong);
				// note if others
				var otherText = document.createElement('text');
				otherText.textContent = others;
				infowincontent.appendChild(otherText);
				infowincontent.appendChild(document.createElement('br'));
				// add address and phone information
				var text = document.createElement('text');
				text.textContent = address + ( email ? ( ', ' + email ) : '' ) +  ( phone ? ( ', ' + phone ) : '' )
				infowincontent.appendChild(text);
				// define marker look
				var icon = wpIssuesCRM.icons['U']; // unselected marker
				// create the marker
				var marker = new google.maps.Marker({
					map: wpIssuesCRM.map,
					position: point,
					icon: icon
				});

				// cache the marker for removal
				wpIssuesCRM.markerCache.push( new wpIssuesCRM.MarkerCacheElement ( marker, markerWeight, false ) );
				// add listener to display in the info div
				marker.addListener('click', function() {
					infoWindow.setContent(infowincontent);
					infoWindow.open(wpIssuesCRM.map, marker);
				}); 
			}); // Array.prototype

			// add a checkbox to the list to show/hide found points
			var checkboxWrap = document.createElement('div');
			checkboxWrap.classList.add ('layer-check-wrap');

			var checkbox = document.createElement('input');
			checkbox.type = "checkbox";
			checkbox.id = 'wic-google-found-points';
			checkbox.classList.add ('layer-check');
			checkbox.checked = true;
			var label = document.createElement('label')
			label.htmlFor = 'wic-google-found-points';
			label.appendChild(document.createTextNode('Found List'));
			label.classList.add ('layer-label');

			checkboxWrap.appendChild(checkbox);
			checkboxWrap.appendChild(label);
			layerControlDiv.appendChild(checkboxWrap);
					
			checkbox.addEventListener('change', function(element) {
				togglePoints( checkbox.checked );
			});

			mapTitleDiv.innerHTML = 'Found List of ' + buttonVals[2]+ ' constituents, showing ' + response['countPoints'] + ' distinct geocoded addresses. ';
			if ( ! viewFrame.isEmpty()) {
				wpIssuesCRM.map.fitBounds ( viewFrame );
			}
		});	// ajaxPost	 
	}


	function togglePoints ( show ) {
		map = show ? wpIssuesCRM.map : null;
		for (var i = 0; i < wpIssuesCRM.markerCache.length; i++) {
			wpIssuesCRM.markerCache[i].getMarker().setMap(map);
		}
	
	}

	// save an option to the database for recall
	function setGeocodeOption ( optionName, optionValue ) {
		wpIssuesCRM.ajaxPost( 'geocode', 'set_geocode_option',  optionName, optionValue,  function( response ) {
		});	  
	}

	/*
	*
	* drawing controls
	*
	*/
	wpIssuesCRM.stdOptions =  {
				fillColor: '#ff0000',
				fillOpacity: 0.03,
				strokeWeight: 2,
				strokeColor: 'red',
				strokeOpacity: .5,
				geodesic: true,
				editable: true,
				draggable: true,
				editable: true,
				zIndex: 1
	
	}
	function addDrawingManager() {
	
		wpIssuesCRM.drawingManager = new google.maps.drawing.DrawingManager({
			drawingControl: true,
			drawingControlOptions: {
				position: google.maps.ControlPosition.TOP_LEFT,
				drawingModes: [ 'circle', 'rectangle', 'polygon']
			},

			rectangleOptions: wpIssuesCRM.stdOptions,
			circleOptions: wpIssuesCRM.stdOptions,
			polygonOptions: wpIssuesCRM.stdOptions


		
		});
		
		google.maps.event.addListener(wpIssuesCRM.drawingManager, 'overlaycomplete', wpIssuesCRM.overlayComplete );
		
	}
	/**
	*
	* selectControls
	*
	*
	*/
	function selectControls( controlDiv ) {

		controlDiv.style.clear = 'both';
		controlDiv.id = 'wic-google-select-controls-list';

		var sendMail = document.createElement('div');
		sendMail.id = 'sendMailWICGoogle';
		sendMail.classList.add ( 'wic-google-select-control')
		sendMail.classList.add ( 'dashicons')
		sendMail.classList.add ( 'dashicons-email-alt')
		sendMail.title = 'Click to enter send email dialog';
		controlDiv.appendChild(sendMail);

		var downloadList = document.createElement('div');
		downloadList.id = 'downloadWICGoogle';
		downloadList.classList.add ( 'wic-google-select-control')
		downloadList.classList.add ( 'dashicons')
		downloadList.classList.add ( 'dashicons-download')
		downloadList.title = 'Click to enter list download dialog';
		controlDiv.appendChild(downloadList);

		var showShapeStats = document.createElement('div');
		showShapeStats.id = 'showShapeStatsWICGoogle';
		showShapeStats.classList.add ( 'wic-google-select-control')
		showShapeStats.classList.add ( 'dashicons')
		showShapeStats.classList.add ( 'dashicons-info')		
		showShapeStats.title = 'See shape statistics';
		controlDiv.appendChild(showShapeStats);

		var clearFeatures = document.createElement('div');
		clearFeatures.id = 'clearFeaturesWICGoogle';
		clearFeatures.classList.add ( 'wic-google-select-control')
		clearFeatures.classList.add ( 'dashicons')
		clearFeatures.classList.add ( 'dashicons-dismiss')		
		clearFeatures.title = 'Click to clear shapes';
		controlDiv.appendChild(clearFeatures);

		// use global, because may need to update again before added to dom 
		wpIssuesCRM.countConstituentsWICGoogle = document.createElement('div');
		wpIssuesCRM.countConstituentsWICGoogle.id = 'countConstituentsWICGoogle';
		wpIssuesCRM.countConstituentsWICGoogle.classList.add ( 'wic-google-select-control')
		wpIssuesCRM.countConstituentsWICGoogle.classList.add ( 'wic-google-select-control-count')
		wpIssuesCRM.countConstituentsWICGoogle.title = 'Count of selected points/constituents (may be more than one per point)';
		wpIssuesCRM.countConstituentsWICGoogle.innerHTML='0/0';
		controlDiv.appendChild(wpIssuesCRM.countConstituentsWICGoogle);

		controlDiv.addEventListener( "click", wpIssuesCRM.selectControlCallBacks );
	
	}
	
	wpIssuesCRM.selectControlCallBacks = function( e ){
		if ( 'sendMailWICGoogle'  == e.target.id )  {
        		wpIssuesCRM.sendMailMap();
		} else if ( 'downloadWICGoogle'  == e.target.id ) {
			wpIssuesCRM.downloadListMap();
		} else if ( 'showShapeStatsWICGoogle'  == e.target.id ) {
			wpIssuesCRM.showShapeStats();
		} else if ( 'clearFeaturesWICGoogle'  == e.target.id ) {
			wpIssuesCRM.clearFeaturesMap();
		} else if ( 'countConstituentsWICGoogle'  == e.target.id ) {
			wpIssuesCRM.showInfoMap();	
		}												
	}


	/**
	*
	* placeControl
	*
	*
	*/
	placeControl = function ( placeControlDiv ) { 
	
		// create input and put it in the placeControlDiv
		var placeInput = document.createElement ('input');
		placeInput.id = "pac-input";
		placeInput.classList.add ( "wic-input-controls" );
		placeInput.placeholder = "Enter a location" ;
		placeInput.type = "text" 
		placeControlDiv.appendChild ( placeInput );
		// attach autocomplete to input
        var autocomplete = new google.maps.places.Autocomplete(placeInput);
        autocomplete.bindTo('bounds', wpIssuesCRM.map);

		// create content div for marker
		var infowindowContent = document.createElement('div');
		infowindowContent.id = 'infowindow-content';
		infowindowContent.innerHTML = 
		//	'<h4>Beta Lookup Results -- use with caution</h4>' +
     		'<span id="place-address"></span>' 
     	//	+ '<br><div id ="poly-container"></div>';
		var tempList = document.createElement('div');

		// info window bound to marker, but not yet placed
        var infowindow = new google.maps.InfoWindow();
        infowindow.setContent(infowindowContent);
        var marker = new google.maps.Marker({
        	map: wpIssuesCRM.map
        });

		// set listener on marker to reopen windwo if closed
        marker.addListener('click', function() {
        	infowindow.open(wpIssuesCRM.map, marker);
        });

		// on the select event . . .	
        autocomplete.addListener( 'place_changed', function() {

			// close the existing window
			infowindow.close();
				var place = autocomplete.getPlace();
				if (!place.geometry) {
			return;
			}

			if (place.geometry.viewport) {
				wpIssuesCRM.map.fitBounds(place.geometry.viewport);
			} else {
				wpIssuesCRM.map.setCenter(place.geometry.location);
			}
			wpIssuesCRM.map.setZoom(13);


			// Set the position of the marker using the place ID and location.
			marker.setPlace({
				placeId: place.place_id,
				location: place.geometry.location
			});
			marker.setVisible(true);
			infowindowContent.children['place-address'].textContent = place.formatted_address;
	//		infowindowContent.children['poly-container'].innerHTML = tempList.innerHTML;
			infowindow.open(wpIssuesCRM.map, marker);
        });
    }

	/**
	*
	* legend control
	*
	*/
	function legendControl( legendDiv ) {
		if ( ! wicGoogleMaps.localCredit ) {
			return;
		}
		mapTitleDiv   = document.createElement('div');
		mapTitleDiv.id = "wic-google-title";
		mapTitleDiv.appendChild(document.createTextNode("Map Credits: " ));
		sourceDiv   = document.createElement('div');
		sourceDiv.id = "wic-google-legend-text";
		sourceDiv.innerHTML = wicGoogleMaps.localCredit;

		legendDiv.appendChild ( mapTitleDiv );
		legendDiv.appendChild ( sourceDiv );
		legendDiv.id = "wic-google-legend";
		
	}
	/*
	*
	* exit control
	*
	*/

	function exitControl( legendDiv ) {
	
		legendDiv.id = "wic-google-exit";
		legendDiv.innerHTML = '<span class="dashicons dashicons-dismiss"></span>';
		legendDiv.addEventListener('click', wpIssuesCRM.closeMapPopup );
	}
	
	
	/**
	*
	* set center control
	*
	* derived from https://developers.google.com/maps/documentation/javascript/examples/control-custom-state 
	*
	*/
	function CenterControl( controlDiv, map, center ) {
		// We set up a variable for this since we're adding event listeners
		// later.
		var control = this;

		// Set the center property upon construction
		control.center_ = center;
		controlDiv.style.clear = 'both';

		// create center div
		var goCenterUI = document.createElement('div');
		goCenterUI.id = 'goCenterUI';
		goCenterUI.title = 'Click to recenter the map';
		controlDiv.appendChild(goCenterUI);

		// create text div within center div
		var goCenterText = document.createElement('div');
		goCenterText.id = 'goCenterText';
		goCenterText.innerHTML = 'Center Map';
		goCenterUI.appendChild(goCenterText);

		// create set center div
		var setCenterUI = document.createElement('div');
		setCenterUI.id = 'setCenterUI';
		setCenterUI.title = 'Click to change the startup center of the map';
		controlDiv.appendChild(setCenterUI);

		// create text div within set center div
		var setCenterText = document.createElement('div');
		setCenterText.id = 'setCenterText';
		setCenterText.innerHTML = 'Set Center';
		setCenterUI.appendChild(setCenterText);

		// Set up the click event listener for 'Center Map': 
		// Set the center of the map to the current center of the control.
		goCenterUI.addEventListener('click', function() {
			var currentCenter = control.getCenter();
			map.setCenter(currentCenter);
		});

		// Set up the click event listener for 'Set Center': Set the center of
		// the control to the current center of the map.
		setCenterUI.addEventListener('click', function() {
			var newCenter = map.getCenter();
			control.setCenter(newCenter);
		});
	}
	/**
	* Define a property to hold the center state.
	*/
	CenterControl.prototype.center_ = null;
	/**
	* Gets the map center.
	*/
	CenterControl.prototype.getCenter = function() {
		return this.center_;
	};
	/**
	* Sets the map center.
	* @param {?google.maps.LatLng} center
	*/
	CenterControl.prototype.setCenter = function(center) {
		// update permanent default center 
		setGeocodeOption( 'set-map-midpoints', [center.lat(), center.lng()] );
		// update script variable for center
		this.center_ = center;
	};

	/**
	*
	* show layer control -- add layers as we go, enable boxes as loaded
	*
	*/
	
	function LayerControl ( controlDiv, map ) {
	
		var control = this;
		
		controlDiv.classList.add ('layer-list');
        controlDiv.style['padding-left'] = '10px';
        controlDiv.index = 1;

		Array.prototype.forEach.call( wicGoogleMaps.localLayers, function( layer ) {

			var checkboxWrap = document.createElement('div');
			checkboxWrap.classList.add ('layer-check-wrap');

			var checkbox = document.createElement('input');
			checkbox.type = "checkbox";
			checkbox.id = layer['layerId'];
			checkbox.classList.add ('layer-check');
		    checkbox.disabled = true;
			$.getJSON( layer['layerURL'], function( json ) {
				Array.prototype.forEach.call( json.features, function( feature ) {
					feature.properties.layerId = layer['layerId'];
					map.data.addGeoJson(feature );
				});
				updateBaseStyles();	
				checkbox.disabled = false;
			});

			var label = document.createElement('label')
			label.htmlFor = "layerId";
			label.appendChild(document.createTextNode(layer['layerTitle']));
			label.classList.add ('layer-label');

			checkboxWrap.appendChild(checkbox);
			checkboxWrap.appendChild(label);
			controlDiv.appendChild(checkboxWrap);
			
			checkbox.addEventListener('change', function(element) {
				toggleLayers( checkbox.id, checkbox.checked );
			});
		});	
	}

	function toggleLayers( layerId, showLayer) { 
		// just shut down the check boxes and update styles to reflect new checkbox status
		$layerChecked = $( "#google-map .layer-check-wrap input" ).prop( "disabled", true );
		updateBaseStyles();
		$layerChecked = $( "#google-map .layer-check-wrap input" ).prop( "disabled", false );
	}

	function getLayer ( layerId ) {
		// get layerURL
		currentLayer =  wicGoogleMaps.localLayers.find (function( layer ) {
			return layer['layerId']  == layerId;
		});	
		return currentLayer;	
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure 	