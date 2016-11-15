/* globals module, require */
/**
 * Dialog for displaying maps in full screen mode.
 *
 * See [OO.ui.Dialog](https://doc.wikimedia.org/oojs-ui/master/js/#!/api/OO.ui.Dialog)
 * documentation for more details.
 *
 * @class Kartographer.Dialog.DialogClass
 * @extends OO.ui.Dialog
 */
module.Dialog = ( function ( $, mw, CloseFullScreenControl, router ) {

	/**
	 * @constructor
	 * @type {Kartographer.Dialog.DialogClass}
	 */
	var MapDialog = function () {
		// Parent method
		MapDialog.super.apply( this, arguments );
	};

	/* Inheritance */

	OO.inheritClass( MapDialog, OO.ui.Dialog );

	/* Static Properties */

	MapDialog.static.size = 'full';

	/* Methods */

	MapDialog.prototype.initialize = function () {
		this.$mapDetailsButton = null;

		// Parent method
		MapDialog.super.prototype.initialize.apply( this, arguments );

		this.$body
			.append( '<div class="kartographer-mapDialog-loading"></div>' );
		this.$foot
			.addClass( 'mw-kartographer-mapDialog-foot' );

		this.map = null;
	};

	MapDialog.prototype.addFooterButton = function () {
		var dialog = this;
		mw.loader.using( 'oojs-ui-widgets' ).done( function () {
			$( function () {

				// Create footer toggle button
				var button = dialog.$mapDetailsButton = new OO.ui.ToggleButtonWidget( {
						label: mw.msg( 'kartographer-sidebar-togglebutton' ),
						icon: 'info',
						iconTitle: mw.msg( 'kartographer-sidebar-togglebutton' )
					} ),
					$captionContainer = dialog.$captionContainer = $( '<div class="mw-kartographer-captionfoot">' ),
					$buttonContainer = $( '<div class="mw-kartographer-buttonfoot">' ),
					$inlineContainer = $( '<div class="mw-kartographer-inlinefoot">' )
						.append( $buttonContainer, $captionContainer );

				if ( dialog.map ) {
					$captionContainer
						.attr( 'title', dialog.map.captionText )
						.text( dialog.map.captionText );
				}

				$buttonContainer.append( button.$element );

				// Add the button to the footer
				dialog.$foot.append( $inlineContainer );

				button.on( 'change', dialog.toggleSideBar, null, dialog );
			} );
		} );
	};

	MapDialog.prototype.toggleSideBar = function ( open ) {
		var dialog = this;
		mw.loader.using( 'ext.kartographer.dialog.sidebar' ).done( function () {
			var SideBar;
			if ( !dialog.sideBar ) {
				SideBar = mw.loader.require( 'ext.kartographer.dialog.sidebar' );
				dialog.sideBar = new SideBar( { dialog: dialog } );
			}

			open = ( typeof open === 'undefined' ) ? !dialog.$mapDetailsButton.value : open;

			if ( dialog.$mapDetailsButton.value !== open ) {
				dialog.$mapDetailsButton.setValue( open );
				// This `change` event callback is fired again, so skip here.
				return;
			}

			mw.track( 'mediawiki.kartographer', {
				action: open ? 'sidebar-show' : 'sidebar-hide',
				isFullScreen: true,
				feature: dialog.map.parentMap || dialog.map.parentLink
			} );
			dialog.sideBar.toggle( open );
		} );
	};

	MapDialog.prototype.getActionProcess = function ( action ) {
		var dialog = this;

		if ( !action ) {
			return new OO.ui.Process( function () {
				if ( dialog.map ) {
					dialog.map.closeFullScreen();
					dialog.map.remove();
					dialog.map = null;
				}
			} );
		}
		return MapDialog.super.prototype.getActionProcess.call( this, action );
	};

	/**
	 * Tells the router to navigate to the current full screen map route.
	 */
	MapDialog.prototype.updateHash = function () {
		var hash = this.map.getHash();

		// Avoid extra operations
		if ( this.lastHash !== hash ) {
			/* eslint-disable no-underscore-dangle */
			this.map._updatingHash = true;
			/* eslint-enable no-underscore-dangle */
			router.navigate( hash );
			this.lastHash = hash;
		}
	};

	/**
	 * Listens to `moveend` event and calls {@link #updateHash}.
	 *
	 * This method is throttled, meaning the method will be called at most once per
	 * every 250 milliseconds.
	 */
	MapDialog.prototype.onMapMove = OO.ui.throttle( function () {
		// Stop listening to `moveend` event while we're
		// manually moving the map (updating from a hash),
		// or if the map is not yet loaded.
		/* eslint-disable no-underscore-dangle */
		if ( this.movingMap || !this.map || !this.map._loaded ) {
			return false;
		}
		/* eslint-enable no-underscore-dangle */
		this.updateHash();
	}, 250 );

	MapDialog.prototype.getSetupProcess = function ( options ) {
		return MapDialog.super.prototype.getSetupProcess.call( this, options )
			.next( function () {
				var dialog = this;

				if ( options.map && options.map !== dialog.map ) {

					if ( dialog.map ) {
						dialog.map.remove();
					}

					dialog.map = options.map;

					dialog.map.closeFullScreenControl = new CloseFullScreenControl( { position: 'topright' } )
						.addTo( dialog.map );

					dialog.$body.append(
						dialog.map.$container.css( 'position', '' )
					);

					if ( dialog.$captionContainer ) {
						dialog.$captionContainer
							.attr( 'title', dialog.map.captionText )
							.text( dialog.map.captionText );
					}

					if ( !dialog.$mapDetailsButton ) {
						dialog.addFooterButton();
					} else if ( dialog.sideBar ) {
						dialog.sideBar.tearDown();
						dialog.map.doWhenReady( function () {
							dialog.sideBar.render();
						} );
					}
				}
			}, this );
	};

	MapDialog.prototype.getReadyProcess = function ( data ) {
		return MapDialog.super.prototype.getReadyProcess.call( this, data )
			.next( function () {

				if ( !this.map ) {
					return;
				}
				this.map.doWhenReady( function () {
					mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
				}, this );
			}, this );
	};

	MapDialog.prototype.getTeardownProcess = function ( data ) {
		return MapDialog.super.prototype.getTeardownProcess.call( this, data )
			.next( function () {
				if ( this.map ) {
					this.map.remove();
					this.map = null;
				}
				this.$body.empty();
			}, this );
	};

	return function () {
		return new MapDialog();
	};

}(
	jQuery,
	mediaWiki,
	module.CloseFullScreenControl,
	require( 'mediawiki.router' )
) );
