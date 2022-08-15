/**
 * @private
 * @param {L.LatLngBounds} bounds
 * @return {number} Radius in meter
 */
function getDebouncedRadius( bounds ) {
	// This corresponds to the smallest circle around the bounding rectangle, so some results will
	// be outside that visible rectangle
	var radius = Math.floor( bounds.getCenter().distanceTo( bounds.getSouthWest() ) );
	// Rounding to 2 significant digits means we loose +/-5% in the absolute worst case
	// eslint-disable-next-line no-bitwise
	return radius.toPrecision( 2 ) | 0;
}

/**
 * De-bounce point to a certain degree of accuracy depending on zoom factor.
 *
 * @private
 * @param {L.LatLng} point
 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
 * @return {L.LatLng}
 */
function getDebouncedPoint( point, zoom ) {
	// Higher numbers = less precision = larger grid size = better debounce
	var looseness = 22;
	// 4 decimal places correspond to ~11m, 3 to ~110m, and so on
	var decimalPlaces = Math.max(
		// Zoom changes with a factor of 2, lat/lng with a factor of 10 per decimal place
		4 - Math.floor( ( looseness - zoom ) * Math.LN2 / Math.LN10 ),
		0
	);
	return new L.LatLng(
		point.lat.toFixed( decimalPlaces ),
		point.lng.toFixed( decimalPlaces )
	);
}

/**
 * Building the search query. Includes calculations to debounce the input
 * from the bounding box.
 *
 * @private
 * @param {L.LatLngBounds} bounds
 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
 * @return {string}
 */
function getSearchQuery( bounds, zoom ) {
	var radius = getDebouncedRadius( bounds ),
		center = getDebouncedPoint( bounds.getCenter(), zoom );
	radius = radius % 1000 ? radius + 'm' : Math.round( radius / 1000 ) + 'km';
	return 'nearcoord:' + radius + ',' + center.lat + ',' + center.lng;
}

/**
 * @private
 * @param {string} title
 * @param {string|undefined} description
 * @param {string|undefined} imageUrl
 * @return {string}
 */
function createPopupHtml( title, description, imageUrl ) {
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

function createNearbyMarker( geoJson, latlng ) {
	return L.marker( latlng, {
		icon: L.mapbox.marker.icon( { 'marker-color': geoJson.properties[ 'marker-color' ] || 'a2a9b1' } )
	} );
}

module.exports = {
	/**
	 * @param {L.LatLngBounds} bounds
	 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
	 * @return {jQuery.Promise}
	 */
	fetch: function ( bounds, zoom ) {
		// The maximum thumbnail limit is currently 50
		var limit = 50;
		// TODO: Cache results if bounds remains unchanged
		return ( new mw.Api( {
			/* TODO: Temporary override for local testing; remove when not needed any more *
			ajax: {
				url: 'https://en.wikipedia.org/w/api.php',
				headers: {
					'User-Agent': 'Kartographer - the WMF Content Transform Team (https://www.mediawiki.org/wiki/Content_Transform_Team)'
				}
			}
			/**/
		} ) ).get( {
			action: 'query',
			format: 'json',
			formatversion: 2,
			prop: 'coordinates|pageprops|pageimages|description',
			// co… arguments belong to prop=coordinates
			colimit: 'max',
			generator: 'search',
			// gsr… arguments belong to generator=search
			gsrsearch: getSearchQuery( bounds, zoom ),
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
						imageUrl: thumbnail ? thumbnail.source : undefined,
						'marker-color': '0000ff'
					}
				} );
			}

			return result;
		}, [] );
	},

	createNearbyLayer: function ( geojson ) {
		return L.geoJSON(
			geojson,
			{ pointToLayer: createNearbyMarker }
		).bindPopup( function ( layer ) {
			return createPopupHtml(
				layer.feature.properties.title,
				layer.feature.properties.description,
				layer.feature.properties.imageUrl
			);
		} );
	}

};
