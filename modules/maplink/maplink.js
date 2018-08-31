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
module.exports = ( function ( $, mw, router ) {

	/**
	 * References the maplinks of the page.
	 *
	 * @type {HTMLElement[]}
	 */
	var maplinks = [],
		/**
		 * @private
		 * @ignore
		 */
		routerInited = false;

	/**
	 * This code will be executed once the article is rendered and ready.
	 *
	 * @ignore
	 */
	mw.hook( 'wikipage.content' ).add( function () {

		// `wikipage.content` may be fired more than once.
		$.each( maplinks, function () {
			maplinks.pop().$container.off( 'click.kartographer' );
		} );

		if ( routerInited ) {
			return;
		}
		// execute this piece of code only once
		routerInited = true;

		// Opens a maplink in full screen. #/maplink(/:zoom)(/:latitude)(/:longitude)
		// Examples:
		//     #/maplink/0
		//     #/maplink/0/5
		//     #/maplink/0/16/-122.4006/37.7873
		router.route( /maplink\/([0-9]+)(?:\/([0-9]+))?(?:\/([+-]?\d+\.?\d{0,5})?\/([+-]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
			var link = maplinks[ maptagId ],
				position;

			if ( !link ) {
				router.navigate( '' );
				return;
			}

			if ( zoom !== undefined && latitude !== undefined && longitude !== undefined ) {
				position = {
					center: [ +latitude, +longitude ],
					zoom: +zoom
				};
			}

			link.openFullScreen( position );
		} );

		// Check if we need to open a map in full screen.
		router.checkRoute();
	} );

	return maplinks;
}(
	jQuery,
	mediaWiki,
	require( 'mediawiki.router' ),
	require( 'ext.kartographer.linkbox' )
) );
