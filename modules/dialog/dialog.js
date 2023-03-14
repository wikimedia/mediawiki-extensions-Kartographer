/**
 * Dialog for displaying maps in full screen mode.
 *
 * See [OO.ui.Dialog](https://doc.wikimedia.org/oojs-ui/master/js/#!/api/OO.ui.Dialog)
 * documentation for more details.
 *
 * @class Kartographer.Dialog.DialogClass
 * @extends OO.ui.Dialog
 */
var CloseFullScreenControl = require( './closefullscreen_control.js' ),
	// Opens the sidebar when the screen is wide enough (greater than 1024px)
	FOOTER_HEIGHT = 63,
	SIDEBAR_WIDTH = 320;

/**
 * @constructor
 * @type {Kartographer.Dialog.DialogClass}
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

	this.$body
		.addClass( 'mw-kartographer-mapDialog-body' )
		.append( $( '<div>' ).addClass( 'kartographer-mapDialog-loading' ) );
	this.$foot
		.addClass( 'mw-kartographer-mapDialog-foot' );

	this.map = null;
};

/**
 * @param {L.Map} map
 */
MapDialog.prototype.setMap = function ( map ) {
	var dialog = this;
	// remove older map
	if ( dialog.map ) {
		dialog.map.remove();
		dialog.$body.empty();
	}
	// set new map
	dialog.map = map;

	if ( !dialog.map ) {
		return;
	}
	// update the view
	if ( dialog.isOpening() || dialog.isOpened() ) {
		dialog.map.closeFullScreenControl = new CloseFullScreenControl( { position: 'topright' } )
			.addTo( dialog.map );
	}

	dialog.$body.append(
		dialog.map.$container.css( 'position', '' )
	);

	var $focusBox = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-focusBox' );

	// Add focus frame and hide on mouse but show on keyboard navigation
	dialog.map.$container
		.append( $focusBox )
		.on( 'mousedown', function () {
			$focusBox.removeClass( 'mw-kartographer-mapDialog-focusBox-available' );
		} )
		.on( 'mouseup', function () {
			// Keep focus in container to allow keyboard navigation
			dialog.map.$container.trigger( 'focus' );
		} )
		.on( 'keyup', function ( e ) {
			if ( e.which === OO.ui.Keys.TAB ) {
				var isMap = dialog.map.$container.is( e.target );
				$focusBox.toggleClass( 'mw-kartographer-mapDialog-focusBox-available', isMap );
			}
		} );

	dialog.$captionContainer
		.text( dialog.map.captionText );

	// The button exists, the sidebar was open, call `tearDown` and reopen it.
	if ( dialog.sideBar ) {
		dialog.sideBar.tearDown();
		dialog.map.doWhenReady( function () {
			var open = dialog.mapDetailsButton.getValue();
			dialog.offsetMap( open );
			dialog.toggleSideBar( open );
		} );
	} else {
		// The button exists, the sidebar was not open, simply run `offsetMap`
		dialog.map.doWhenReady( function () {
			dialog.offsetMap( false );
			// preload the sidebar, we finished doing all the other stuff
			mw.loader.load( 'ext.kartographer.dialog.sidebar' );
		} );
	}
	// If the window was already open, trigger wikipage.maps
	// otherwise let the ready() of the window handle this.
	if ( dialog.isOpened() ) {
		mw.hook( 'wikipage.maps' ).fire( dialog.map, true /* isFullScreen */ );
	}
};

MapDialog.prototype.addFooterButton = function () {
	var dialog = this,
		$buttonContainer, $inlineContainer;

	// Create footer toggle button
	dialog.$captionContainer = dialog.$element.find( '.mw-kartographer-captionfoot' );
	$buttonContainer = dialog.$element.find( '.mw-kartographer-buttonfoot' );
	$inlineContainer = dialog.$element.find( '.mw-kartographer-inlinefoot' );

	if ( !dialog.mapDetailsButton ) {
		dialog.mapDetailsButton = new OO.ui.ToggleButtonWidget( {
			label: mw.msg( 'kartographer-sidebar-togglebutton' )
		} );
		dialog.mapDetailsButton.connect( dialog, { change: 'toggleSideBar' } );
	}
	if ( !dialog.mapNearbyButton && mw.config.get( 'wgKartographerNearby' ) &&
		( !OO.ui.isMobile() || mw.config.get( 'wgKartographerNearbyOnMobile' ) )
	) {
		dialog.mapNearbyButton = new OO.ui.ToggleButtonWidget( {
			label: mw.msg( 'kartographer-sidebar-nearbybutton' )
		} );
		dialog.mapNearbyButton.connect( dialog, { change: 'toggleNearbyLayerWrapper' } );
	}

	if ( !dialog.$captionContainer.length ) {
		dialog.$captionContainer = $( '<div>' )
			.addClass( 'mw-kartographer-captionfoot' );
	}

	if ( !$buttonContainer.length ) {
		$buttonContainer = $( '<div>' )
			.addClass( 'mw-kartographer-buttonfoot' );
	}
	if ( dialog.mapNearbyButton ) {
		$buttonContainer.append( dialog.mapNearbyButton.$element );
	}
	$buttonContainer.append( dialog.mapDetailsButton.$element );

	if ( !$inlineContainer.length ) {
		$inlineContainer = $( '<div>' )
			.addClass( 'mw-kartographer-inlinefoot' );
	}
	$inlineContainer.append(
		$buttonContainer,
		dialog.$captionContainer
	);

	// Add the button to the footer
	dialog.$foot.append( $inlineContainer );

	if ( dialog.map ) {
		dialog.$captionContainer
			.text( dialog.map.captionText );
	}
};

