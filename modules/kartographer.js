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
	 * @return {L.map} Map object
	 */
	mw.kartographer.createMap = function ( container, data ) {
		var dataLayer, geoJson, map,
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
			dataLayer = L.mapbox.featureLayer().addTo( map );
			dataLayer.setGeoJSON( geoJson );
		}

		return map;
	};

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.mw-kartographer-interactive' ).each( function () {
			var $this = $( this );

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
