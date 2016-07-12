/* globals module */
module.MWMap = ( function ( FullScreenControl, dataLayerOpts, ControlScale ) {

	var scale, urlFormat,
		mapServer = mw.config.get( 'wgKartographerMapServer' ),
		worldLatLng = new L.LatLngBounds( [ -90, -180 ], [ 90, 180 ] ),
		MWMap;

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

	L.Map.mergeOptions( {
		sleepTime: 250,
		wakeTime: 1000,
		sleepNote: false,
		sleepOpacity: 1,
		// the default zoom applied when `longitude` and `latitude` were
		// specified, but zoom was not.
		fallbackZoom: 13
	} );

	/*jscs:disable disallowDanglingUnderscores */
	/**
	 * Interactive map object
	 *
	 * @param {HTMLElement} container Map container
	 * @param {Object} data Map data
	 * @param {number} data.latitude Latitude
	 * @param {number} data.longitude Longitude
	 * @param {number} data.zoom Zoom
	 * @param {string} [data.style] Map style
	 * @param {string[]} [data.overlays] Names of overlay groups to show
	 * @param {boolean} [data.enableFullScreenButton] add zoom
	 */
	MWMap = function ( container, data ) {
		this.container = container;
		this.$container = $( container );

		this.$container.addClass( 'mw-kartographer-map' );
		this._data = data;
		this.map = L.map( this.container );

		if ( !this.container.clientWidth ) {
			this._fixMapSize();
		}

		this.loaded = this._initMap();
	};

	/**
	 * Initializes the map:
	 *
	 * - Adds the base tile layer
	 * - Fetches groups data
	 * - Adds data layers
	 *
	 * @return {jQuery.Promise} Promise which resolves once the map is
	 *   initialized.
	 * @private
	 */
	MWMap.prototype._initMap = function () {
		var style = this._data.style || mw.config.get( 'wgKartographerDfltStyle' );

		/**
		 * @property {L.TileLayer} Reference to `Wikimedia` tile layer.
		 */
		this.map.wikimediaLayer = L.tileLayer( mapServer + '/' + style + urlFormat, {
			maxZoom: 18,
			attribution: mw.message( 'kartographer-attribution' ).parse()
		} ).addTo( this.map );

		return this._initGroupData();
	};

	/**
	 * Primary function to use right after you instantiated a `MWMap`
	 * to get the map ready and be able to extend the {@link #map} object.
	 *
	 * Use it to add handlers to be called, once the map is ready, with
	 * the {@link #map map object} and updated {@link #_data map data}.
	 *
	 * *Notes on updated map data:*
	 * - During the "init" phase, the map downloads its data asynchronously.
	 * - During the "ready" phase, the map positions its center and sets its
	 * zoom according to the initial map data. The map is able to position
	 * itself and calculate its best zoom in case longitude/latitude/zoom were
	 * not explicitly defined. See with the code below how you can retrieve
	 * the `map` and `mapData` objects:
	 *
	 *     var MWMap = new MWMap( this.$map[ 0 ], initialMapData );
	 *     MWMap.ready(function(map, mapData) {
		 *         // `map` is the map object
		 *         // `mapData` is the updated mapData object.
		 *     });
	 *
	 * @param {jQuery.Promise} promise Promise which resolves with the map object and
	 *   the updated map data once the map is ready.
	 * @return {jQuery.Promise.then} Function to add handlers to be
	 *   called once the map is ready.
	 */
	MWMap.prototype.ready = function ( promise ) {
		this.ready = $.when( this.loaded )
			.then( this._initMapPosition.bind( this ) )
			.then( this._initControls )
			.then( promise )
			.then;
		return this.ready;
	};

	/**
	 * Initializes map data:
	 *
	 * - Fetches groups data
	 * - Adds data layers
	 *
	 * @return {jQuery.Promise} Promise which resolves once the data is loaded
	 *   and the data layers added to the map.
	 * @private
	 */
	MWMap.prototype._initGroupData = function () {
		var deferred = $.Deferred(),
			self = this,
			map = this.map,
			data = this._data;

		/**
		 * @property {Object} Hash map of data groups and their corresponding
		 *   {@link L.mapbox.FeatureLayer layers}.
		 */
		map.dataLayers = {};

		data.overlays = data.overlays || [];

		getMapGroupData( data.overlays ).then( function ( mapData ) {
			$.each( data.overlays, function ( index, group ) {
				if ( !$.isEmptyObject( mapData[ group ] ) ) {
					map.dataLayers[ group ] = self.addDataLayer( map, mapData[ group ] );
				} else {
					mw.log.warn( 'Layer not found or contains no data: "' + group + '"' );
				}
			} );
			deferred.resolve().promise();
		} );
		return deferred.promise();
	};

	/**
	 * Init map position with {@link #setView}.
	 *
	 * @return {jQuery.Promise} Promise which resolves with the updated map
	 *   data.
	 * @private
	 */
	MWMap.prototype._initMapPosition = function () {
		var data = this._data;

		this.setView( [ data.latitude, data.longitude ], data.zoom, true, true );
		return $.Deferred().resolveWith( this, [ this.map, this._data ] ).promise();
	};

	/**
	 * Init map controls.
	 *
	 * - Adds the "attribution" control.
	 * - Adds the "full screen" button control when `enableFullScreenButton` is
	 *   truthy.
	 *
	 * @private
	 */
	MWMap.prototype._initControls = function () {
		this.map.attributionControl.setPrefix( '' );

		if ( this._data.enableFullScreenButton ) {
			this.map.addControl( new FullScreenControl( {
				mapData: this._data
			} ) );
		}

		this.map.addControl( new ControlScale( { position: 'bottomright' } ) );

		return $.Deferred().resolveWith( this, [ this.map, this._data ] ).promise();
	};

	/**
	 * Create a new GeoJSON layer and add it to map.
	 *
	 * @param {L.mapbox.Map} map Map to get layers from
	 * @param {Object} geoJson
	 */
	MWMap.prototype.addDataLayer = function ( map, geoJson ) {
		try {
			return L.mapbox.featureLayer( geoJson, dataLayerOpts ).addTo( this.map );
		} catch ( e ) {
			mw.log( e );
		}
	};

	/**
	 * Fixes map size when the container is not visible yet, thus has no
	 * physical size.
	 *
	 * The hack is to try jQuery which will pick up CSS dimensions. T125263
	 *
	 * However in full screen, the container size is actually [0,0] at that
	 * time. In that case, the script looks for the first visible parent and
	 * takes its `height` and `width` to initialize the map.
	 *
	 * @private
	 */
	MWMap.prototype._fixMapSize = function () {
		var width, height, $visibleParent = this.$container.closest( ':visible' );

		// Get `max` properties in case the container was wrapped
		// with {@link #responsiveContainerWrap}.
		width = $visibleParent.css( 'max-width' );
		height = $visibleParent.css( 'max-height' );
		width = ( !width || width === 'none' ) ? $visibleParent.width() : width;
		height = ( !height || height === 'none' ) ? $visibleParent.height() : height;

		while ( ( !height && $visibleParent.parent().length ) ) {
			$visibleParent = $visibleParent.parent();
			width = $visibleParent.outerWidth( true );
			height = $visibleParent.outerHeight( true );
		}

		this.map._size = new L.Point( width, height );
	};

	MWMap.prototype.setView = function ( center, zoom, options, save ) {
		var maxBounds,
			map = this.map,
			data = this._data;

		try {
			center = L.latLng( center );
			zoom = isNaN( zoom ) ? this.map.options.fallbackZoom : zoom;
			map.setView( center, zoom, options );
		} catch ( e ) {
			// Determines best center of the map
			maxBounds = getValidBounds( map );

			if ( maxBounds.isValid() ) {
				map.fitBounds( maxBounds );
			} else {
				map.fitWorld();
			}
			// (Re-)Applies expected zoom
			if ( !isNaN( data.zoom ) ) {
				map.setZoom( data.zoom );
			}
			if ( save ) {
				// Updates map data.
				data.zoom = map.getZoom();
				data.longitude = map.getCenter().lng;
				data.latitude = map.getCenter().lat;
				// Updates container's data attributes to avoid `NaN` errors
				$( map.getContainer() ).closest( '.mw-kartographer-interactive' ).data( {
					zoom: data.zoom,
					lon: data.longitude,
					lat: data.latitude
				} );
			}
		}
	};

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
			groupsLoaded = mw.config.get( 'wgKartographerLiveData' ),
			groupsToLoad = [],
			promises = [];

		if ( !groupsLoaded ) {
			// Keep the reference to groupsLoaded, as it shouldn't change again
			groupsLoaded = {};
			mw.config.set( 'wgKartographerLiveData', groupsLoaded );
		}

		// For each requested layer, make sure it is loaded or is promised to be loaded
		$( overlays ).each( function ( key, value ) {
			var data = groupsLoaded[ value ];
			if ( data === undefined ) {
				groupsToLoad.push( value );
				// Once loaded, this value will be replaced with the received data
				groupsLoaded[ value ] = deferred.promise();
			} else if ( data !== null && $.isFunction( data.then ) ) {
				promises.push( data );
			}
		} );

		if ( groupsToLoad.length ) {
			promises.push( deferred.promise() );
		}
		if ( !promises.length ) {
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
			deferred.resolve( groupsLoaded );
		} );

		return $.when.apply( $, promises ).then( function () {
			// All pending promises are done
			return groupsLoaded;
		} ).promise();
	}

	/**
	 * Gets the valid bounds of a map/layer.
	 *
	 * @param {L.Map|L.Layer} layer
	 * @return {L.LatLngBounds} Extended bounds
	 * @private
	 */
	function getValidBounds( layer ) {
		var layerBounds = new L.LatLngBounds();
		if ( typeof layer.eachLayer === 'function' ) {
			layer.eachLayer( function ( child ) {
				layerBounds.extend( getValidBounds( child ) );
			} );
		} else {
			layerBounds.extend( validateBounds( layer ) );
		}
		return layerBounds;
	}

	/**
	 * Validate that the bounds contain no outlier.
	 *
	 * An outlier is a layer whom bounds do not fit into the world,
	 * i.e. `-180 <= longitude <= 180  &&  -90 <= latitude <= 90`
	 *
	 * @param {L.Layer} layer Layer to get and validate the bounds.
	 * @return {L.LatLng|boolean} Bounds if valid.
	 * @private
	 */
	function validateBounds( layer ) {
		var bounds = ( typeof layer.getBounds === 'function' ) && layer.getBounds();

		bounds = bounds || ( typeof layer.getLatLng === 'function' ) && layer.getLatLng();

		if ( bounds && worldLatLng.contains( bounds ) ) {
			return bounds;
		}
		return false;
	}

	return function ( container, data ) {
		return new MWMap( container, data );
	};
} )(
	module.FullScreenControl,
	module.dataLayerOpts,
	module.ControlScale
);
