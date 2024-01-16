/**
 * Module listening to `wikipage.maps` hook and adding a right-click handler to
 * the map to show the corresponding coordinates.
 *
 * This module may be loaded and executed by
 * {@link Kartographer.Box.enablePreview ext.kartographer.box}.
 *
 * @borrows Kartographer.Preview as Preview
 * @borrows Kartographer.Preview as ext.kartographer.preview
 * @class Kartographer.Preview
 * @singleton
 */
mw.hook( 'wikipage.maps' ).add( function ( maps ) {
	maps = Array.isArray( maps ) ? maps : [ maps ];

	maps.forEach( function ( map ) {
		const popup = L.popup();

		function onMapMenu( e ) {
			const zoom = map.getZoom();
			const wrapped = e.latlng.wrap();
			const coords = map.getScaleLatLng(
				wrapped.lat,
				wrapped.lng
			);
			const content = '<table>' +
				'<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-lat' ).escaped() + '</th><td>' + coords[ 0 ] + '</td></tr>' +
				'<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-lon' ).escaped() + '</th><td>' + coords[ 1 ] + '</td></tr>' +
				'<tr><th>' + mw.message( 'visualeditor-mwmapsdialog-position-zoom' ).escaped() + '</th><td>' + zoom + '</td></tr>' +
				'</table>';

			popup
				.setLatLng( e.latlng )
				// These are non-localized wiki tag attributes, so no need for i18n
				.setContent( content )
				.openOn( map );
		}

		if ( !map.isStatic() ) {
			// on right click, add a little popup with the coordinates.
			map.on( 'contextmenu', onMapMenu );
		}
	} );
} );
