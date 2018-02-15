/**
 * Module listening to `wikipage.maps` hook and adding a right-click handler to
 * the map to show the corresponding coordinates.
 *
 * This module may be loaded and executed by
 * {@link Kartographer.Box.enablePreview ext.kartographer.box}.
 *
 * @alias Preview
 * @alias ext.kartographer.preview
 * @class Kartographer.Preview
 * @singleton
 */
( function ( mw, L ) {

	mw.hook( 'wikipage.maps' ).add( function ( maps ) {
		maps = Array.isArray( maps ) ? maps : [ maps ];

		$.each( maps, function ( i, map ) {
			var popup = L.popup();

			function onMapMenu( e ) {
				var content = '',
					zoom = map.getZoom(),
					coords = map.getScaleLatLng(
						e.latlng.lat,
						e.latlng.lng
					);

				content += '<table>';
				content += '<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-lat' ).escaped() + '</th><td>' + coords[ 0 ] + '</td></tr>';
				content += '<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-lon' ).escaped() + '</th><td>' + coords[ 1 ] + '</td></tr>';
				content += '<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-zoom' ).escaped() + '</th><td>' + zoom + '</td></tr>';
				content += '</table>';

				popup
					.setLatLng( e.latlng )
					// These are non-localized wiki tag attributes, so no need for i18n
					.setContent( content )
					.openOn( map );
			}

			// on right click, add a little popup with the coordinates.
			map.on( 'contextmenu', onMapMenu );
		} );
	} );

}( mediaWiki, L ) );
