( function ( $, mw ) {

	// Load this script after lib/mapbox-lib.js

	var scale, urlFormat, windowManager, mapDialog,
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

	mw.kartographer.FullScreenControl = L.Control.extend( {
		options: {
			// Do not switch for RTL because zoom also stays in place
			position: 'topright'
		},

		onAdd: function ( map ) {
			var container = L.DomUtil.create( 'div', 'leaflet-control-mapbox-share leaflet-bar' ),
				link = L.DomUtil.create( 'a', 'mapbox-share mapbox-icon mapbox-icon-share', container );

			link.href = '#';
			link.title = mw.msg( 'kartographer-fullscreen-text' );
			this.map = map;

			L.DomEvent.addListener( link, 'click', this.onShowFullScreen, this );
			L.DomEvent.disableClickPropagation( container );

			return container;
		},

		onShowFullScreen: function ( e ) {
			L.DomEvent.stop( e );
			mw.kartographer.openFullscreenMap( this.options.mapPositionData, this.map );
		}
	} );

	/**
	 * Create a new interactive map
	 *
	 * @param {HTMLElement} container Map container
	 * @param {Object} data Map data
	 * @param {number} data.latitude Latitude
	 * @param {number} data.longitude Longitude
	 * @param {number} data.zoom Zoom
	 * @param {string} [data.style] Map style
	 * @param {string[]} [data.overlays] Names of overlay groups to show
	 * @param {boolean} [data.enableFullScreenButton] add zoom
	 * @return {L.mapbox.Map} Map object
	 */
	mw.kartographer.createMap = function ( container, data ) {
		var map,
			style = data.style || mw.config.get( 'wgKartographerDfltStyle' ),
			mapData = mw.config.get( 'wgKartographerLiveData' ) || {};

		map = L.map( container );
		if ( !container.clientWidth ) {
			// HACK: If the container is not naturally measurable, try jQuery
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

		if ( data.enableFullScreenButton ) {
			map.addControl( new mw.kartographer.FullScreenControl( { mapPositionData: data } ) );
		}

		L.tileLayer( mapServer + '/' + style + urlFormat, {
			maxZoom: 18,
			attribution: mw.message( 'kartographer-attribution' ).parse()
		} ).addTo( map );

		if ( data.overlays ) {
			$.each( data.overlays, function ( index, group ) {
				if ( mapData.hasOwnProperty( group ) ) {
					mw.kartographer.addDataLayer( map, mapData[ group ] );
				} else {
					mw.log( 'Layer not found "' + group + '"' );
				}
			} );
		}

		return map;
	};

	mw.kartographer.dataLayerOpts = {
		// Disable double-sanitization by mapbox's internal sanitizer
		// because geojson has already passed through the MW internal sanitizer
		sanitizer: function ( v ) {
			return v;
		}
	};

	/**
	 * Create a new GeoJSON layer and add it to map.
	 *
	 * @param {L.mapbox.Map} map Map to get layers from
	 * @param {Object} geoJson
	 */
	mw.kartographer.addDataLayer = function ( map, geoJson ) {
		try {
			return L.mapbox.featureLayer( geoJson, mw.kartographer.dataLayerOpts ).addTo( map );
		} catch ( e ) {
			mw.log( e );
		}
	};

	/**
	 * Get "editable" geojson layer for the map.
	 *
	 * If a layer doesn't exist, create and attach one.
	 *
	 * @param {L.mapbox.Map} map Map to get layers from
	 * @param {L.mapbox.FeatureLayer} map.kartographerLayer show tag-specific info in this layer
	 * @return {L.mapbox.FeatureLayer|null} GeoJSON layer, if present
	 */
	mw.kartographer.getKartographerLayer = function ( map ) {
		if ( !map.kartographerLayer ) {
			map.kartographerLayer = L.mapbox.featureLayer().addTo( map );
		}
		return map.kartographerLayer;
	};

	/**
	 * Update "editable" geojson layer from a string
	 *
	 * @param {L.mapbox.Map} map Map to set the GeoJSON for
	 * @param {string} geoJsonString GeoJSON data, empty string to clear
	 * @return {boolean} The GeoJSON string provided was valid as was applied
	 */
	mw.kartographer.updateKartographerLayer = function ( map, geoJsonString ) {
		var geoJson, layer, isValid = true;

		if ( geoJsonString ) {
			try {
				geoJson = JSON.parse( geoJsonString );
			} catch ( e ) {
				// Invalid JSON, clear it
				isValid = false;
			}
		}

		try {
			layer = mw.kartographer.getKartographerLayer( map );
			layer.setGeoJSON( !isValid || geoJson === undefined ? [] : geoJson );
			return isValid;
		} catch ( e ) {
			return false;
		}
	};

	function getWindowManager() {
		if ( !windowManager ) {
			windowManager = new OO.ui.WindowManager();
			mapDialog = new mw.kartographer.MapDialog();
			$( 'body' ).append( windowManager.$element );
			windowManager.addWindows( [ mapDialog ] );
		}
		return windowManager;
	}

	/**
	 * Open a full screen map
	 *
	 * @param {Object} data Map data
	 * @param {L.mapbox.Map} [map] Optional map to get current state from
	 */
	mw.kartographer.openFullscreenMap = function ( data, map ) {
		mw.loader.using( 'ext.kartographer.fullscreen' ).done( function () {
			var center;
			if ( map ) {
				center = map.getCenter();
				data.latitude = center.lat;
				data.longitude = center.lng;
				data.zoom = map.getZoom();
			}
			// full screen map should never show "full screen" button
			data.enableFullScreenButton = false;
			getWindowManager()
				.openWindow( mapDialog, data )
				.then( function ( opened ) { return opened; } )
				.then( function ( closing ) {
					if ( map ) {
						map.setView( mapDialog.map.getCenter(), mapDialog.map.getZoom() );
					}
					return closing;
				} );
		} );
	};

	function getMapData( $el ) {
		// Prevent users from adding map divs directly via wikitext
		if ( $el.attr( 'mw-data' ) !== 'interface' ) {
			return;
		}

		return {
			latitude: +$el.data( 'lat' ),
			longitude: +$el.data( 'lon' ),
			zoom: +$el.data( 'zoom' ),
			style: $el.data( 'style' ),
			overlays: $el.data( 'overlays' )
		};
	}

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		$content.find( '.mw-kartographer-link' ).each( function () {
			var $this = $( this ),
				data = getMapData( $this );

			if ( data ) {
				$this.on( 'click', function () {
					mw.kartographer.openFullscreenMap( data );
					return false;
				} );
			}
		} );

		L.Map.mergeOptions( {
			sleepTime: 250,
			wakeTime: 1000,
			sleepNote: false,
			sleepOpacity: 1
		} );

		$content.find( '.mw-kartographer-interactive' ).each( function () {
			var map,
				$this = $( this ),
				data = getMapData( $this );

			if ( data ) {
				data.enableFullScreenButton = true;
				map = mw.kartographer.createMap( this, data );
				map.doubleClickZoom.disable();

				mw.hook( 'wikipage.maps' ).fire( map, false /* isFullScreen */ );

				$this.on( 'dblclick', function () {
					mw.kartographer.openFullscreenMap( data, map );
				} );
			}
		} );
	} );
}( jQuery, mediaWiki ) );
