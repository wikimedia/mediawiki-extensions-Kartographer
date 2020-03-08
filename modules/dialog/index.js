/**
 * Module to help rendering maps in a full screen dialog.
 *
 * @alias ext.kartographer.dialog
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
	mapDialog = null;
	windowManager = null;
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
					map.parentMap.setView(
						map.getCenter(),
						map.getZoom()
					);
				}
				mapDialog = null;
				windowManager = null;
			} );
		} else if ( dialog.map !== map ) {
			dialog.setup( { map: map } );
			dialog.ready( { map: map } );
		}
	},

	/**
	 * Opens the map dialog, creates the map and renders it.
	 *
	 * @param {Object} mapObject
	 * @param {Function} mapCb
	 */
	renderNewMap: function ( mapObject, mapCb ) {

		var manager = getWindowManager(),
			dialog = getMapDialog(),
			map, instance;

		function createAndRenderMap() {
			mw.loader.using( 'ext.kartographer.box' ).then( function () {
				map = require( 'ext.kartographer.box' ).map( mapObject );

				if ( map.useRouter && !routerEnabled ) {
					router.on( 'route', closeIfNotMapRoute );
					router.route( '', closeIfNotMapRoute );
					routerEnabled = true;
				}

				dialog.setup( { map: map } );
				dialog.ready( { map: map } );

				mapCb( map );
			} );
		}

		if ( manager.getCurrentWindow() ) {
			createAndRenderMap();
		} else {
			instance = manager.openWindow( dialog, {} );
			instance.opened.then( function () {
				createAndRenderMap();
			} );
			instance.closing.then( function () {
				if ( map.parentMap ) {
					map.parentMap.setView(
						map.getCenter(),
						map.getZoom()
					);
				}
				mapDialog = null;
				windowManager = null;
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
	}
};
