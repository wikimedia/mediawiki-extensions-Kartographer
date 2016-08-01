/* globals module, require */
/**
 * Module to help rendering maps in a full screen dialog.
 *
 * @alias ext.kartographer.dialog
 * @class Kartographer.Dialog
 * @singleton
 */
module.exports = ( function ( Dialog, router ) {

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
						// It takes 250ms for the dialog to open,
						// we'd better invalidate the size once it opened.
						// setTimeout( function () {
						// 	map.invalidateSize();
						// }, 300 );
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
		 * Closes the map dialog.
		 */
		close: function () {
			if ( mapDialog && mapDialog.map.useRouter ) {
				router.navigate( '' );
			} else {
				close();
			}
		}
	};
} )( module.Dialog, require( 'mediawiki.router' ) );
