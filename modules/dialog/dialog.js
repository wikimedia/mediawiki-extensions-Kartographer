/**
 * Dialog for displaying maps in full screen mode.
 *
 * See [OO.ui.Dialog](https://doc.wikimedia.org/oojs-ui/master/js/#!/api/OO.ui.Dialog)
 * documentation for more details.
 *
 * @class Kartographer.Dialog.DialogClass
 * @extends OO.ui.Dialog
 */
const CloseFullScreenControl = require( './closefullscreen_control.js' );
// Opens the sidebar when the screen is wide enough (greater than 1024px)
const SIDEBAR_WIDTH = 320;

/**
 * @constructor
 * @memberof Kartographer.Dialog.DialogClass
 */
function MapDialog() {
	// Parent method
	MapDialog.super.apply( this, arguments );
}

/* Inheritance */

OO.inheritClass( MapDialog, OO.ui.Dialog );

/* Static Properties */

MapDialog.static.name = 'mapDialog';
MapDialog.static.size = 'full';

/* Methods */

MapDialog.prototype.initialize = function () {
	this.mapDetailsButton = null;

	// Parent method
	MapDialog.super.prototype.initialize.apply( this, arguments );

	// T359082: Temporarily disable dark mode unless we have a better idea
	this.$mapBody = $( '<div>' ).addClass( 'mw-kartographer-map-body notheme' )
		.append( $( '<div>' ).addClass( 'kartographer-mapDialog-loading' ) );
	this.$mapFooter = $( '<div>' ).addClass( 'mw-kartographer-map-foot' );

	this.$body
		.addClass( 'mw-kartographer-mapDialog-body' )
		.append( this.$mapBody, this.$mapFooter );

	this.map = null;
	this.offset = [ 0, 0 ];
};

/**
 * @param {L.Map|null} map
 */
MapDialog.prototype.setMap = function ( map ) {
	// remove older map
	if ( this.map ) {
		this.map.remove();
		this.$mapBody.empty();
	}
	// set new map
	this.map = map;

	if ( !this.map ) {
		return;
	}
	// update the view
	if ( this.isOpening() || this.isOpened() ) {
		this.map.closeFullScreenControl = new CloseFullScreenControl( { position: 'topright' } )
			.addTo( this.map );
	}

	this.$captionContainer.text( this.map.captionText );
	this.$mapBody.append( this.map.$container.css( 'position', '' ) );

	const $focusBox = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-focusBox' );

	// Add focus frame and hide on mouse but show on keyboard navigation
	this.map.$container
		.append( $focusBox )
		.on( 'mousedown', () => {
			$focusBox.removeClass( 'mw-kartographer-mapDialog-focusBox-available' );
		} )
		.on( 'mouseup', () => {
			// Keep focus in container to allow keyboard navigation
			this.map.$container.trigger( 'focus' );
		} )
		.on( 'keyup', ( e ) => {
			if ( e.which === OO.ui.Keys.TAB ) {
				const isMap = this.map.$container.is( e.target );
				$focusBox.toggleClass( 'mw-kartographer-mapDialog-focusBox-available', isMap );
			}
		} );

	// The button exists, the sidebar was open, call `tearDown` and reopen it.
	if ( this.sideBar ) {
		this.sideBar.tearDown();
		this.map.doWhenReady( () => {
			const open = this.mapDetailsButton.getValue();
			this.offsetMap( open );
			this.toggleSideBar( open );
		}, this );
	} else {
		// The button exists, the sidebar was not open, simply run `offsetMap`
		this.map.doWhenReady( () => {
			this.offsetMap( false );
			// preload the sidebar, we finished doing all the other stuff
			mw.loader.load( 'ext.kartographer.dialog.sidebar' );
		} );
	}
	// If the window was already open, trigger wikipage.maps
	// otherwise let the ready() of the window handle this.
	if ( this.isOpened() ) {
		mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
	}
};

MapDialog.prototype.setupFooter = function () {
	const $buttonContainer = $( '<div>' ).addClass( 'mw-kartographer-buttonfoot' );

	// Add nearby button
	if ( mw.config.get( 'wgKartographerNearby' ) ) {
		this.mapNearbyButton = new OO.ui.ToggleButtonWidget( {
			label: mw.msg( 'kartographer-sidebar-nearbybutton' )
		} );
		this.mapNearbyButton.connect( this, { change: 'toggleNearbyLayerWrapper' } );
		$buttonContainer.append( this.mapNearbyButton.$element );
	}

	// Add sidbar button
	this.mapDetailsButton = new OO.ui.ToggleButtonWidget( {
		label: mw.msg( 'kartographer-sidebar-togglebutton' )
	} );
	this.mapDetailsButton.connect( this, { change: 'toggleSideBar' } );
	$buttonContainer.append( this.mapDetailsButton.$element );

	// Add caption
	this.$captionContainer = $( '<div>' )
		.addClass( 'mw-kartographer-captionfoot' );

	this.$mapFooter.append(
		this.$captionContainer,
		$buttonContainer
	);
};

/**
 * @param {boolean} [open] If the sidebar should be shown or not, omit to toggle
 */
