/**
 * # Options passed to Mapbox when adding a feature layer.
 *
 * `L.mapbox.featureLayer` provides an easy way to add a layer from GeoJSON
 * into your map. This module is the set of options passed to this method
 * when it is called.
 *
 * See [L.mapbox.featureLayer](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-mapbox-featurelayer/)
 * documentation for the full list of options.
 *
 * @class Kartographer.Box.dataLayerOpts
 * @singleton
 * @private
 */
module.dataLayerOpts = {
	/**
	 * A function that accepts a string containing tooltip data,
	 * and returns a sanitized result for HTML display.
	 *
	 * The default Mapbox sanitizer is disabled because GeoJSON has already
	 * passed through Kartographer's internal sanitizer (avoids double
	 * sanitization).
	 *
	 * @param {Object} geojson
	 * @return {Object}
	 */
	sanitizer: function ( geojson ) {
		return geojson;
	}
};
