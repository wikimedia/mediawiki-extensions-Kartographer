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
	// Absolute limitation of the center point's precision to ~11m
	var lat = bounds.getCenter().lat.toFixed( 4 ),
		lng = bounds.getCenter().lng.toFixed( 4 );
	// Absolute limitation of the radius' precision to 100m
	var radius = ( Math.floor( getRadiusFromBounds( bounds ) / 100 ) || 1 ) * 100;

	return 'nearcoord:' + radius + 'm,' + lat + ',' + lng;
}

module.exports = {
	/**
	 * @param {L.LatLngBounds} bounds
	 * @return {jQuery.Promise}
	 */
	fetch: function ( bounds ) {
		// The maximum thumbnail limit is currently 50
		var limit = 50;
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
			formatversion: 2,
			prop: 'coordinates|pageprops|pageimages|description',
			// co… arguments belong to prop=coordinates
			colimit: 'max',
			generator: 'search',
			// gsr… arguments belong to generator=search
			gsrsearch: getSearchQuery( bounds ),
			gsrnamespace: 0,
			gsrlimit: limit,
			// pp… arguments belong to prop=pageprops
			ppprop: 'displaytitle',
			// pi… arguments belong to prop=pageimages
			piprop: 'thumbnail',
			pithumbsize: 300,
			pilimit: limit
		} );
	},

	/**
	 * @param {Object} response Raw data returned by the geosearch API.
	 * @return {Object[]} A list of GeoJSON features, one for each page.
	 */
	convertGeosearchToGeojson: function ( response ) {
		var pages = response.query && response.query.pages || [];

		return pages.reduce( function ( result, page ) {
			var coordinates = page.coordinates && page.coordinates[ 0 ];

			if ( coordinates ) {
				var thumbnail = page.thumbnail;

				result.push( {
					type: 'Feature',
					geometry: { type: 'Point', coordinates: [ coordinates.lon, coordinates.lat ] },
					properties: {
						title: page.title,
						description: page.description,
						imageUrl: thumbnail ? thumbnail.source : undefined
					}
				} );
			}

			return result;
		}, [] );
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
