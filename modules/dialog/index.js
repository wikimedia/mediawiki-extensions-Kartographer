/* globals module, require */
/**
 * Module to help rendering maps in a full screen dialog.
 *
 * @alias ext.kartographer.dialog
 * @class Kartographer.Dialog
 * @singleton
 */
module.exports = ( function ( CloseFullScreenControl, Dialog, router ) {

	var windowManager, mapDialog, routerEnabled;

	function getMapDialog() {
		mapDialog = mapDialog || new Dialog();
		return mapDialog;
	}

	function getWindowManager() {
		if ( !windowManager ) {
			windowManager = new OO.ui.WindowManager();
			$( 'body' ).append( windowManager.$element );
			getWindowManager().addWindows( [ getMapDialog() ] );
		}
		return windowManager;
	}

	function close() {
		if ( mapDialog ) {
			mapDialog.close();
		}
		mapDialog = null;
		windowManager = null;
	}

	return {
		/**
		 * Opens the map dialog and renders the map.
		 *
		 * @param {Kartographer.Box.MapClass} map
		 */
		render: function ( map ) {

			var window = getWindowManager(),
				dialog = getMapDialog();

			if ( map.useRouter && !routerEnabled ) {
				router.route( '', function () {
					close();
				} );
			}

			if ( !window.opened ) {
				getWindowManager()
					.openWindow( dialog, { map: map } )
					.then( function ( opened ) {
						return opened;
					} )
					.then( function ( closing ) {
						if ( map.parentMap ) {
							map.parentMap.setView(
								map.getCenter(),
								map.getZoom()
							);
						}
						dialog.close();
						mapDialog = null;
						windowManager = null;
						return closing;
					} );
			} else if ( dialog.map !== map ) {
				dialog.setup.call( dialog, { map: map } );
				dialog.ready.call( dialog, { map: map } );
			}
		},

		/**
		 * Opens the map dialog, creates the map and renders it.
		 *
		 * @param {Object} mapObject
		 * @param {Function} mapCb
		 */
		renderNewMap: function ( mapObject, mapCb ) {

			var window = getWindowManager(),
				dialog = getMapDialog(),
				map;

			function createAndRenderMap() {
				mw.loader.using( 'ext.kartographer.box' ).then( function () {
					map = mw.loader.require( 'ext.kartographer.box' ).map( mapObject );

					if ( map.useRouter && !routerEnabled ) {
						router.route( '', function () {
							close();
						} );
					}

					dialog.setup.call( dialog, { map: map } );
					dialog.ready.call( dialog, { map: map } );

					mapCb( map );
				} );
			}

			if ( window.opened ) {
				createAndRenderMap();
			} else {
				getWindowManager()
					.openWindow( dialog, {} )
					.then( function ( opened ) {
						createAndRenderMap();
						return opened;
					} )
					.then( function ( closing ) {
						if ( map.parentMap ) {
							map.parentMap.setView(
								map.getCenter(),
								map.getZoom()
							);
						}
						dialog.close();
						mapDialog = null;
						windowManager = null;
						return closing;
					} );
			}

		},

		/**
		 * Closes the map dialog.
		 */
		close: function () {
			if ( mapDialog && mapDialog.map.useRouter ) {
				router.navigate( '' );
			} else {
				close();
			}
		},

		/**
		 * @type {Kartographer.Dialog.CloseFullScreenControl}
		 * @ignore
		 */
		CloseFullScreenControl: CloseFullScreenControl
	};
} )( module.CloseFullScreenControl, module.Dialog, require( 'mediawiki.router' ) );
