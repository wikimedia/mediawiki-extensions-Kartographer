( function ( $, mw ) {

	// Load this script after lib/mapbox-lib.js
	var scale, urlFormat, windowManager, mapDialog,
		mapServer = mw.config.get( 'wgKartographerMapServer' ),
		forceHttps = mapServer[ 4 ] === 's',
		config = L.mapbox.config,
		router = mw.loader.require( 'mediawiki.router' );

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
	 * References the map containers of the page.
	 *
	 * @type {HTMLElement[]}
	 */
	mw.kartographer.maps = [];

	mw.kartographer.FullScreenControl = L.Control.extend( {
		options: {
			// Do not switch for RTL because zoom also stays in place
			position: 'topright'
		},

		onAdd: function ( map ) {
			var container = L.DomUtil.create( 'div', 'leaflet-bar' ),
				link = L.DomUtil.create( 'a', 'oo-ui-icon-fullScreen', container );

			this.href = link.href = '#' + mw.kartographer.getMapHash( this.options.mapData, this.map );
			link.title = mw.msg( 'kartographer-fullscreen-text' );
			this.map = map;

			L.DomEvent.addListener( link, 'click', this.onShowFullScreen, this );
			L.DomEvent.disableClickPropagation( container );

			return container;
		},

		onShowFullScreen: function ( e ) {
			var hash = mw.kartographer.getMapHash( this.options.mapData, this.map );
			L.DomEvent.stop( e );

			this.href = '#' + hash;

			if ( router.isSupported() ) {
				router.navigate( hash );
			} else {
				mw.kartographer.openFullscreenMap( this.map, getMapPosition( this.map ) );
			}
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
			$container = $( container ),
			style = data.style || mw.config.get( 'wgKartographerDfltStyle' ),
			width, height;

		$container.addClass( 'mw-kartographer-map' );

		map = L.map( container );

		if ( !container.clientWidth ) {
			// Get `max` properties in case the container was wrapped
			// with {@link #responsiveContainerWrap}.
			width = $container.css( 'max-width' );
			height = $container.css( 'max-height' );
			width = ( !width || width === 'none' ) ? $container.width() : width;
			height = ( !height || height === 'none' ) ? $container.height() : height;

			// HACK: If the container is not naturally measurable, try jQuery
			// which will pick up CSS dimensions. T125263
			/*jscs:disable disallowDanglingUnderscores */
			map._size = new L.Point( width, height );
			/*jscs:enable disallowDanglingUnderscores */
		}
		map.setView( [ data.latitude, data.longitude ], data.zoom, true );
		map.attributionControl.setPrefix( '' );

		if ( data.enableFullScreenButton ) {
			map.addControl( new mw.kartographer.FullScreenControl( {
				mapData: data
			} ) );
		}

		/**
		 * @property {L.TileLayer} Reference to `Wikimedia` tile layer.
		 */
		map.wikimediaLayer = L.tileLayer( mapServer + '/' + style + urlFormat, {
			maxZoom: 18,
			attribution: mw.message( 'kartographer-attribution' ).parse()
		} ).addTo( map );

		/**
		 * @property {Object} Hash map of data groups and their corresponding
		 *   {@link L.mapbox.FeatureLayer layers}.
		 */
		map.dataLayers = {};

		if ( data.overlays ) {

			getMapGroupData( data.overlays ).done( function ( mapData ) {
				$.each( data.overlays, function ( index, group ) {
					if ( !$.isEmptyObject( mapData[ group ] ) ) {
						map.dataLayers[ group ] = mw.kartographer.addDataLayer( map, mapData[ group ] );
					} else {
						mw.log.warn( 'Layer not found or contains no data: "' + group + '"' );
					}
				} );
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
	 * Updates "editable" GeoJSON layer from a string.
	 *
	 * Validates the GeoJSON against the `sanitize-mapdata` api
	 * before executing it.
	 *
	 * The deferred object will be resolved with a `boolean` flag
	 * indicating whether the GeoJSON was valid and was applied.
	 *
	 * @param {L.mapbox.Map} map Map to set the GeoJSON for
	 * @param {string} geoJsonString GeoJSON data, empty string to clear
	 * @return {jQuery.Promise} Promise which resolves when the GeoJSON is updated, and rejects if there was an error
	 */
	mw.kartographer.updateKartographerLayer = function ( map, geoJsonString ) {
		var deferred = $.Deferred();

		if ( geoJsonString === '' ) {
			return deferred.resolve().promise();
		}

		new mw.Api().post( {
			action: 'sanitize-mapdata',
			text: geoJsonString,
			title: mw.config.get( 'wgPageName' )
		} ).done( function ( resp ) {
			var geoJson, layer,
				data = resp[ 'sanitize-mapdata' ];

			geoJsonString = data && data.sanitized;

			if ( geoJsonString && !data.error ) {
				try {
					geoJson = JSON.parse( geoJsonString );
					layer = mw.kartographer.getKartographerLayer( map );
					layer.setGeoJSON( geoJson );
					deferred.resolve();
				} catch ( e ) {
					deferred.reject( e );
				}
			} else {
				deferred.reject();
			}
		} );

		return deferred.promise();
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
	 * Opens a full screen map.
	 *
	 * This method loads dependencies asynchronously. While these scripts are
	 * loading, more calls to this method can be made. We only need to resolve
	 * the last one. To make sure we only load the last map requested, we keep
	 * an increment of the calls being made.
	 *
	 * @param {L.Map|Object} mapData Map object to get data from, or raw map data.
	 * @param {Object} [fullScreenState] Optional full screen position in which to
	 *   open the map.
	 * @param {number} [fullScreenState.zoom]
	 * @param {number} [fullScreenState.latitude]
	 * @param {number} [fullScreenState.longitude]
	 */
	mw.kartographer.openFullscreenMap = ( function () {

		var counter = -1;

		return function ( mapData, fullScreenState ) {
			var id = ++counter;

			mw.loader.using( 'ext.kartographer.fullscreen' ).done( function () {

				var map, dialogData = {};

				if ( counter > id ) {
					return;
				}

				if ( mapData instanceof L.Map ) {
					map = mapData;
					mapData = getMapData( $( map.getContainer() ).closest( '.mw-kartographer-interactive' ) );
				} else if ( $.type( mapData.articleMapId ) === 'number' ) {
					map = mw.kartographer.maps[ mapData.articleMapId ];
				}

				$.extend( dialogData, mapData, {
					fullScreenState: fullScreenState,
					enableFullScreenButton: false
				} );

				if ( mapDialog ) {
					mapDialog.changeMap( dialogData );
					return;
				}
				getWindowManager()
					.openWindow( mapDialog, dialogData )
					.then( function ( opened ) { return opened; } )
					.then( function ( closing ) {
						if ( map ) {
							map.setView(
								mapDialog.map.getCenter(),
								mapDialog.map.getZoom()
							);
						}
						windowManager = mapDialog = null;
						return closing;
					} );
			} );
		};
	} )();

	/**
	 * Formats the full screen route of the map, such as:
	 *   `/map/:articleMapId(/:zoom/:longitude/:latitude)`
	 *
	 * The hash will contain the portion between parenthesis if and only if
	 * one of these 3 values differs from the initial setting.
	 *
	 * @param {Object} data Map data.
	 * @param {L.mapbox.Map} [map] When a map object is passed, the method will
	 *   read the current zoom and center from the map object.
	 * @return {string} The route to open the map in full screen mode.
	 */
	mw.kartographer.getMapHash = function ( data, map ) {
		var hash = '/map/' + data.articleMapId,
			mapPosition,
			newHash,
			initialHash = getScaleCoords( data.zoom, data.latitude, data.longitude ).join( '/' );

		if ( map ) {
			mapPosition = getMapPosition( map );
			newHash = getScaleCoords( mapPosition.zoom, mapPosition.latitude, mapPosition.longitude ).join( '/' );

			if ( newHash !== initialHash ) {
				hash += '/' + newHash;
			}
		}

		return hash;
	};

	/**
	 * Convenient method that gets the current position of the map.
	 *
	 * @return {Object} Object with the zoom, the latitude and the longitude.
	 * @return {number} return.zoom
	 * @return {number} return.latitude
	 * @return {number} return.longitude
	 * @private
	 */
	function getMapPosition( map ) {
		var center = map.getCenter();
		return { zoom: map.getZoom(), latitude: center.lat, longitude: center.lng };
	}

	/**
	 * Convenient method that formats the coordinates based on the zoom level.
	 *
	 * @param {number} zoom
	 * @param {number} lat
	 * @param {number} lng
	 * @return {Array} Array with the zoom (number), the latitude (string) and
	 *   the longitude (string).
	 * @private
	 */
	function getScaleCoords( zoom, lat, lng ) {
		var precisionPerZoom = [ 0, 0, 1, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 4, 4, 4, 4, 5, 5 ];

		return [
			zoom,
			lat.toFixed( precisionPerZoom[ zoom ] ),
			lng.toFixed( precisionPerZoom[ zoom ] )
		];
	}

	/**
	 * Gets the map data attached to an element.
	 *
	 * @param {HTMLElement} element Element
	 * @return {Object|null} Map properties
	 * @return {number} return.latitude Latitude
	 * @return {number} return.longitude Longitude
	 * @return {number} return.zoom Zoom level
	 * @return {string} return.style Map style
	 * @return {string[]} return.overlays Overlay groups
	 * @private
	 */
	function getMapData( element ) {
		var $el = $( element ),
			articleMapId = null;
		// Prevent users from adding map divs directly via wikitext
		if ( $el.attr( 'mw-data' ) !== 'interface' ) {
			return null;
		}

		if ( $.type( $el.data( 'article-map-id' ) ) !== 'undefined' ) {
			articleMapId = +$el.data( 'article-map-id' );
		}

		return {
			articleMapId: articleMapId,
			latitude: +$el.data( 'lat' ),
			longitude: +$el.data( 'lon' ),
			zoom: +$el.data( 'zoom' ),
			style: $el.data( 'style' ),
			overlays: $el.data( 'overlays' )
		};
	}

	/**
	 * Returns the map data for the page.
	 *
	 * If the data is not already loaded (`wgKartographerLiveData`), an
	 * asynchronous request will be made to fetch the missing groups.
	 * The new data is then added to `wgKartographerLiveData`.
	 *
	 * @param {string[]} overlays Overlay group names
	 * @return {jQuery.Promise} Promise which resolves with the group data, an object keyed by group name
	 * @private
	 */
	function getMapGroupData( overlays ) {
		var deferred = $.Deferred(),
			groupsLoaded = mw.config.get( 'wgKartographerLiveData' ) || {},
			groupsToLoad = [];

		$( overlays ).each( function ( key, value ) {
			if ( !( value in groupsLoaded ) ) {
				groupsToLoad.push( value );
			}
		} );

		if ( !groupsToLoad.length ) {
			return deferred.resolve( groupsLoaded ).promise();
		}

		new mw.Api().get( {
			action: 'query',
			formatversion: '2',
			titles: mw.config.get( 'wgPageName' ),
			prop: 'mapdata',
			mpdgroups: groupsToLoad.join( '|' )
		} ).done( function ( data ) {
			var rawMapData = data.query.pages[ 0 ].mapdata,
				mapData = rawMapData && JSON.parse( rawMapData ) || {};

			$.extend( groupsLoaded, mapData );
			mw.config.set( 'wgKartographerLiveData', groupsLoaded );

			deferred.resolve( groupsLoaded );
		} );

		return deferred.promise();
	}

	/**
	 * Wraps a map container to make it (and its map) responsive on
	 * mobile (MobileFrontend).
	 *
	 * The initial `mapContainer`:
	 *
	 *     <div class="mw-kartographer-interactive" style="height: Y; width: X;">
	 *         <!-- this is the component carrying Leaflet.Map -->
	 *     </div>
	 *
	 * Becomes :
	 *
	 *     <div class="mw-kartographer-interactive mw-kartographer-responsive" style="max-height: Y; max-width: X;">
	 *         <div class="mw-kartographer-responder" style="padding-bottom: (100*Y/X)%">
	 *             <div>
	 *                 <!-- this is the component carrying Leaflet.Map -->
	 *             </div>
	 *         </div>
	 *     </div>
	 *
	 * **Note:** the container that carries the map data remains the initial
	 * `mapContainer` passed in arguments. Its selector remains `.mw-kartographer-interactive`.
	 * However it is now a sub-child that carries the map.
	 *
	 * **Note 2:** the CSS applied to these elements vary whether the map width
	 * is absolute (px) or relative (%). The example above describes the absolute
	 * width case.
	 *
	 * @param {HTMLElement} mapContainer Initial component to carry the map.
	 * @return {HTMLElement} New map container to carry the map.
	 */
	function responsiveContainerWrap( mapContainer ) {
		var $container = $( mapContainer ),
			$responder, $map,
			width = mapContainer.style.width,
			isRelativeWidth = width.slice( -1 ) === '%',
			height = +( mapContainer.style.height.slice( 0, -2 ) ),
			containerCss, responderCss;

		// Convert the value to a string.
		width = isRelativeWidth ? width : +( width.slice( 0, -2 ) );

		if ( isRelativeWidth ) {
			containerCss = {};
			responderCss = {
				// The inner container must occupy the full height
				height: height
			};
		} else {
			containerCss = {
				// Remove explicitly set dimensions
				width: '',
				height: '',
				// Prevent over-sizing
				'max-width': width,
				'max-height': height
			};
			responderCss = {
				// Use padding-bottom trick to maintain original aspect ratio
				'padding-bottom': ( 100 * height / width ) + '%'
			};
		}
		$container.addClass( 'mw-kartographer-responsive' ).css( containerCss );
		$responder = $( '<div>' ).addClass( 'mw-kartographer-responder' ).css( responderCss );

		$map = $( '<div>' );
		$container.append( $responder.append( $map ) );
		return $map[ 0 ];
	}

	/**
	 * This code will be executed once the article is rendered and ready.
	 */
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var mapsInArticle = [],
			isMobile = mw.config.get( 'skin' ) === 'minerva';

		// Some links might be displayed outside of $content, so we need to
		// search outside. This is an anti-pattern and should be improved...
		// Meanwhile #content is better than searching the full document.
		$( '#content' ).on( 'click', '.mw-kartographer-link', function () {
			var data = getMapData( this );

			if ( data ) {
				mw.kartographer.openFullscreenMap( data );
			}
		} );

		L.Map.mergeOptions( {
			sleepTime: 250,
			wakeTime: 1000,
			sleepNote: false,
			sleepOpacity: 1
		} );

		$content.find( '.mw-kartographer-interactive' ).each( function ( index ) {
			var map, data,
				container = this,
				$container = $( this );

			$container.data( 'article-map-id', index );
			data = getMapData( container );

			if ( data ) {
				data.enableFullScreenButton = true;

				if ( isMobile ) {
					container = responsiveContainerWrap( container );
				}

				map = mw.kartographer.createMap( container, data );
				map.doubleClickZoom.disable();

				mapsInArticle.push( map );
				mw.kartographer.maps[ index ] = map;

				$container.on( 'dblclick', function () {
					if ( router.isSupported() ) {
						router.navigate( mw.kartographer.getMapHash( data, map ) );
					} else {
						mw.kartographer.openFullscreenMap( map, getMapPosition( map ) );
					}
				} );

				// Special case for collapsible maps.
				// When the container is hidden Leaflet is not able to
				// calculate the expected size when visible. We need to force
				// updating the map to the new container size on `expand`.
				if ( !$container.is( ':visible' ) ) {
					$container.closest( '.mw-collapsible' )
						.on( 'afterExpand.mw-collapsible', function () {
							map.invalidateSize();
						} );
				}
			}
		} );

		// Allow customizations of interactive maps in article.
		mw.hook( 'wikipage.maps' ).fire( mapsInArticle, false /* isFullScreen */ );

		// Opens map in full screen. #/map(/:zoom)(/:latitude)(/:longitude)
		// Examples:
		//     #/map/0
		//     #/map/0/5
		//     #/map/0/16/-122.4006/37.7873
		router.route( /map\/([0-9]+)(?:\/([0-9]+))?(?:\/([\-\+]?\d+\.?\d{0,5})?\/([\-\+]?\d+\.?\d{0,5})?)?/, function ( mapId, zoom, latitude, longitude ) {
			var map = mw.kartographer.maps[ mapId ],
				fullScreenState = {};

			if ( zoom !== undefined && zoom >= 0 && zoom <= 18 ) {
				fullScreenState.zoom = +zoom;
			}
			if ( longitude !== undefined ) {
				fullScreenState.latitude = +latitude;
				fullScreenState.longitude = +longitude;
			}
			mw.kartographer.openFullscreenMap( map, fullScreenState );
		} );

		// Check if we need to open a map in full screen.
		router.checkRoute();

		// Add index route.
		router.route( '', function () {
			if ( mapDialog ) {
				mapDialog.close();
			}
		} );
	} );
}( jQuery, mediaWiki ) );
