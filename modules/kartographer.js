/* globals module */
( function ( $, mw ) {

	var windowManager, mapDialog;

	mw.kartographer = mw.kartographer || {};

	function getWindowManager() {
		if ( !windowManager ) {
			windowManager = new OO.ui.WindowManager();
			setMapDialog( mw.loader.require( 'ext.kartographer.fullscreen' ).MapDialog() );
			$( 'body' ).append( windowManager.$element );
			windowManager.addWindows( [ getMapDialog() ] );
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
				} else if ( mapData && mapData.isMapframe ) {
					map = mw.kartographer.maps[ mapData.maptagId ];
				}

				$.extend( dialogData, mapData, {
					fullScreenState: fullScreenState,
					enableFullScreenButton: false
				} );

				if ( getMapDialog() ) {
					getMapDialog().changeMap( dialogData );
					return;
				}
				getWindowManager()
					.openWindow( getMapDialog(), dialogData )
					.then( function ( opened ) {
						// It takes 250ms for the dialog to open,
						// we'd better invalidate the size once it opened.
						setTimeout( function () {
							var map = getMapDialog().map;
							if ( map ) {
								map.invalidateSize();
							}
						}, 300 );
						return opened;
					} )
					.then( function ( closing ) {
						var dialog = getMapDialog();
						if ( map ) {
							map.setView(
								dialog.map.getCenter(),
								dialog.map.getZoom()
							);
						}
						setMapDialog( null );
						windowManager = null;
						return closing;
					} );
			} );
		};
	} )();

	/**
	 * Formats the full screen route of the map, such as:
	 *   `/map/:maptagId(/:zoom/:longitude/:latitude)`
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

		var hash = '/' + ( data.isMapframe ? 'map' : 'maplink' ),
			mapPosition,
			newHash,
			initialHash = getScaleCoords( data.zoom, data.latitude, data.longitude ).join( '/' );

		hash += '/' + data.maptagId;

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
			maptagId = null;
		// Prevent users from adding map divs directly via wikitext
		if ( $el.attr( 'mw-data' ) !== 'interface' ) {
			return null;
		}

		if ( $.type( $el.data( 'maptag-id' ) ) !== 'undefined' ) {
			maptagId = +$el.data( 'maptag-id' );
		}

		return {
			isMapframe: $el.hasClass( 'mw-kartographer-interactive' ),
			maptagId: maptagId,
			latitude: +$el.data( 'lat' ),
			longitude: +$el.data( 'lon' ),
			zoom: +$el.data( 'zoom' ),
			style: $el.data( 'style' ),
			overlays: $el.data( 'overlays' ) || []
		};
	}

	/**
	 * Formats the fullscreen state object based on route attributes.
	 *
	 * @param {string|number} [zoom]
	 * @param {string|number} [latitude]
	 * @param {string|number} [longitude]
	 * @return {Object} Full screen state
	 * @return {number} [return.zoom] Zoom if between 0 and 18.
	 * @return {number} [return.latitude]
	 * @return {number} [return.longitude]
	 * @private
	 */
	function getFullScreenState( zoom, latitude, longitude ) {
		var obj = {};
		if ( zoom !== undefined && zoom >= 0 && zoom <= 18 ) {
			obj.zoom = +zoom;
		}
		if ( longitude !== undefined ) {
			obj.latitude = +latitude;
			obj.longitude = +longitude;
		}
		return obj;
	}

	function getMapDialog() {
		return mapDialog;
	}

	function setMapDialog( dialog ) {
		mapDialog = dialog;
		return mapDialog;
	}

	module.exports = {
		getMapHash: mw.kartographer.getMapHash,
		openFullscreenMap: mw.kartographer.openFullscreenMap,
		getMapData: getMapData,
		getMapPosition: getMapPosition,
		getFullScreenState: getFullScreenState,
		getMapDialog: getMapDialog,
		getScaleCoords: getScaleCoords
	};

}( jQuery, mediaWiki ) );
