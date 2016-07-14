/* globals module */
/**
 * Contains a set of options passed to mapbox when adding a feature layer (see available options).
 *
 * See [L.mapbox.featureLayer](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-mapbox-featurelayer/)
 * documentation for the full list of options.
 *
 * @alias dataLayerOpts
 * @class Kartographer.Live.dataLayerOpts
 * @singleton
 * @private
 */
module.dataLayerOpts = {
	// Disable double-sanitization by mapbox's internal sanitizer
	// because geojson has already passed through the MW internal sanitizer
	sanitizer: function ( v ) {
		return v;
	}
};