/**
 * @param {boolean} [open] If the sidebar should be shown or not, omit to toggle
 */
MapDialog.prototype.toggleSideBar = function ( open ) {
	var dialog = this;

	mw.loader.using( 'ext.kartographer.dialog.sidebar' ).then( function () {
		if ( !dialog.sideBar ) {
			var SideBar = require( 'ext.kartographer.dialog.sidebar' );
			dialog.sideBar = new SideBar( { dialog: dialog } );
			dialog.sideBar.toggle( true );
		}

		open = open === undefined ? !dialog.mapDetailsButton.getValue() : open;

		if ( dialog.mapDetailsButton.getValue() !== open ) {
			dialog.mapDetailsButton.setValue( open );
			// This `change` event callback is fired again, so skip here.
			return;
		}

		// Animations only work if content is visible
		dialog.sideBar.$el.attr( 'aria-hidden', null );
		setTimeout( function () {
			dialog.$body.toggleClass( 'mw-kartographer-mapDialog-sidebar-opened', open );
			setTimeout( function () {
				// Ensure proper hidden content after animation finishes
				dialog.sideBar.$el.attr( 'aria-hidden', !open );
			}, 100 /* Duration of the CSS animation */ );
		} );
	} );
};

/**
 * @param {boolean} showNearby
 */
MapDialog.prototype.toggleNearbyLayerWrapper = function ( showNearby ) {
	if ( mw.config.get( 'wgKartographerNearbyClustering' ) ) {
		mw.loader.using( 'ext.kartographer.lib.leaflet.markercluster' )
			.then( this.toggleNearbyLayer.bind( this, showNearby, true ) );
	} else {
		this.toggleNearbyLayer( showNearby );
	}
};

/**
 * @param {boolean} showNearby
 * @param {boolean} [enableClustering]
 */
MapDialog.prototype.toggleNearbyLayer = function ( showNearby, enableClustering ) {
	if ( this.map ) {
		if ( !this.nearby ) {
			var Nearby = require( './nearby.js' );
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
	var dialog = this;

	if ( !action ) {
		return new OO.ui.Process( function () {
			if ( dialog.map ) {
				dialog.map.closeFullScreen();
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
	var map = this.map,
		offsetX = isSidebarOpen ? SIDEBAR_WIDTH / -2 : 0,
		offsetY = FOOTER_HEIGHT / -2,
		targetPoint = map.project( map.getCenter(), map.getZoom() ).subtract( [ offsetX, offsetY ] ),
		targetLatLng = map.unproject( targetPoint, map.getZoom() );

	map.setView( targetLatLng, map.getZoom() );
};

MapDialog.prototype.getSetupProcess = function ( options ) {
	return MapDialog.super.prototype.getSetupProcess.call( this, options )
		.next( function () {
			var dialog = this,
				isFirstTimeOpen = !dialog.mapDetailsButton;

			if ( isFirstTimeOpen ) {
				// The button does not exist yet, add it
				dialog.addFooterButton();
			}

			if ( options.map && options.map !== dialog.map ) {
				this.setMap( options.map );
			}
		}, this );
};

MapDialog.prototype.getReadyProcess = function ( data ) {
	return MapDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			if ( mw.eventLog ) {
				mw.eventLog.submit( 'mediawiki.maps_interaction', {
					$schema: '/analytics/mediawiki/maps/interaction/1.0.0',
					action: 'fullscreen'
				} );
			}
			this.seenNearby = false;

			if ( !this.map ) {
				return;
			}
			this.map.doWhenReady( function () {
				mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
			}, this );
		}, this );
};

MapDialog.prototype.getHoldProcess = function ( data ) {
	return MapDialog.super.prototype.getHoldProcess.call( this, data )
		.next( function () {
			// T297519: Disable touch/mouse early to not cause chaos on "dragend" and such
			this.map.dragging.disable();
			this.map.touchZoom.disable();
			this.map.doubleClickZoom.disable();
			this.map.scrollWheelZoom.disable();
			this.map.keyboard.disable();
		}, this );
};

MapDialog.prototype.getTeardownProcess = function ( data ) {
	return MapDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			if ( this.mapNearbyButton ) {
				this.mapNearbyButton.setValue( false );
			}
			if ( this.map ) {
				this.map.remove();
				this.map = null;
			}
			this.$body.empty();
		}, this );
};

module.exports = MapDialog;
