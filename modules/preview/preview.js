/* globals require */
( function ( mw, L, kartographer ) {

	/**
	 * This module adds a `contextmenu` event to the map. When a user right
	 * clicks on the map, he gets a little popup with the coordinates of
	 * where he clicked.
	 */
	mw.hook( 'wikipage.maps' ).add( function ( maps ) {
		maps = $.isArray( maps ) ? maps : [ maps ];

		$.each( maps, function ( i, map ) {
			var popup = L.popup();

			function onMapMenu( e ) {
				var coords = kartographer.getScaleCoords(
					map.getZoom(),
					e.latlng.lat,
					e.latlng.lng
				);

				popup
					.setLatLng( e.latlng )
					// These are non-localized wiki tag attributes, so no need for i18n
					.setContent( 'latitude=' + coords[ 1 ] + ' longitude=' + coords[ 2 ] )
					.openOn( map );
			}

			map.on( 'contextmenu', onMapMenu );
		} );
	} );

}( mediaWiki, L, require( 'ext.kartographer.init' ) ) );
