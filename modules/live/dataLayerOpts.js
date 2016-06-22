/* globals module */
module.dataLayerOpts = {
	// Disable double-sanitization by mapbox's internal sanitizer
	// because geojson has already passed through the MW internal sanitizer
	sanitizer: function ( v ) {
		return v;
	}
};
