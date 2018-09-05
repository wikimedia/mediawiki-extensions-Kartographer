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
module.exports = ( function ( $, mw, router, kartolink ) {

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
	 * Gets the map data attached to an element.
	 *
	 * @param {HTMLElement} element Element
	 * @return {Object|null} Map properties
	 * @return {number} return.latitude
	 * @return {number} return.longitude
	 * @return {number} return.zoom
	 * @return {string} return.style Map style
	 * @return {string[]} return.overlays Overlay groups
	 */
	function getMapData( element ) {
		var $el = $( element );
		// Prevent users from adding map divs directly via wikitext
		if ( $el.attr( 'mw-data' ) !== 'interface' ) {
			return null;
		}

		return {
			latitude: +$el.data( 'lat' ),
			longitude: +$el.data( 'lon' ),
			zoom: +$el.data( 'zoom' ),
			lang: $el.data( 'lang' ),
			style: $el.data( 'style' ),
			captionText: $el.text(),
			overlays: $el.data( 'overlays' ) || []
		};
	}

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

		// Some links might be displayed outside of $content, so we need to
		// search outside. This is an anti-pattern and should be improved...
		// Meanwhile .mw-body is better than searching the full document.
		$( '.mw-kartographer-maplink', '.mw-body' ).each( function ( index ) {
			var data = getMapData( this );
			maplinks[ index ] = kartolink.link( {
				featureType: 'maplink',
				container: this,
				center: [ data.latitude, data.longitude ],
				zoom: data.zoom,
				lang: data.lang,
				dataGroups: data.overlays,
				captionText: data.captionText,
				fullScreenRoute: '/maplink/' + index
			} );
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
