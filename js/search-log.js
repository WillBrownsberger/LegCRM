/*
*
*	search-log.js 
*
*	
*
*/
jQuery( document ).ready( function($) { 
 
 	// set date picker -- note that this invokes 
	$( "#wp-issues-crm" ).on ( "click", ".wic-favorite-button", function (e) {
		wpIssuesCRM.handleSearchFavoriteButton ( this );
	})  
	
		// set up handler for name replacement on right click of list button
	.on("mousedown",  ".wic-search-log-list-button" , function(e) {
		if ( 3 == e.which ) { 
			wpIssuesCRM.doSearchNameDialog( this ); 
		};
	});

});

// anonymous function creates namespace object
( function( wpIssuesCRM, $, undefined ) {

	wpIssuesCRM.handleSearchFavoriteButton = function ( favoriteButton ) {
		starSpan = $( favoriteButton ).find("span").first();
		if ( 1 == $( favoriteButton ).next().find(".pl-search_log-is_named").text() ) {
			return false;
		}
		var buttonValueArray = $( favoriteButton ).val().split(",");
		var searchID = buttonValueArray[2];
		var favorite = starSpan.hasClass ( "dashicons-star-filled" );
		var data = { favorite : !favorite };
		wpIssuesCRM.ajaxPost( 'search_log', 'set_favorite', searchID, data, function( response ) {
			if ( favorite ) { 
				starSpan.removeClass ( "dashicons-star-filled" );
				starSpan.addClass ( "dashicons-star-empty" );
			} else {
				starSpan.addClass ( "dashicons-star-filled" );
				starSpan.removeClass ( "dashicons-star-empty" );
			}
		});						
	}
	
	/*
	*
	* doSearchNameDialog -- manages naming of searches
	*
	*/
	wpIssuesCRM.doSearchNameDialog = function ( listButton ) {

		// get necessary values
		var searchID = listButton.value.split(',')[2] ;
		var searchNameElement = $(listButton).find(".pl-search_log-share_name")
		var searchName = searchNameElement.text();
		searchName = 'private' == searchName ? '' : searchName;
		// define dialog box
		var divOpen = '<div title="Enter Search Name">';
		var nameInput = '<input id="new_search_name" type="text" value ="'  + searchName + '"></input>' ;
		var divClose = '</div>';
		dialog = $.parseHTML( divOpen + nameInput + divClose );
		// kill the standard context menu (just for this button)
		var saveContextMenu = document.oncontextmenu 
		document.oncontextmenu = function() {return false;};
		// show the share name dialog instead
		dialogObject = $( dialog );
  		dialogObject.dialog({
			appendTo: "#wp-issues-crm",  		
  			closeOnEscape: true,
  			close: function ( event, ui ) {
  				document.oncontextmenu = saveContextMenu; 	// restore context menu
  				dialogObject.remove();						// cleanup object
  				},
			position: { my: "left top", at: "left bottom", of: searchNameElement }, 	
			width: 180,
			buttons: [
				{	width: 50,
					text: "OK",
					click: function() {
						var newSearchName = dialogObject.find ( "#new_search_name" ).val();
						wpIssuesCRM.ajaxPost( 'search_log', 'update_name', searchID, newSearchName, function ( response ) {
							searchNameElement.text( newSearchName > '' ? newSearchName : 'private' );
							starSpan = $( listButton ).prev().find("span").first()
							if( newSearchName > '' ) {
								// when name > '', then database access will always set favorite
								// need to reflect this on client side
								starSpan.addClass ( "dashicons-star-filled" );
								starSpan.removeClass ( "dashicons-star-empty" );
								starSpan.parent().tooltip( "option", "content", 'Cannot unfavorite non-private searches ( those with a Share Name ).' )
								searchNameElement.parent().find(".pl-search_log-is_named").text("1");
							} else {
								searchNameElement.text ('private');
								starSpan.parent().tooltip( "option", "content", 'Click to mark/unmark private favorite searches.' )
								searchNameElement.parent().find(".pl-search_log-is_named").text("0");
							}
							$( "#post-form-message-box" ).text(response[1]);
							dialogObject.dialog( "close" ); 
						});
					}
				},
				{
					width: 50,
					text: "Cancel",
					click: function() {
						dialogObject.dialog( "close" ); 
					}
				}
			],
  			modal: true,
  		});
		$( ".ui-widget-overlay.ui-front" ).css ( "opacity", "0.4" );

	}

	wpIssuesCRM.initializeSearchLogTooltips = function () {
		$( ".wic-search-log-list-button" ).tooltip( {
			position: { my: "left top", at: "center", collision: "flipfit" },
			show: false,
			hide: false
		});
		$( ".wic-favorite-button" ).tooltip( {show: false, hide: false} );
	}


}( window.wpIssuesCRM = window.wpIssuesCRM || {}, jQuery )); // end anonymous namespace enclosure	