MapDialog.prototype.toggleSideBar = function ( open ) {
	mw.loader.using( 'ext.kartographer.dialog.sidebar' ).then( () => {
		if ( !this.sideBar ) {
			const SideBar = require( 'ext.kartographer.dialog.sidebar' );
			this.sideBar = new SideBar( { dialog: this } );
			this.sideBar.toggle( true );
		}

		open = open === undefined ? !this.mapDetailsButton.getValue() : open;

		if ( this.mapDetailsButton.getValue() !== open ) {
			this.mapDetailsButton.setValue( open );
			// This `change` event callback is fired again, so skip here.
			return;
		}

		// Animations only work if content is visible
		this.sideBar.$el.attr( 'aria-hidden', null );
		setTimeout( () => {
			this.$mapBody.toggleClass( 'mw-kartographer-mapDialog-sidebar-opened', open );
			setTimeout( () => {
				// Ensure proper hidden content after animation finishes
				this.sideBar.$el.attr( 'aria-hidden', !open );
			}, 100 /* Duration of the CSS animation */ );
		} );
	} );
};

/**
 * @param {boolean} showNearby
 */
MapDialog.prototype.toggleNearbyLayerWrapper = function ( showNearby ) {
	mw.loader.using( 'ext.kartographer.lib.leaflet.markercluster' )
		.then( this.toggleNearbyLayer.bind( this, showNearby, true ) );
};

/**
 * @param {boolean} showNearby
 * @param {boolean} [enableClustering]
 */
MapDialog.prototype.toggleNearbyLayer = function ( showNearby, enableClustering ) {
	if ( this.map ) {
		if ( !this.nearby ) {
			const Nearby = require( './nearby.js' );
			this.nearby = new Nearby( enableClustering );
		}
		this.nearby.toggleNearbyLayer( this.map, showNearby );

		if ( showNearby && !this.seenNearby ) {
			if ( mw.eventLog ) {
				mw.eventLog.submit( 'mediawiki.maps_interaction', {
					$schema: '/analytics/mediawiki/maps/interaction/1.0.0',
					action: 'nearby-show'
				} );
			}
			this.seenNearby = true;
		}
	}
};

MapDialog.prototype.getActionProcess = function ( action ) {
	if ( !action ) {
		return new OO.ui.Process( () => {
			if ( this.map ) {
				this.map.closeFullScreen();
				// Will be destroyed later, {@see getTeardownProcess} below
			}
		} );
	}
	return MapDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * Adds an offset to the center of the map.
 *
 * @param {boolean} isSidebarOpen Whether the sidebar is open.
 */
MapDialog.prototype.offsetMap = function ( isSidebarOpen ) {
	const map = this.map;
	this.offset = [ isSidebarOpen ? SIDEBAR_WIDTH / 2 : 0, 0 ];
	const targetPoint = map.project( map.getCenter(), map.getZoom() ).add( this.offset );
	const targetLatLng = map.unproject( targetPoint, map.getZoom() );

	map.setView( targetLatLng, map.getZoom(), { animate: false } ).invalidateSize();
};

MapDialog.prototype.getSetupProcess = function ( options ) {
	return MapDialog.super.prototype.getSetupProcess.call( this, options )
		.next( () => {
			const isFirstTimeOpen = !this.mapDetailsButton;

			if ( isFirstTimeOpen ) {
				this.setupFooter();
			}

			if ( options.map !== this.map ) {
				this.setMap( null );
			}
		} );
};

MapDialog.prototype.getReadyProcess = function ( options ) {
	return MapDialog.super.prototype.getReadyProcess.call( this, options )
		.next( () => {
			if ( mw.eventLog ) {
				mw.eventLog.submit( 'mediawiki.maps_interaction', {
					$schema: '/analytics/mediawiki/maps/interaction/1.0.0',
					action: 'fullscreen'
				} );
			}
			this.seenNearby = false;

			if ( options.map ) {
				this.setMap( options.map );
				this.map.doWhenReady( () => {
					mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
				} );
			}
		} );
};

MapDialog.prototype.getHoldProcess = function ( data ) {
	return MapDialog.super.prototype.getHoldProcess.call( this, data )
		.next( () => {
			// T297519: Disable touch/mouse early to not cause chaos on "dragend" and such
			this.map.boxZoom.disable();
			// T297848: The L.Handler.BoxZoom.removeHooks implementation is incomplete
			/* eslint-disable-next-line no-underscore-dangle */
			this.map.boxZoom._moved = false;
			this.map.dragging.disable();
			this.map.touchZoom.disable();
			this.map.doubleClickZoom.disable();
			this.map.scrollWheelZoom.disable();
			this.map.keyboard.disable();
		} );
};

MapDialog.prototype.getTeardownProcess = function ( data ) {
	return MapDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( () => {
			if ( this.mapNearbyButton ) {
				this.mapNearbyButton.setValue( false );
			}
			if ( this.map ) {
				this.map.remove();
				this.map = null;
			}
			this.$mapBody.empty();
		} );
};

module.exports = MapDialog;
