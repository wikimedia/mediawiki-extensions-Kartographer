/* globals require */
/**
 * Link module.
 *
 * Once the page is loaded and ready, turn all `<maplink/>` tags into a link
 * that opens the map in full screen mode.
 *
 * @alias Link
 * @alias ext.kartographer.link
 * @class Kartographer.Link
 * @singleton
 */
( function ( $, mw, kartographer, router ) {

	/**
	 * References the maplinks of the page.
	 *
	 * @type {HTMLElement[]}
	 * @member mw.kartographer
	 */
	mw.kartographer.maplinks = [];

	/**
	 * This code will be executed once the article is rendered and ready.
	 *
	 * @ignore
	 */
	mw.hook( 'wikipage.content' ).add( function ( ) {

		// Some links might be displayed outside of $content, so we need to
		// search outside. This is an anti-pattern and should be improved...
		// Meanwhile #content is better than searching the full document.
		$( '.mw-kartographer-link', '#content' ).each( function ( index ) {
			mw.kartographer.maplinks[ index ] = this;

			$( this ).data( 'maptag-id', index );
			this.href = '#' + '/maplink/' + index;
		} );

		// Opens a maplink in full screen. #/maplink(/:zoom)(/:latitude)(/:longitude)
		// Examples:
		//     #/maplink/0
		//     #/maplink/0/5
		//     #/maplink/0/16/-122.4006/37.7873
		router.route( /maplink\/([0-9]+)(?:\/([0-9]+))?(?:\/([\-\+]?\d+\.?\d{0,5})?\/([\-\+]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
			var link = mw.kartographer.maplinks[ maptagId ],
				data;

			if ( !link ) {
				router.navigate( '' );
				return;
			}
			data = kartographer.getMapData( link );
			mw.kartographer.openFullscreenMap( data, kartographer.getFullScreenState( zoom, latitude, longitude ) );
		} );

		// Check if we need to open a map in full screen.
		router.checkRoute();
	} );
} )(
	jQuery,
	mediaWiki,
	require( 'ext.kartographer.init' ),
	require( 'mediawiki.router' )
);
