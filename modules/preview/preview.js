/* globals require */
/**
 * Module listening to `wikipage.maps` hook and adding a right-click handler to
 * the map to show the corresponding coordinates.
 *
 * This module may be loaded and executed by
 * {@link Kartographer.Live.enablePreview ext.kartographer.live}.
 *
 * @alias Preview
 * @alias ext.kartographer.preview
 * @class Kartographer.Preview
 * @singleton
 */
( function ( mw, L, kartographer ) {

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

			// on right click, add a little popup with the coordinates.
			map.on( 'contextmenu', onMapMenu );
		} );
	} );

}( mediaWiki, L, require( 'ext.kartographer.init' ) ) );
