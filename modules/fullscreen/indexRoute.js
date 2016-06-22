/* globals module, require */
module.indexRoute = ( function ( kartographer, router ) {

	// Add index route.
	router.route( '', function () {
		// TODO: mapDialog is undefined
		if ( kartographer.getMapDialog() ) {
			kartographer.getMapDialog().close();
		}
	} );

} )( require( 'ext.kartographer.init' ), require( 'mediawiki.router' ) );
