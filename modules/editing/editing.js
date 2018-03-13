/**
 * Module containing useful methods when editing a map.
 *
 * @alias Editing
 * @alias ext.kartographer.editing
 * @class Kartographer.Editing
 * @singleton
 */
module.exports = ( function ( $, mw ) {

	/**
	 * Get "editable" geojson layer for the map.
	 *
	 * If a layer doesn't exist, create and attach one.
	 *
	 * @param {L.Map} map Map to get layers from
	 * @param {L.mapbox.FeatureLayer} map.kartographerLayer show tag-specific info in this layer
	 * @return {L.mapbox.FeatureLayer|null} GeoJSON layer, if present
	 */
	function getKartographerLayer( map ) {
		if ( !map.kartographerLayer ) {
			map.kartographerLayer = L.mapbox.featureLayer().addTo( map );
		}
		return map.kartographerLayer;
	}

	/**
	 * Updates "editable" GeoJSON layer from a string.
	 *
	 * Validates the GeoJSON against the `sanitize-mapdata` api
	 * before executing it.
	 *
	 * The deferred object will be resolved with a `boolean` flag
	 * indicating whether the GeoJSON was valid and was applied.
	 *
	 * @param {L.Map} map Map to set the GeoJSON for
	 * @param {string} geoJsonString GeoJSON data
	 * @return {jQuery.Promise} Promise which resolves when the GeoJSON is updated, and rejects if there was an error
	 */
	function updateKartographerLayer( map, geoJsonString ) {
		if ( geoJsonString === '' ) {
			return $.Deferred().resolve().promise();
		}

		return new mw.Api().post( {
			action: 'sanitize-mapdata',
			text: geoJsonString,
			title: mw.config.get( 'wgPageName' )
		} ).then( function ( resp ) {
			var geoJson, layer,
				data = resp[ 'sanitize-mapdata' ],
				sanitizedJsonString = data && data.sanitized;

			if ( data.error || !sanitizedJsonString ) {
				return $.Deferred().reject().promise();
			}
			geoJson = JSON.parse( sanitizedJsonString );
			layer = getKartographerLayer( map );
			layer.setGeoJSON( geoJson );
		} );
	}

	/**
	 * Convert sanitized GeoJSON back to GeoJSON that's suitable for storage.
	 *
	 * Specifically, this looks for key pairs like { "title": "foo", "_origtitle": "bar" }, restores
	 * title from _origtitle, and deletes _origtitle, so the end result is { "title": "bar" }.
	 *
	 * @param {Object} geoJson GeoJSON object, will be modified
	 */
	function restoreUnparsedText( geoJson ) {
		var key, baseKey;
		for ( key in geoJson ) {
			if ( key.substring( 0, '_orig'.length ) === '_orig' ) {
				baseKey = key.substring( '_orig'.length );
				// Copy the original value back, and delete the _orig key
				geoJson[ baseKey ] = geoJson[ key ];
				delete geoJson[ key ];
			} else if ( typeof geoJson[ key ] === 'object' ) {
				// Recurse
				restoreUnparsedText( geoJson[ key ] );
			}
		}
	}

	return {
		getKartographerLayer: getKartographerLayer,
		updateKartographerLayer: updateKartographerLayer,
		restoreUnparsedText: restoreUnparsedText
	};

}( jQuery, mediaWiki ) );
