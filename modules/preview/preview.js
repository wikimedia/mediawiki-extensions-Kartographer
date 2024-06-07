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
mw.hook( 'wikipage.maps' ).add( ( maps ) => {
	maps = Array.isArray( maps ) ? maps : [ maps ];

	maps.forEach( ( map ) => {
		const popup = L.popup();

		function onMapMenu( e ) {
			const zoom = map.getZoom();
			const wrapped = e.latlng.wrap();
			const coords = map.getScaleLatLng(
				wrapped.lat,
				wrapped.lng
			);
			const $content = $( '<table>' ).append(
				$( '<tr>' ).append(
					$( '<th>' ).text( mw.msg( 'visualeditor-mwmapsdialog-position-lat' ) ),
					$( '<td>' ).text( coords[ 0 ] )
				),
				$( '<tr>' ).append(
					$( '<th>' ).text( mw.msg( 'visualeditor-mwmapsdialog-position-lon' ) ),
					$( '<td>' ).text( coords[ 1 ] )
				),
				$( '<tr>' ).append(
					$( '<th>' ).text( mw.msg( 'visualeditor-mwmapsdialog-position-zoom' ) ),
					$( '<td>' ).text( zoom )
				)
			);

			popup
				.setLatLng( e.latlng )
				// These are non-localized wiki tag attributes, so no need for i18n
				.setContent( $content[ 0 ] )
				.openOn( map );
		}

		if ( !map.isStatic() ) {
			// on right click, add a little popup with the coordinates.
			map.on( 'contextmenu', onMapMenu );
		}
	} );
} );
