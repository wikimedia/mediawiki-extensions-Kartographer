( function ( $, mw ) {

	// Load this script after lib/mapbox-lib.js

	var scale, urlFormat,
		mapServer = mw.config.get( 'wgKartographerMapServer' ),
		forceHttps = mapServer[ 4 ] === 's',
		config = L.mapbox.config;

	config.REQUIRE_ACCESS_TOKEN = false;
	config.FORCE_HTTPS = forceHttps;
	config.HTTP_URL = forceHttps ? false : mapServer;
	config.HTTPS_URL = !forceHttps ? false : mapServer;

	function bracketDevicePixelRatio() {
		var i, scale,
			brackets = mw.config.get( 'wgKartographerSrcsetScales' ),
			baseRatio = window.devicePixelRatio || 1;
		if ( !brackets ) {
			return 1;
		}
		brackets.unshift( 1 );
		for ( i = 0; i < brackets.length; i++ ) {
			scale = brackets[ i ];
			if ( scale >= baseRatio || ( baseRatio - scale ) < 0.1 ) {
				return scale;
			}
		}
		return brackets[ brackets.length - 1 ];
	}

	scale = bracketDevicePixelRatio();
	scale = ( scale === 1 ) ? '' : ( '@' + scale + 'x' );
	urlFormat = '/{z}/{x}/{y}' + scale + '.png';

	mw.kartographer = {};

	/**
	 * Create a new interactive map
	 *
	 * @param {HTMLElement} container Map container
	 * @param {Object} data Map data
	 * @param {number} data.latitude Latitude
	 * @param {number} data.longitude Longitude
	 * @param {number} data.zoom Zoom
	 * @param {string} [data.style] Map style
	 * @param {Object} [data.geoJson] Raw GeoJSON
	 * @param {Object} [data.overlays] Overlays
	 * @return {L.mapbox.Map} Map object
	 */
	mw.kartographer.createMap = function ( container, data ) {
		var geoJson, map,
			style = data.style || mw.config.get( 'wgKartographerDfltStyle' ),
			mapData = mw.config.get( 'wgKartographerLiveData' ) || {};

		map = L.map( container );
		if ( !container.clientWidth ) {
			// HACK: If the container is not naturally measureable, try jQuery
			// which will pick up CSS dimensions. T125263
			/*jscs:disable disallowDanglingUnderscores */
			map._size = new L.Point(
				$( container ).width(),
				$( container ).height()
			);
			/*jscs:enable disallowDanglingUnderscores */
		}
		map.setView( [ data.latitude, data.longitude ], data.zoom );
		map.attributionControl.setPrefix( '' );
		L.tileLayer( mapServer + '/' + style + urlFormat, {
			maxZoom: 18,
			attribution: mw.message( 'kartographer-attribution' ).parse()
		} ).addTo( map );

		geoJson = data.geoJson || [];

		if ( data.overlays ) {
			geoJson = [];
			$.each( data.overlays, function ( _, group ) {
				if ( group === '*' ) {
					$.each( mapData, function ( k, d ) {
						if ( k[ 0 ] !== '_' ) {
							geoJson = geoJson.concat( d );
						}
					} );
				} else if ( mapData.hasOwnProperty( group ) ) {
					geoJson = geoJson.concat( mapData[ group ] );
				}
			} );
		}
		if ( geoJson.length ) {
			mw.kartographer.setGeoJson( map, geoJson );
		}

		return map;
	};

	/**
	 * Get GeoJSON layer for the specified map.
	 *
	 * If a layer doesn't exist, create and attach one.
	 *
	 * @param {L.mapbox.Map} map Map to get layers from
	 * @return {L.mapbox.FeatureLayer|null} GeoJSON layer, if present
	 */
	mw.kartographer.getGeoJsonLayer = function ( map ) {
		var geoJsonLayer = null;
		map.eachLayer( function ( layer ) {
			if ( !geoJsonLayer && layer instanceof L.mapbox.FeatureLayer && layer.getGeoJSON() ) {
				geoJsonLayer = layer;
			}
		} );
		return geoJsonLayer;
	};

	/**
	 * Set the GeoJSON for a map, removing any existing GeoJSON layer.
	 *
	 * @param {L.mapbox.Map} map Map to set the GeoJSON for
	 * @param {Object|null} geoJson GeoJSON data, or null to clear
	 * @return {boolean} The GeoJSON provided was valid as was applied
	 */
	mw.kartographer.setGeoJson = function ( map, geoJson ) {
		var geoJsonLayer = mw.kartographer.getGeoJsonLayer( map ),
			isNew = !geoJsonLayer;

		if ( isNew ) {
			geoJsonLayer = L.mapbox.featureLayer();
		}

		if ( geoJson ) {
			try {
				geoJsonLayer.setGeoJSON( geoJson );
			} catch ( e ) {
				// Invalid GeoJSON
				return false;
			}
		} else {
			map.removeLayer( geoJsonLayer );
		}

		// Only attach new layer once GeoJSON has been set
		if ( isNew ) {
			map.addLayer( geoJsonLayer );
		}

		return true;
	};

	/**
	 * Set the GeoJSON for a map as string
	 *
	 * @param {L.mapbox.Map} map Map to set the GeoJSON for
	 * @param {string} geoJsonString GeoJSON data, empty string to clear
	 * @return {boolean} The GeoJSON string provided was valid as was applied
	 */
	mw.kartographer.setGeoJsonString = function ( map, geoJsonString ) {
		var geoJson;

		if ( geoJsonString ) {
			try {
				geoJson = JSON.parse( geoJsonString );
			} catch ( e ) {
				// Invalid JSON
				return false;
			}
		} else {
			// If the input string is empty, pass null to #setGeoJson to clear
			geoJson = null;
		}

		return mw.kartographer.setGeoJson( map, geoJson );
	};

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.mw-kartographer-interactive' ).each( function () {
			var $this = $( this );

			// Prevent users from adding map divs directly via wikitext
			if ( $this.attr( 'mw-data' ) !== 'interface' ) {
				return;
			}

			mw.kartographer.createMap( this, {
				latitude: +$this.data( 'lat' ),
				longitude: +$this.data( 'lon' ),
				zoom: +$this.data( 'zoom' ),
				style: $this.data( 'style' ),
				overlays: $this.data( 'overlays' )
			} );
		} );
	} );
}( jQuery, mediaWiki ) );
