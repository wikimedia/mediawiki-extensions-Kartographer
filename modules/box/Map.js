/**
 * # Kartographer Map class.
 *
 * Creates a map with layers, markers, and interactivity.
 *
 * Avoid creating a local variable "Map" as this is a native function in ES6. Also note that some
 * methods have dependants outside of this codebase. Especially tools and gadgets around maps for
 * Wikimedia wikis. Be extra careful when changing these.
 *
 * @alternateClassName KartographerMap
 * @class Kartographer.Box.MapClass
 * @extends L.Map
 */
var util = require( 'ext.kartographer.util' ),
	OpenFullScreenControl = require( './openfullscreen_control.js' ),
	dataLayerOpts = require( './dataLayerOpts.js' ),
	ScaleControl = require( './scale_control.js' ),
	DataManagerFactory = require( './data.js' ),
	scale, urlFormat,
	worldLatLng = new L.LatLngBounds( [ -90, -180 ], [ 90, 180 ] ),
	KartographerMap;

/**
 * @return {number}
 * @private
 */
function bracketDevicePixelRatio() {
	var brackets = mw.config.get( 'wgKartographerSrcsetScales' ),
		baseRatio = window.devicePixelRatio || 1;
	if ( !brackets ) {
		return 1;
	}
	brackets.unshift( 1 );
	for ( var i = 0; i < brackets.length; i++ ) {
		var scl = brackets[ i ];
		if ( scl >= baseRatio || ( baseRatio - scl ) < 0.1 ) {
			return scl;
		}
	}
	return brackets[ brackets.length - 1 ];
}

scale = bracketDevicePixelRatio();
scale = ( scale === 1 ) ? '' : ( '@' + scale + 'x' );
urlFormat = '/{z}/{x}/{y}' + scale + '.png';

require( './leaflet.sleep.js' );
require( './mapbox-settings.js' ).configure();
require( './enablePreview.js' );

L.Map.mergeOptions( {
	sleepTime: 250,
	wakeTime: 500,
	sleepNote: false,
	sleepOpacity: 1,
	// the default zoom applied when `longitude` and `latitude` were
	// specified, but zoom was not.
	fallbackZoom: mw.config.get( 'wgKartographerFallbackZoom' )
} );

L.Popup.mergeOptions( {
	minWidth: 160,
	maxWidth: 300,
	autoPanPadding: [ 12, 12 ]
} );

/* eslint-disable no-underscore-dangle */
/**
 * Validate that the bounds contain no outlier.
 *
 * An outlier is a layer whom bounds do not fit into the world,
 * i.e. `-180 <= longitude <= 180  &&  -90 <= latitude <= 90`
 *
 * There is a special case for **masks** (polygons that cover the entire
 * globe with a hole to highlight a specific area). In this case the
 * algorithm tries to validate the hole bounds.
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
	} else if ( layer instanceof L.Polygon && layer.getLatLngs() && layer.getLatLngs()[ 1 ] ) {
		// This is a geomask
		// We drop the outer ring (aka world) and only look at the layers that are holes
		bounds = new L.LatLngBounds( layer._convertLatLngs( layer.getLatLngs().slice( 1 ) ) );
		if ( worldLatLng.contains( bounds ) ) {
			return bounds;
		}
	}
	return false;
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
 * @param {string} url ExternalData "page" URL
 * @return {string} Attribution string
 * @private
 */
function buildAttribution( url ) {
	var uri = new mw.Uri( url );
	var link = mw.html.element(
		'a',
		{
			target: '_blank',
			href: '//commons.wikimedia.org/wiki/Data:' + encodeURIComponent( uri.query.title )
		},
		uri.query.title
	);
	return mw.msg(
		'kartographer-attribution-externaldata',
		mw.msg( 'project-localized-name-commonswiki' ),
		[ link ]
	);
}

