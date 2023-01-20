/**
 * @param {Object} parameters
 * @return {jQuery.Promise}
 */
function mwApi( parameters ) {
	return ( new mw.Api( {
		/* TODO: Temporary override for local testing; remove when not needed any more *
		ajax: {
			url: 'https://en.wikipedia.org/w/api.php',
			headers: {
				'User-Agent': 'Kartographer - the WMF Content Transform Team (https://www.mediawiki.org/wiki/Content_Transform_Team)'
			}
		}
		/**/
	} ) ).get( parameters );
}

/**
 * @param {L.Popup} popup
 * @param {string} title
 * @param {string} [description]
 */
function fetchThumbnail( popup, title, description ) {
	mwApi( {
		action: 'query',
		titles: title,
		format: 'json',
		formatversion: 2,
		prop: 'pageimages',
		piprop: 'thumbnail',
		pithumbsize: 300
	} ).then( function ( result ) {
		var thumbnail = result.query.pages[ 0 ].thumbnail;
		if ( thumbnail && thumbnail.source ) {
			popup.setContent( createPopupHtml(
				title,
				description,
				thumbnail
			) );
		}
	} );
}

/**
 * @class
 * @constructor
 * @param {boolean} [enableClustering]
 * @property {Object} nearbyLayers
 * @property {Set} knownTitles
 */
function Nearby( enableClustering ) {
	this.nearbyLayers = {};
	this.knownTitles = new Set();
	this.mapReloadNearbyButton = null;
	if ( enableClustering ) {
		this.initClusterMarkers();
	}
}

/**
 * @private
 */
Nearby.prototype.initClusterMarkers = function () {
	this.clusterMarkers = L.markerClusterGroup( {
		// TODO: We could decrease this further, but it breaks edge cases with a lot of markers
		spiderfyDistanceMultiplier: 0.8, // default: 1
		spiderLegPolylineOptions: {
			weight: 0, // default: 0.5
			color: '#222',
			opacity: 0.5
		},
		showCoverageOnHover: false, // default: true
		zoomToBoundsOnClick: true, // default
		maxClusterRadius: 15, // default: 80
		iconCreateFunction: this.createNearbyClusterMarker
	} );
};

/**
 * @private
 * @param {L.LatLngBounds} bounds
 * @return {number} Radius in meter
 */
Nearby.prototype.getDebouncedRadius = function ( bounds ) {
	// This corresponds to the smallest circle around the bounding rectangle, so some results will
	// be outside that visible rectangle
	var radius = Math.floor( bounds.getCenter().distanceTo( bounds.getSouthWest() ) );
	// Rounding to 2 significant digits means we loose +/-5% in the absolute worst case
	// eslint-disable-next-line no-bitwise
	return radius.toPrecision( 2 ) | 0;
};

/**
 * De-bounce point to a certain degree of accuracy depending on zoom factor.
 *
 * @private
 * @param {L.LatLng} point
 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
 * @return {L.LatLng}
 */
Nearby.prototype.getDebouncedPoint = function ( point, zoom ) {
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
};

/**
 * Building the search query. Includes calculations to debounce the input
 * from the bounding box.
 *
 * @private
 * @param {L.LatLngBounds} bounds
 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
 * @return {string}
 */
Nearby.prototype.getSearchQuery = function ( bounds, zoom ) {
	var radius = this.getDebouncedRadius( bounds ),
		center = this.getDebouncedPoint( bounds.getCenter(), zoom );
	radius = radius % 1000 ? radius + 'm' : Math.round( radius / 1000 ) + 'km';
	return 'nearcoord:' + radius + ',' + center.lat + ',' + center.lng;
};

/**
 * @param {string} title
 * @param {string} [description]
 * @param {Object} [thumbnail]
 * @return {string}
 */
function createPopupHtml( title, description, thumbnail ) {
	title = mw.Title.newFromText( title );

	var linkHtml = mw.html.element( 'a', {
			href: title.getUrl(),
			class: 'nearby-article-link',
			target: '_blank'
		}, title.getPrefixedText() ),
		titleHtml = mw.html.element( 'div', {
			class: 'marker-title'
		}, new mw.html.Raw( linkHtml ) ),
		contentHtml = '',
		thumbnailHtml = '';

	if ( description ) {
		contentHtml += mw.html.element( 'span', {}, description );
	}

	if ( thumbnail ) {
		thumbnailHtml += mw.html.element( 'img', {
			src: thumbnail.source,
			width: thumbnail.width || '',
			height: thumbnail.height || ''
		} );
	}

	return titleHtml + mw.html.element( 'div', {
		class: 'marker-description'
	}, new mw.html.Raw( contentHtml + thumbnailHtml ) );
}

