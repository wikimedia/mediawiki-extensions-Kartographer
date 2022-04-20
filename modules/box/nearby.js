/**
 * @private
 * @param {L.LatLng} coordinates
 * @return {string}
 */
function coordinatesToString( coordinates ) {
	return coordinates.lat + '|' + coordinates.lng;
}

/**
 * @private
 * @param {L.LatLng} northWest
 * @param {L.LatLng} southEast
 * @return {string}
 */
function getBoundingBoxString( northWest, southEast ) {
	return coordinatesToString( northWest ) + '|' + coordinatesToString( southEast );
}

module.exports = {
	/**
	 * @param {L.LatLngBounds} bounds
	 * @return {Promise}
	 */
	fetch: function ( bounds ) {
		// TODO: Cache results if bounds remains unchanged
		return ( new mw.Api( {
			/* ajax: {
				// TODO: Temporary override for local testing
				url: 'https://en.wikipedia.org/w/api.php',
				headers: {
					'User-Agent': 'Kartographer - the WMF Content Transform Team (https://www.mediawiki.org/wiki/Content_Transform_Team)'
				}
			} */
		} ) ).get( {
			action: 'query',
			format: 'json',
			formatversion: '2',
			prop: 'coordinates|pageprops|pageimages|description',
			colimit: 'max',
			generator: 'geosearch',
			gsbbox: getBoundingBoxString( bounds.getNorthWest(), bounds.getSouthEast() ),
			ggsnamespace: '0',
			ggslimit: '50',
			ggscoord: coordinatesToString( bounds.getCenter() ),
			ppprop: 'displaytitle',
			piprop: 'thumbnail',
			pithumbsize: '150',
			pilimit: '50'
		} );
	},

	/**
	 * @param {Object} response Raw data returned by the geosearch API.
	 * @return {Object[]} A list of GeoJSON features, one for each page.
	 */
	convertGeosearchToGeojson: function ( response ) {
		return response.query.pages.map( function ( page ) {
			return {
				type: 'Feature',
				geometry: {
					type: 'Point',
					coordinates: [
						page.coordinates[ 0 ].lon,
						page.coordinates[ 0 ].lat
					]
				},
				properties: {
					name: page.title
				}
			};
		} );
	}
};
