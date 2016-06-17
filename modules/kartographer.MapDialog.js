/**
 * Dialog for full screen maps
 *
 * @class
 * @extends OO.ui.Dialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
mw.kartographer.MapDialog = function MwKartographerMapDialog() {
	// Parent method
	mw.kartographer.MapDialog.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.kartographer.MapDialog, OO.ui.Dialog );

/* Static Properties */

mw.kartographer.MapDialog.static.size = 'full';

/* Methods */

mw.kartographer.MapDialog.prototype.initialize = function () {
	// Parent method
	mw.kartographer.MapDialog.super.prototype.initialize.apply( this, arguments );

	this.map = null;
	this.mapData = null;
	this.$map = null;
};

/**
 * Changes the map within the map dialog.
 *
 * If the new map is the same at the previous map, we reuse the same map
 * object and simply update the zoom and the center of the map.
 *
 * If the new map is different, we keep the dialog open and simply
 * replace the map object with a new one.
 *
 * @param {Object} mapData The data for the new map.
 * @param {Object} [mapData.fullScreenState] Optional full screen position in
 *   which to open the map.
 * @param {number} [mapData.fullScreenState.zoom]
 * @param {number} [mapData.fullScreenState.latitude]
 * @param {number} [mapData.fullScreenState.longitude]
 */
mw.kartographer.MapDialog.prototype.changeMap = function ( mapData ) {
	var fullScreenState, extendedData,
		existing = this.mapData;

	// Check whether it is the same map.
	if ( existing &&
		typeof existing.maptagId === 'number' &&
		existing.maptagId === mapData.maptagId ) {

		fullScreenState = mapData.fullScreenState;
		extendedData = {};

		// override with full screen state
		$.extend( extendedData, mapData, fullScreenState );

		// Use this boolean to stop listening to `moveend` event while we're
		// manually moving the map.
		this.movingMap = true;
		this.map.setView( new L.LatLng( extendedData.latitude, extendedData.longitude ), extendedData.zoom );
		this.map.mapData = mapData;
		this.movingMap = false;
		return;
	}

	this.setup.call( this, mapData );
};

mw.kartographer.MapDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if ( !action ) {
		return new OO.ui.Process( function () {
			var router = mw.loader.require( 'mediawiki.router' );
			if ( router.getPath() !== '' ) {
				router.navigate( '' );
			} else {
				// force close
				dialog.close( { action: action } );
			}
		} );
	}
	return mw.kartographer.MapDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Tells the router to navigate to the current full screen map route.
 */
mw.kartographer.MapDialog.prototype.updateHash = function () {
	var router = mw.loader.require( 'mediawiki.router' ),
		hash = mw.kartographer.getMapHash( this.mapData, this.map );

	// Avoid extra operations
	if ( this.lastHash !== hash ) {
		router.navigate( hash );
		this.lastHash = hash;
	}
};

/**
 * Listens to `moveend` event and calls {@link #updateHash}.
 *
 * This method is throttled, meaning the method will be called at most once per
 * every 100 milliseconds.
 */
mw.kartographer.MapDialog.prototype.onMapMove = OO.ui.throttle( function () {
	// Stop listening to `moveend` event while we're
	// manually moving the map (updating from a hash),
	// or if the map is not yet loaded.
	/*jscs:disable disallowDanglingUnderscores */
	if ( this.movingMap || !this.map || !this.map._loaded ) {
		return false;
	}
	/*jscs:enable disallowDanglingUnderscores */
	this.updateHash();
}, 100 );

mw.kartographer.MapDialog.prototype.getSetupProcess = function ( mapData ) {
	return mw.kartographer.MapDialog.super.prototype.getSetupProcess.call( this, mapData )
		.next( function () {
			var fullScreenState = mapData.fullScreenState,
				extendedData = {};

			if ( this.map ) {
				this.map.remove();
				this.$map.remove();
			}

			this.$map = $( '<div>' )
				.addClass( 'mw-kartographer-mapDialog-map' )
				.appendTo( this.$body );

			this.map = mw.kartographer.createMap( this.$map[ 0 ], mapData );
			this.map.addControl( new mw.kartographer.MapDialogCloseControl( { dialog: this } ) );

			// copy of the initial settings
			this.mapData = mapData;

			if ( fullScreenState ) {
				// override with full screen state
				$.extend( extendedData, mapData, fullScreenState );
				this.map.setView( new L.LatLng( extendedData.latitude, extendedData.longitude ), extendedData.zoom, true );
			}

			if ( typeof mapData.maptagId === 'number' ) {
				this.map.on( 'moveend', this.onMapMove, this );
			}

			mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
		}, this );
};

mw.kartographer.MapDialog.prototype.getReadyProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.map.invalidateSize();
		}, this );
};

mw.kartographer.MapDialog.prototype.getTeardownProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			this.map.remove();
			this.$map.remove();
			this.map = null;
			this.mapData = null;
			this.$map = null;
		}, this );
};

/**
 * Close control on full screen mode.
 */
mw.kartographer.MapDialogCloseControl = L.Control.extend( {
	options: {
		position: 'topright'
	},

	onAdd: function () {
		var container = L.DomUtil.create( 'div', 'leaflet-bar' ),
			link = L.DomUtil.create( 'a', 'oo-ui-icon-close', container );

		this.href = '#';
		link.title = mw.msg( 'kartographer-fullscreen-close' );

		L.DomEvent.addListener( link, 'click', this.onClick, this );
		L.DomEvent.disableClickPropagation( container );

		return container;
	},

	onClick: function ( e ) {
		L.DomEvent.stop( e );

		this.options.dialog.executeAction( '' );
	}
} );