/**
 * @private
 * @param {L.Map} map
 */
Nearby.prototype.initializeKnownPoints = function ( map ) {
	map.eachLayer( function ( layer ) {
		// Note: mapbox does simple checks like this in other places as well
		if ( layer.feature && layer.feature.properties && layer.feature.properties.title ) {
			this.knownTitles.add( layer.feature.properties.title );
		}
	}.bind( this ) );
};

/**
 * @private
 * @param {Object} geoJSON
 * @return {boolean}
 */
Nearby.prototype.filterDuplicatePoints = function ( geoJSON ) {
	if ( this.knownTitles.has( geoJSON.properties.title ) ) {
		return false;
	}
	this.knownTitles.add( geoJSON.properties.title );
	return true;
};

/**
 * @private
 * @param {Object} geoJSON
 * @param {L.LatLng} latlng
 * @return {L.Marker}
 */
Nearby.prototype.createNearbyMarker = function ( geoJSON, latlng ) {
	return L.marker( latlng, {
		icon: L.divIcon( {
			iconSize: [ 32, 32 ],
			popupAnchor: [ 0, -7 ],
			className: 'nearby-marker',
			html: '<div class="nearby-icon"></div>'
		} )
	} );
};

/**
 * @private
 * @param {L.MarkerCluster} cluster
 * @return {L.Marker}
 */
Nearby.prototype.createNearbyClusterMarker = function ( cluster ) {
	return L.divIcon( {
		iconSize: [ 40, 40 ],
		className: 'nearby-cluster',
		html: '<div class="nearby-icon">' + cluster.getChildCount() + '</div>'
	} );
};

/**
 * @param {L.Map} map
 * @param {boolean} show
 */
Nearby.prototype.toggleNearbyLayer = function ( map, show ) {
	this.map = map;

	if ( show ) {
		this.performanceStartTime = mw.now();
		this.seenArticleLink = false;
		this.seenMarkerPaint = false;
		this.initializeKnownPoints( map );
		this.fetchAndPopulateNearbyLayer( map );
		this.createReloadButton( map );
		map.on( {
			move: this.onMapMoveOrZoomEnd.bind( this, map ),
			zoomend: this.onMapMoveOrZoomEnd.bind( this, map )
		} );

		if ( this.clusterMarkers ) {
			map.addLayer( this.clusterMarkers );
		}
	} else {
		map.off( 'move zoomend' );

		for ( var i in this.nearbyLayers ) {
			if ( !this.clusterMarkers ) {
				map.removeLayer( this.nearbyLayers[ i ] );
			}
			delete this.nearbyLayers[ i ];
		}
		this.knownTitles.clear();

		if ( this.clusterMarkers ) {
			this.clusterMarkers.clearLayers();
			map.removeLayer( this.clusterMarkers );
		}

		this.mapReloadNearbyButton.$element.hide();
	}
};

/**
 * @param {L.Map} map
 */
Nearby.prototype.createReloadButton = function ( map ) {
	if ( map.mapReloadNearbyButton ) {
		return;
	}

	this.mapReloadNearbyButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'kartographer-sidebar-reload-nearbybutton' ),
		icon: 'reload',
		classes: [ 'mw-kartographer-reload-nearbybutton' ]
	} );

	this.mapReloadNearbyButton.connect( this, { click: 'reloadNearbyLayer' } );
	/* eslint-disable-next-line no-underscore-dangle */
	$( map._controlContainer ).append( this.mapReloadNearbyButton.$element );
	this.mapReloadNearbyButton.$element.hide();

};

/**
 * @private
 */
Nearby.prototype.onMapMoveOrZoomEnd = OO.ui.debounce( function () {
	this.mapReloadNearbyButton.$element.show();
}, 500 );

/**
 * @private
 */
Nearby.prototype.reloadNearbyLayer = function () {
	this.toggleNearbyLayer( this.map, false );
	this.toggleNearbyLayer( this.map, true );
	this.mapReloadNearbyButton.$element.hide();
};

/**
 * @private
 * @param {L.Map} map
 */
