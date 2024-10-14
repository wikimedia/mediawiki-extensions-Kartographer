/**
 * Module to help rendering maps in a full screen dialog.
 *
 * @borrows Kartographer.Dialog as ext.kartographer.dialog
 * @class Kartographer.Dialog
 * @singleton
 */
const Dialog = require( './dialog.js' );
const router = require( 'mediawiki.router' );
let windowManager, mapDialog, routerEnabled;

/**
 * @return {Kartographer.Dialog}
 */
function getMapDialog() {
	mapDialog = mapDialog || new Dialog();
	return mapDialog;
}

/**
 * @return {OO.ui.WindowManager}
 */
function getWindowManager() {
	if ( !windowManager ) {
		windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		getWindowManager().addWindows( [ getMapDialog() ] );
	}
	return windowManager;
}

function close() {
	if ( mapDialog ) {
		mapDialog.close();
	}
}

function onRouterRoute( routeEv ) {
	// The hash has been changed by some user action. If it is no longer
	// a known map route, close the map dialog.
	const isMapRoute = routeEv && /^\/(map|maplink)\//.test( routeEv.path );
	if ( !isMapRoute ) {
		close();
	}
}

module.exports = {
	/**
	 * Opens the map dialog and renders the map.
	 * Used by mapframe
	 *
	 * @param {Kartographer.Box.MapClass} map
	 */
	render: function ( map ) {
		const manager = getWindowManager();
		const dialog = getMapDialog();

		if ( map.useRouter && !routerEnabled ) {
			router.on( 'route', onRouterRoute );
			routerEnabled = true;
		}

		if ( !manager.getCurrentWindow() ) {
			const instance = manager.openWindow( dialog, { map: map } );
			instance.closing.then( () => {
				if ( map.parentMap && !map.parentMap.options.alwaysStatic ) {
					const targetPoint = map.project( map.getCenter(), map.getZoom() ).subtract( dialog.offset ),
						targetLatLng = map.unproject( targetPoint, map.getZoom() );
					map.parentMap.setView(
						targetLatLng,
						map.getZoom()
					);
				}
			} );
		} else {
			dialog.setMap( map );
		}
	},

	/**
	 * Opens the map dialog, creates the map and renders it.
	 * Used by staticframe and maplink.
	 *
	 * @param {Object} mapObject
	 * @return {jQuery.Promise} Promise which resolves when the map has been created from mapObject.
	 *                          The rendering process might not yet be finished.
	 */
	renderNewMap: function ( mapObject ) {
		const manager = getWindowManager();
		const dialog = getMapDialog();
		const deferred = $.Deferred();
		const promises = [ mw.loader.using( 'ext.kartographer.box' ) ];

		if ( !manager.getCurrentWindow() ) {
			// We open the window immediately to guarantee responsiveness
			// Only THEN we set the map
			const instance = manager.openWindow( dialog, {} );
			promises.push( instance.opened );
		}
		$.when.apply( $, promises ).then( () => {
			const map = require( 'ext.kartographer.box' ).map( mapObject );
			deferred.resolve( map );
			if ( map.useRouter && !routerEnabled ) {
				router.on( 'route', onRouterRoute );
				routerEnabled = true;
			}
			dialog.setMap( map );
		}, () => {
			deferred.reject();
		} );
		return deferred.promise();
	},

	/**
	 * Closes the map dialog.
	 */
	close: function () {
		if ( mapDialog && mapDialog.map.useRouter ) {
			router.navigate( '' );
			// #navigate uses history.pushState which doesn't trigger a
			// hashchange event, so we still need to close the dialog manually.
		}
		close();
	},

	private: {
		Nearby: require( './nearby.js' )
	}
};
