/**
 * Module to help rendering maps in a full screen dialog.
 *
 * @alternateClassName ext.kartographer.dialog
 * @class Kartographer.Dialog
 * @singleton
 */
var Dialog = require( './dialog.js' ),
	router = require( 'mediawiki.router' ),
	windowManager, mapDialog, routerEnabled;

function getMapDialog() {
	mapDialog = mapDialog || new Dialog();
	return mapDialog;
}

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

function closeIfNotMapRoute( routeEv ) {
	var isMapRoute = routeEv && /^\/(map|maplink)\//.test( routeEv.path );
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
		var manager = getWindowManager(),
			dialog = getMapDialog(),
			instance;

		if ( map.useRouter && !routerEnabled ) {
			router.on( 'route', closeIfNotMapRoute );
			router.route( '', closeIfNotMapRoute );
			routerEnabled = true;
		}

		if ( !manager.getCurrentWindow() ) {
			instance = manager.openWindow( dialog, { map: map } );
			instance.closing.then( function () {
				if ( map.parentMap ) {
					// FIXME we need to correct for the footerbar offset
					map.parentMap.setView(
						map.getCenter(),
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
		var manager = getWindowManager(),
			dialog = getMapDialog(),
			deferred = $.Deferred(),
			promises = [ mw.loader.using( 'ext.kartographer.box' ) ],
			instance;

		if ( !manager.getCurrentWindow() ) {
			// We open the window immediately to guarantee responsiveness
			// Only THEN we set the map
			instance = manager.openWindow( dialog, {} );
			promises.push( instance.opened );
		}
		$.when.apply( $, promises ).then( function () {
			var map = require( 'ext.kartographer.box' ).map( mapObject );
			deferred.resolve( map );
			if ( map.useRouter && !routerEnabled ) {
				router.on( 'route', closeIfNotMapRoute );
				router.route( '', closeIfNotMapRoute );
				routerEnabled = true;
			}
			dialog.setMap( map );
		}, function () {
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
		} else {
			close();
		}
	}
};
