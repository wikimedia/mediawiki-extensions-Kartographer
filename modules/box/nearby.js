/**
 * Gets a radius from bounds in meters.
 *
 * @private
 * @param {L.LatLngBounds} bounds
 * @return {number}
 */
function getRadiusFromBounds( bounds ) {
	// This is currently drawing a circle around the whole box so that some
	// results might be outside of the visible area.
	return Math.floor( bounds.getCenter().distanceTo( bounds.getSouthWest() ) );
}

/**
 * Building the search query. Includes calculations to debounce the input
 * from the bounding box.
 *
 * @private
 * @param {L.LatLngBounds} bounds
 * @return {string}
 */
function getSearchQuery( bounds ) {
	// TODO: Precision could be influenced by zoom factor
	var lat = bounds.getCenter().lat.toFixed( 4 ), // cut to a precision of ~11m
		lng = bounds.getCenter().lng.toFixed( 4 ), // cut to a precision of ~11m
		radius = Math.floor( getRadiusFromBounds( bounds ) / 100 ) * 100; // cut to a precision of 100m steps

	return 'nearcoord:' + radius + 'm,' + lat + ',' + lng;
}

module.exports = {
	/**
	 * @param {L.LatLngBounds} bounds
	 * @return {jQuery.Promise}
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
			generator: 'search',
			gsrsearch: getSearchQuery( bounds ),
			gsrnamespace: '0',
			// Set to the max thumbnail limit
			gsrlimit: '50',
			ppprop: 'displaytitle',
			piprop: 'thumbnail',
			pithumbsize: '300',
			// The thumbnail limit is currently 50
			pilimit: '50'
		} );
	},

	/**
	 * @param {Object} response Raw data returned by the geosearch API.
	 * @return {Object[]} A list of GeoJSON features, one for each page.
	 */
	convertGeosearchToGeojson: function ( response ) {
		return response.query.pages.map( function ( page ) {
			var thumbnail = page.thumbnail;
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
					title: page.title,
					description: page.description,
					imageUrl: thumbnail ? thumbnail.source : undefined
				}
			};
		} );
	},

	/**
	 * @param {string} title
	 * @param {string|undefined} description
	 * @param {string|undefined} imageUrl
	 * @return {string}
	 */
	createPopupHtml: function ( title, description, imageUrl ) {
		title = mw.Title.newFromText( title );

		var linkHtml = mw.html.element( 'a', {
				href: title.getUrl(),
				target: '_blank'
			}, title.getPrefixedText() ),
			titleHtml = mw.html.element( 'div', {
				class: 'marker-title'
			}, new mw.html.Raw( linkHtml ) ),
			contentHtml = '';

		if ( description ) {
			contentHtml += mw.html.element( 'span', {}, description );
		}

		if ( imageUrl ) {
			contentHtml += mw.html.element( 'img', { src: imageUrl } );
		}

		if ( contentHtml ) {
			return titleHtml + mw.html.element( 'div', {
				class: 'marker-description'
			}, new mw.html.Raw( contentHtml ) );
		}
		return titleHtml;
	}
};