Nearby.prototype.fetchAndPopulateNearbyLayer = function ( map ) {
	this.fetch( map.getBounds(), map.getZoom() )
		.then( this.populateNearbyLayer.bind( this, map ) );
};

/**
 * @private
 * @param {L.LatLngBounds} bounds
 * @param {number} zoom Typically ranging from 0 (entire world) to 19 (nearest)
 * @return {jQuery.Promise}
 */
Nearby.prototype.fetch = function ( bounds, zoom ) {
	var limit = 100;
	// TODO: Cache results if bounds remains unchanged
	return mwApi( {
		action: 'query',
		format: 'json',
		formatversion: 2,
		prop: 'coordinates|pageprops|description',
		// co… arguments belong to prop=coordinates
		colimit: 'max',
		generator: 'search',
		// gsr… arguments belong to generator=search
		gsrsearch: this.getSearchQuery( bounds, zoom ),
		gsrnamespace: 0,
		gsrlimit: limit,
		// pp… arguments belong to prop=pageprops
		ppprop: 'displaytitle'
	} );
};

/**
 * @private
 * @param {L.Map} map
 * @param {Object} queryApiResponse
 */
Nearby.prototype.populateNearbyLayer = function ( map, queryApiResponse ) {
	var zoom = map.getZoom();
	var geoJSON = this.convertGeosearchToGeoJSON( queryApiResponse );

	if ( !this.nearbyLayers[ zoom ] ) {
		this.nearbyLayers[ zoom ] = this.createNearbyLayer( geoJSON );
		if ( !this.clusterMarkers ) {
			map.addLayer( this.nearbyLayers[ zoom ] );
		}
	} else {
		if ( this.clusterMarkers ) {
			this.clusterMarkers.removeLayers( this.nearbyLayers[ zoom ].getLayers() );
		}
		this.nearbyLayers[ zoom ].addData( geoJSON );
	}

	if ( this.clusterMarkers ) {
		this.clusterMarkers.addLayers( this.nearbyLayers[ zoom ].getLayers() );
	}

	if ( !this.seenMarkerPaint && mw.eventLog ) {
		var elapsedTime = Math.round( mw.now() - this.performanceStartTime );
		mw.eventLog.submit( 'mediawiki.maps_interaction', {
			$schema: '/analytics/mediawiki/maps/interaction/1.0.0',
			action: 'nearby-marker-paint',
			/* eslint-disable camelcase */
			initial_marker_count: geoJSON.length,
			initial_marker_time_ms: elapsedTime
			/* eslint-enable camelcase */
		} );
		this.seenMarkerPaint = true;
	}
};

/**
 * @private
 * @param {Object} response Raw data returned by the geosearch API.
 * @return {Object[]} A list of GeoJSON features, one for each page.
 */
Nearby.prototype.convertGeosearchToGeoJSON = function ( response ) {
	var pages = response.query && response.query.pages || [];

	return pages.reduce( function ( result, page ) {
		var coordinates = page.coordinates && page.coordinates[ 0 ];

		if ( coordinates ) {
			result.push( {
				type: 'Feature',
				geometry: { type: 'Point', coordinates: [ coordinates.lon, coordinates.lat ] },
				properties: {
					title: page.title,
					description: page.description,
					'marker-color': '0000ff'
				}
			} );
		}

		return result;
	}, [] );
};

/**
 * @private
 * @param {Object[]} geoJSON
 * @return {L.GeoJSON}
 */
Nearby.prototype.createNearbyLayer = function ( geoJSON ) {
	var self = this;
	return L.geoJSON( geoJSON, {
		filter: this.filterDuplicatePoints.bind( this ),
		pointToLayer: this.createNearbyMarker,
		onEachFeature: function ( feature, layer ) {
			layer.bindPopup( function () {
				return createPopupHtml(
					feature.properties.title,
					feature.properties.description
				);
			}, { closeButton: false } ).on( 'popupopen', function ( event ) {
				fetchThumbnail(
					event.popup,
					feature.properties.title,
					feature.properties.description
				);
				$( event.popup.getElement() ).find( '.nearby-article-link' )
					.on( 'click', function () {
						if ( !self.seenArticleLink ) {
							if ( mw.eventLog ) {
								mw.eventLog.submit( 'mediawiki.maps_interaction', {
									$schema: '/analytics/mediawiki/maps/interaction/1.0.0',
									action: 'nearby-link-click'
								} );
							}
							self.seenArticleLink = true;
						}
					} );
			} );
		}
	} );
};

module.exports = Nearby;
