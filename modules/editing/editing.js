/* globals module */
module.exports = ( function ( $, mw ) {

	/**
	 * Get "editable" geojson layer for the map.
	 *
	 * If a layer doesn't exist, create and attach one.
	 *
	 * @param {L.mapbox.Map} map Map to get layers from
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
	 * @param {L.mapbox.Map} map Map to set the GeoJSON for
	 * @param {string} geoJsonString GeoJSON data, empty string to clear
	 * @return {jQuery.Promise} Promise which resolves when the GeoJSON is updated, and rejects if there was an error
	 */
	function updateKartographerLayer( map, geoJsonString ) {
		var deferred = $.Deferred();

		if ( geoJsonString === '' ) {
			return deferred.resolve().promise();
		}

		new mw.Api().post( {
			action: 'sanitize-mapdata',
			text: geoJsonString,
			title: mw.config.get( 'wgPageName' )
		} ).done( function ( resp ) {
			var geoJson, layer,
				data = resp[ 'sanitize-mapdata' ];

			geoJsonString = data && data.sanitized;

			if ( geoJsonString && !data.error ) {
				try {
					geoJson = JSON.parse( geoJsonString );
					layer = getKartographerLayer( map );
					layer.setGeoJSON( geoJson );
					deferred.resolve();
				} catch ( e ) {
					deferred.reject( e );
				}
			} else {
				deferred.reject();
			}
		} );

		return deferred.promise();
	}

	return {
		getKartographerLayer: getKartographerLayer,
		updateKartographerLayer: updateKartographerLayer
	};

} )( jQuery, mediaWiki );
