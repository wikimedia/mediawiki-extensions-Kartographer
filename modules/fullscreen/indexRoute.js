/* globals module, require */
/**
 * Module executing code to add an index "" route that closes the map dialog.
 *
 * @alias indexRoute
 * @class Kartographer.Fullscreen.indexRoute
 * @private
 */
module.indexRoute = ( function ( kartographer, router ) {

	// Add index route.
	router.route( '', function () {
		// TODO: mapDialog is undefined
		if ( kartographer.getMapDialog() ) {
			kartographer.getMapDialog().close();
		}
	} );

} )( require( 'ext.kartographer.init' ), require( 'mediawiki.router' ) );