KartographerMap = L.Map.extend( {
	/**
	 * Create a map within options.container
	 *
	 * This function implements the constructor
	 *
	 * options.container has to be visible before constructing the map
	 * or call invalidateSizeAndSetInitialView when it becomes visible.
	 * See also phab:T151524 and https://github.com/Leaflet/Leaflet/issues/4200
	 *
	 * @param {Object} options **Configuration and options:**
	 * @param {HTMLElement} options.container **Map container.**
	 * @param {boolean} [options.allowFullScreen=false] **Whether the map
	 *   can be opened in a full screen dialog.**
	 * @param {string[]} [options.dataGroups] **List of known data groups,
	 *   fetchable from the server, to add as overlays onto the map.**
	 * @param {Object|Array} [options.data] **Inline GeoJSON features to
	 *   add to the map.**
	 * @param {boolean} [options.alwaysStatic=false] If the dynamic map should
	 *   behave like a static map
	 * @param {boolean} [options.alwaysInteractive=false] Prevents the map
	 *   from becoming static when the screen is too small.
	 * @param {Array|L.LatLng|string} [options.center] **Initial map center.**
	 * @param {number|string} [options.zoom] **Initial map zoom.**
	 * @param {string} [options.lang] Language for map labels
	 * @param {string} [options.style] Map style. _Defaults to
	 *  `mw.config.get( 'wgKartographerDfltStyle' )`._
	 * @param {Kartographer.Box.MapClass} [options.parentMap] Parent map
	 *   _(internal, used by the full screen map to refer its parent map)_.
	 * @param {boolean} [options.fullscreen=false] Whether the map is a map
	 *   opened in a full screen dialog _(internal, used to indicate it is
	 *   a full screen map)_.
	 * @param {string} [options.fullScreenRoute] Route associated to this map
	 *   _(internal, used by "`<maplink>`" and "`<mapframe>`")_.
	 *
	 * @memberof Kartographer.Box.MapClass
	 * @public
	 */
	initialize: function ( options ) {
		var mapServer = mw.config.get( 'wgKartographerMapServer' ),
			defaultStyle = mw.config.get( 'wgKartographerDfltStyle' ),
			style = options.style || defaultStyle,
			map = this;

		if ( !mapServer ) {
			throw new Error( 'wgKartographerMapServer must be configured.' );
		}

		if ( options.center === 'auto' ) {
			options.center = undefined;
		}
		if ( options.zoom === 'auto' ) {
			options.zoom = undefined;
		}

		$( options.container ).addClass( 'mw-kartographer-interactive' );

		var args = L.extend( {}, L.Map.prototype.options, options, {
			// `center` and `zoom` are to undefined to avoid calling
			// setView now. setView is called later when the data is
			// loaded.
			center: undefined,
			zoom: undefined
		} );

		L.Map.prototype.initialize.call( this, options.container, args );

		/**
		 * @name $container
		 * @property {jQuery} $container Reference to the map
		 *   container.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.$container = $( this._container );

		this.on( 'kartographerisready', function () {
			// eslint-disable-next-line camelcase
			map._kartographer_ready = true;
		} );

		/**
		 * @name parentMap
		 * @property {Kartographer.Box.MapClass} [parentMap=null] Reference
		 *   to the parent map.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.parentMap = options.parentMap || null;

		/**
		 * @name parentLink
		 * @property {Kartographer.Linkbox.LinkClass} [parentLink=null] Reference
		 *   to the parent link.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.parentLink = options.parentLink || null;

		/**
		 * @name featureType
		 * @property {string} The feature type identifier.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.featureType = options.featureType;

		/**
		 * @name fullScreenMap
		 * @property {Kartographer.Box.MapClass} [fullScreenMap=null] Reference
		 *   to the child full screen map.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.fullScreenMap = null;

		/**
		 * @name useRouter
		 * @property {boolean} useRouter Whether the map uses the Mediawiki Router.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.useRouter = !!options.fullScreenRoute;

		/**
		 * @name fullScreenRoute
		 * @property {string} [fullScreenRoute=null] Route associated to this map.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.fullScreenRoute = options.fullScreenRoute || null;

		/**
		 * @name captionText
		 * @property {string} [captionText=''] Caption associated to the map.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.captionText = options.captionText || '';

		/**
		 * @name lang
		 * @property {string} lang Language code to use for labels
		 * @type {string}
		 * @memberof Kartographer.Box.MapClass#
		 */
		this.lang = options.lang || util.getDefaultLanguage();

		/**
		 * @name dataLayers
		 * @property {L.mapbox.FeatureLayer[]} dataLayers References to the data layers.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.dataLayers = [];

		/* Add base layer */

		/**
		 * @name layerUrl
		 * @property {string} layerUrl Base URL for the tile layer
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.layerUrl = mapServer + ( style ? '/' + style : '' ) + urlFormat;

		/**
		 * @name wikimediaLayer
		 * @property {L.TileLayer} wikimediaLayer Reference to `Wikimedia`
		 *   tile layer.
		 * @memberof Kartographer.Box.MapClass#
		 * @protected
		 */
		this.wikimediaLayer = L.tileLayer(
			this.getLayerUrl(),
			{
				maxZoom: 19,
				attribution: mw.message( 'kartographer-attribution' ).parse()
			}
		).addTo( this );

		/* Add map controls */

		/**
		 * @name attributionControl
		 * @property {L.Control.Attribution} attributionControl Reference
		 *   to attribution control.
		 * @memberof Kartographer.Box.MapClass#
		 */
		this.attributionControl.setPrefix( '' );

		/**
		 * @name scaleControl
		 * @property {Kartographer.Box.ScaleControl} scaleControl Reference
		 *   to scale control.
		 * @memberof Kartographer.Box.MapClass#
		 */
		this.scaleControl = new ScaleControl( { position: 'bottomright' } ).addTo( this );

		if ( options.allowFullScreen ) {
			// embed maps, and full screen is allowed
			this.on( 'dblclick', function () {
				map.openFullScreen();
			} );

			/**
			 * @name openFullScreenControl
			 * @property {Kartographer.Box.OpenFullScreenControl|undefined} [openFullScreenControl=undefined]
			 * Reference to open full screen control.
			 * @memberof Kartographer.Box.MapClass#
			 */
			this.openFullScreenControl = new OpenFullScreenControl( { position: 'topright' } ).addTo( this );
		}

		/* Initialize map */

		if ( !this._container.clientWidth || !this._container.clientHeight ) {
			this._fixMapSize();
		}
		if ( !this.options.fullscreen ) {
			this.doubleClickZoom.disable();
		}

		if ( !this.options.fullscreen && !options.alwaysInteractive ) {
			this._invalidateInteractive();
		}

		// The `ready` function has not fired yet so there is no center or zoom defined.
		// Disable panning and zooming until that has happened.
		// See T257872.
		map.dragging.disable();
		map.touchZoom.disable();

		function ready() {
			map.initView( options.center, options.zoom );

			// Workaround to make interactive elements (especially geoshapes) reachable via tab
			for ( var id in map.dataLayers ) {
				map.dataLayers[ id ].eachLayer( function ( shape ) {
					var el = shape.getElement();
					if ( shape.getPopup() ) {
						el.tabIndex = 0;
					} else {
						$( el ).removeClass( 'leaflet-interactive' );
					}
				} );
			}

			if ( !map.isStatic() ) {
				map.dragging.enable();
				map.touchZoom.enable();
			}

			map.fire(
				/**
				 * Fired when the Kartographer Map object is ready.
				 *
				 * @event kartographerisready
				 * @memberof Kartographer.Box.MapClass
				 */
				'kartographerisready' );
		}

		if ( this.parentMap ) {
			this.parentMap.dataLayers.forEach( function ( layer ) {
				map.addGeoJSONLayer( layer.getGeoJSON(), layer.options );
			} );
			ready();
			return;
		}

		this.addDataGroups( options.dataGroups ).then( function () {
			if ( typeof options.data === 'object' ) {
				map.addDataLayer( options.data ).then( function () {
					ready();
				} );
			} else {
				ready();
			}
		}, function () {
			// T25787
			ready();
			mw.log.error( 'Unable to add datalayers to map.' );
		} );
	},

	/**
	 * Runs the given callback **when the Kartographer map has finished
	 * loading the data layers and positioning** the map with a center and
	 * zoom, **or immediately if it happened already**.
	 *
	 * @param {Function} callback
	 * @param {Object} [context]
	 * @chainable
	 */
	doWhenReady: function ( callback, context ) {
		if ( this._kartographer_ready ) {
			callback.call( context || this, this );
		} else {
			this.on( 'kartographerisready', callback, context );
		}
		return this;
	},

	/**
	 * Sets the initial center and zoom of the map, and optionally calls
	 * {@link #setView} to reposition the map.
	 *
	 * @param {L.LatLng|number[]} [center]
	 * @param {number} [zoom]
	 * @param {boolean} [setView=true]
	 * @chainable
	 */
	initView: function ( center, zoom, setView ) {
		if ( Array.isArray( center ) ) {
			if ( !isNaN( center[ 0 ] ) && !isNaN( center[ 1 ] ) ) {
				center = L.latLng( center );
			} else {
				center = undefined;
			}
		}

		zoom = isNaN( zoom ) ? undefined : zoom;
		this._initialPosition = {
			center: center,
			zoom: zoom
		};
		if ( setView !== false ) {
			this.setView( center, zoom, null, true );
		}
		return this;
	},

	/**
	 * Iterate and add each group to the map
	 *
	 * Internal helper function, assumes that the groups have already been expanded.
	 *
	 * @param {Kartographer.Data.Group[]} groups
	 * @private
	 */
	addGeoJSONGroups: function ( groups ) {
		var map = this;
		groups.forEach( function ( group ) {
			if ( group.failed ) {
				if ( group.name && group.name.slice( 0, 1 ) === '_' && group.failureReason ) {
					mw.log.warn( 'Layer ' + group.name + ' not found or contains no data: ' + group.failureReason );
				}
				return;
			}

			var layerOptions = {};
			var geoJSON = group.getGeoJSON();

			if ( !geoJSON.length ) {
				return;
			// FIXME: How can it be an array (with .length) and GeoJSON object the same time?
			} else if ( geoJSON.service === 'page' ) {
				var attribution = buildAttribution( geoJSON.url );
				layerOptions.name = attribution;
				layerOptions.attribution = attribution;
			} else if ( group.name ) {
				layerOptions.name = group.name;
			}
			map.addGeoJSONLayer( geoJSON, layerOptions );
		} );
	},

	/**
	 * Gets and adds known data groups as layers onto the map.
	 *
	 * The data is loaded from the server if not found in memory.
	 *
	 * @param {string[]} dataGroups
	 * @return {jQuery.Promise}
	 * @public
	 */
	addDataGroups: function ( dataGroups ) {
		if ( !dataGroups || !dataGroups.length ) {
			return $.Deferred().resolve().promise();
		}

		var title = mw.config.get( 'wgPageName' );
		var revid = mw.config.get( 'wgRevisionId' );
		return DataManagerFactory().loadGroups( dataGroups, title, revid )
			.then( this.addGeoJSONGroups.bind( this ) );
	},

	/**
	 * Create a new layer from literal GeoJSON
	 *
	 * @param {Object|Object[]} groupData
	 * @public
	 */
	addDataLayer: function ( groupData ) {
		return DataManagerFactory().loadExternalData( groupData )
			.then( this.addGeoJSONGroups.bind( this ) );
	},

	/**
	 * Creates a new GeoJSON layer and pushes onto the list of layers to be
	 * added.
	 *
	 * @param {Object} geoJSON Features
	 * @param {Object} [options] Layer options
	 * @public
	 */
	addGeoJSONLayer: function ( geoJSON, options ) {
		if ( typeof geoJSON === 'string' ) {
			mw.log.warn( 'Please update deprecated call to addGeoJSONLayer, see T327151' );
			var name = geoJSON;
			geoJSON = options;
			options = arguments[ 2 ];
			options.name = name;
		}

		try {
			// T326790 make sure that only points triggering popups are rendered interactive
			options.pointToLayer = function ( feature, latlon ) {
				var props = feature.properties,
					interactive = !!( props && ( props.title || props.description ) );
				// Note: This is the same call as in the default pointToLayer function.
				var marker = L.mapbox.marker.style( feature, latlon );
				marker.options.interactive = interactive;
				marker.options.keyboard = interactive;
				return marker;
			};
			var layer = L.mapbox.featureLayer( geoJSON, $.extend( {}, dataLayerOpts, options ) ).addTo( this );
			layer.getAttribution = function () {
				return this.options.attribution;
			};
			this.attributionControl.addAttribution( layer.getAttribution() );
			this.dataLayers.push( layer );
			layer.isDataGroup = true;
		} catch ( e ) {
			mw.log.warn( e );
		}
	},

	/**
	 * Opens the map in a full screen dialog.
	 *
	 * **Uses Resource Loader module: {@link Kartographer.Dialog ext.kartographer.dialog}**
	 *
	 * @param {Object} [position] Map `center` and `zoom`.
	 */
	openFullScreen: function ( position ) {
		this.doWhenReady( function () {

			var map = this.options.link ? this : this.fullScreenMap;
			position = position || this.getMapPosition();

			if ( !map ) {
				map = this.fullScreenMap = new KartographerMap( {
					container: L.DomUtil.create( 'div', 'mw-kartographer-mapDialog-map' ),
					center: position.center,
					zoom: position.zoom,
					lang: this.lang,
					featureType: this.featureType,
					fullscreen: true,
					captionText: this.captionText,
					fullScreenRoute: this.fullScreenRoute,
					parentMap: this
				} );
				// resets the right initial position silently afterwards.
				map.initView(
					this._initialPosition.center,
					this._initialPosition.zoom,
					false
				);
			} else {
				this.doWhenReady( function () {
					map.setView(
						position.center,
						position.zoom
					);
				} );
			}

			mw.loader.using( 'ext.kartographer.dialog' ).then( function () {
				map.doWhenReady( function () {
					require( 'ext.kartographer.dialog' ).render( map );
				} );
			} );
		}, this );
	},

	/**
	 * Closes full screen dialog.
	 *
	 * @chainable
	 */
	closeFullScreen: function () {
		require( 'ext.kartographer.dialog' ).close();
		return this;
	},

	/**
	 * Gets initial map center and zoom.
	 *
	 * @return {Object}
	 * @return {L.LatLng} return.center
	 * @return {number} return.zoom
	 */
	getInitialMapPosition: function () {
		return this._initialPosition;
	},

	/**
	 * Gets current map center and zoom.
	 *
	 * @param {Object} [options]
	 * @param {boolean} [options.scaled=false] Whether you want the
	 *   coordinates to be scaled to the current zoom.
	 * @return {Object}
	 * @return {L.LatLng} return.center
	 * @return {number} return.zoom
	 */
	getMapPosition: function ( options ) {
		var center = this.getCenter().wrap(),
			zoom = this.getZoom();

		if ( options && options.scaled ) {
			center = L.latLng( this.getScaleLatLng( center.lat, center.lng, zoom ) );
		}
		return {
			center: center,
			zoom: zoom
		};
	},

	/**
	 * Formats the full screen route of the map, such as:
	 *   `/map/:maptagId(/:zoom/:longitude/:latitude)`
	 *
	 * The hash will contain the portion between parenthesis if and only if
	 * one of these 3 values differs from the initial setting.
	 *
	 * @return {string} The route to open the map in full screen mode.
	 */
	getHash: function () {
		if ( !this._initialPosition ) {
			return this.fullScreenRoute;
		}

		var hash = this.fullScreenRoute,
			currentPosition = this.getMapPosition(),
			initialPosition = this._initialPosition,
			newHash = currentPosition.zoom + '/' + this.getScaleLatLng(
				currentPosition.center.lat,
				currentPosition.center.lng,
				currentPosition.zoom
			).join( '/' ),
			initialHash = initialPosition.center && (
				initialPosition.zoom + '/' +
				this.getScaleLatLng(
					initialPosition.center.lat,
					initialPosition.center.lng,
					initialPosition.zoom
				).join( '/' )
			);

		if ( newHash !== initialHash ) {
			hash += '/' + newHash;
		}

		return hash;
	},

	/**
	 * Sets the map at a certain zoom and position.
	 *
	 * When the zoom and map center are provided, it falls back to the
	 * original `L.Map#setView`.
	 *
	 * If the zoom or map center are not provided, this method will
	 * calculate some values so that all the point of interests fit within the
	 * map.
	 *
	 * **Note:** Unlike the original `L.Map#setView`, it accepts an optional
	 * fourth parameter to decide whether to update the container's data
	 * attribute with the calculated values (for performance).
	 *
	 * @param {L.LatLng|number[]|string} [center] Map center.
	 * @param {number} [zoom]
	 * @param {Object} [options] See [L.Map#setView](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-map-class/)
	 *   documentation for the full list of options.
	 * @param {boolean} [save=false] Whether to update the data attributes.
	 * @chainable
	 */
	setView: function ( center, zoom, options, save ) {
		var initial = this.getInitialMapPosition();

		if ( Array.isArray( center ) ) {
			if ( !isNaN( center[ 0 ] ) && !isNaN( center[ 1 ] ) ) {
				center = L.latLng( center );
			} else {
				center = undefined;
			}
		}
		if ( center ) {
			zoom = isNaN( zoom ) ? this.options.fallbackZoom : zoom;
			L.Map.prototype.setView.call( this, center, zoom, options );
		} else {
			// Determines best center of the map
			// Bounds calulation depends on the size of the frame
			// If the frame is not visible, there is no point in calculating
			// You need to call invalidateSize when it becomes available again
			var maxBounds = getValidBounds( this );

			if ( maxBounds.isValid() ) {
				this.fitBounds( maxBounds );
			} else {
				this.fitWorld();
			}
			// (Re-)Applies expected zoom

			if ( initial && !isNaN( initial.zoom ) ) {
				this.setZoom( initial.zoom );
			}

			// Save the calculated position,
			// unless we already know it is incorrect due to being loaded
			// when the frame was invisble and had no dimensions to base
			// autozoom, autocenter on.
			// eslint-disable-next-line no-jquery/no-sizzle
			if ( this.$container.is( ':visible' ) && save ) {
				// Updates map data.
				this.initView( this.getCenter(), this.getZoom(), false );
				// Updates container's data attributes to avoid `NaN` errors
				if ( !this.fullscreen ) {
					this.$container.closest( '.mw-kartographer-interactive' ).data( {
						zoom: this.getZoom(),
						longitude: this.getCenter().lng,
						latitude: this.getCenter().lat
					} );
				}
			}
		}
		return this;
	},

	/**
	 * Get the URL to be passed to L.TileLayer
	 *
	 * @private
	 * @return {string}
	 */
	getLayerUrl: function () {
		return this.layerUrl + '?' + $.param( { lang: this.lang } );
	},

	/**
	 * Change the map's language.
	 *
	 * This will cause the map to be rerendered if the language is different.
	 *
	 * @param {string} lang New language code
	 */
	setLang: function ( lang ) {
		if ( this.lang !== lang ) {
			this.lang = lang;
			this.wikimediaLayer.setUrl( this.getLayerUrl() );
		}
	},

	/**
	 * Convenient method that formats the coordinates based on the zoom level.
	 *
	 * @param {number} lat
	 * @param {number} lng
	 * @param {number} [zoom] Typically ranging from 0 (entire world) to 19 (nearest)
	 * @return {string[]}
	 */
	getScaleLatLng: function ( lat, lng, zoom ) {
		zoom = zoom === undefined ? this.getZoom() : zoom;

		// T321603: It appears like zoom can be a bogus fractional value for unknown reasons
		var precision = zoom > 1 ? Math.ceil( Math.log( zoom ) / Math.LN2 ) : 0;
		return [ lat.toFixed( precision ), lng.toFixed( precision ) ];
	},

	/**
	 * @localdoc Extended to also destroy the {@link #fullScreenMap} when
	 *   it exists.
	 *
	 * @override
	 * @chainable
	 */
	remove: function () {
		var parent = this.parentMap || this.parentLink;

		if ( this.fullScreenMap ) {
			L.Map.prototype.remove.call( this.fullScreenMap );
			this.fullScreenMap = null;
		}
		if ( parent ) {
			parent.fullScreenMap = null;
		}

		return L.Map.prototype.remove.call( this );
	},

	/**
	 * Fixes map size when the container is not visible yet, thus has no
	 * physical size.
	 *
	 * - In full screen, we take the viewport width and height.
	 * - Otherwise, the hack is to try jQuery which will pick up CSS
	 *   dimensions. (T125263)
	 * - Finally, if the calculated size is still [0,0], the script looks
	 *   for the first visible parent and takes its `height` and `width`
	 *   to initialize the map.
	 *
	 * @protected
	 */
	_fixMapSize: function () {
		if ( this.options.fullscreen ) {
			this._size = new L.Point(
				window.innerWidth,
				window.innerHeight
			);
			this._sizeChanged = false;
			return;
		}

		// eslint-disable-next-line no-jquery/no-sizzle
		var $visibleParent = this.$container.closest( ':visible' );

		// Try `max` properties.
		var width = $visibleParent.css( 'max-width' ),
			height = $visibleParent.css( 'max-height' );
		width = ( !width || width === 'none' ) ? $visibleParent.width() : width;
		height = ( !height || height === 'none' ) ? $visibleParent.height() : height;

		while ( ( !height && $visibleParent.parent().length ) ) {
			$visibleParent = $visibleParent.parent();
			width = $visibleParent.outerWidth( true );
			height = $visibleParent.outerHeight( true );
		}

		this._size = new L.Point( width, height );
	},

	/**
	 * Adds Leaflet.Sleep handler and overrides `invalidateSize` when the map
	 * is not in full screen mode.
	 *
	 * The new `invalidateSize` method calls {@link #toggleStaticState} to
	 * determine the new state and make the map either static or interactive.
	 *
	 * @chainable
	 * @protected
	 */
	_invalidateInteractive: function () {
		// add Leaflet.Sleep when the map isn't full screen.
		this.addHandler( 'sleep', L.Map.Sleep );

		// `invalidateSize` is triggered on window `resize` events.
		this.invalidateSize = function ( options ) {
			L.Map.prototype.invalidateSize.call( this, options );

			if ( this.options.fullscreen ) {
				// skip if the map is full screen
				return this;
			}
			// Local debounce because OOjs is not yet available.
			if ( this._staticTimer ) {
				clearTimeout( this._staticTimer );
			}
			this._staticTimer = setTimeout( this.toggleStaticState, 200 );
			return this;
		};
		// Initialize static state.
		this.toggleStaticState = L.Util.bind( this.toggleStaticState, this );
		this.toggleStaticState();
		return this;
	},

	/**
	 * Returns true if the map should behave like a static snapshot
	 */
	isStatic: function () {
		return this._static;
	},

	/**
	 * Makes the map interactive IIF :
	 *
	 * - the `device width > 480px`,
	 * - there is at least a 200px horizontal margin.
	 *
	 * Otherwise makes it static.
	 *
	 * @chainable
	 */
	toggleStaticState: function () {
		var deviceWidth = window.innerWidth,
			// All maps static if deviceWitdh < 480px
			isSmallWindow = deviceWidth <= 480,
			staticMap;

		// If the window is wide enough, make sure there is at least
		// a 200px margin to scroll, otherwise make the map static.
		staticMap = this.options.alwaysStatic || isSmallWindow || ( this.getSize().x + 200 ) > deviceWidth;

		// Skip if the map is already static
		if ( this._static === staticMap ) {
			return;
		}

		// Toggle static/interactive state of the map
		this._static = staticMap;

		if ( staticMap ) {
			this.sleep._sleepMap();
			this.sleep.disable();
			this.scrollWheelZoom.disable();
			this.keyboard.disable();
		} else {
			this.keyboard.enable();
			this.sleep.enable();
		}
		this.$container.toggleClass( 'mw-kartographer-static', staticMap );
		return this;
	},

	/**
	 * Reinitialize a view that was originally hidden.
	 *
	 * These views will have calculated autozoom and autoposition of shapes incorrectly
	 * because the size of the map frame will initially be 0.
	 *
	 * We need to fetch the original position information, invalidate to make sure the
	 * frame is readjusted and then reset the view, so that it will recalculate the
	 * autozoom and autoposition if needed.
	 *
	 * @override
	 * @chainable
	 */
	invalidateSizeAndSetInitialView: function () {
		var position = this.getInitialMapPosition();
		this.invalidateSize();
		if ( position ) {
			// at rare times during load fases, position might be undefined
			this.initView( position.center, position.zoom, true );
		}

		return this;
	}
} );

module.exports = {
	Map: KartographerMap,
	map: function ( options ) {
		return new KartographerMap( options );
	}
};
