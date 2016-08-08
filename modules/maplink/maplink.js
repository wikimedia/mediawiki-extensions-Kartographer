/* globals module, require */
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
module.exports = ( function ( $, mw, router, kartobox ) {

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
			style: $el.data( 'style' ),
			overlays: $el.data( 'overlays' ) || []
		};
	}

	/**
	 * Formats center if valid.
	 *
	 * @param {string|number} latitude
	 * @param {string|number} longitude
	 * @return {Array|undefined}
	 * @private
	 */
	function validCenter( latitude, longitude ) {
		latitude = +latitude;
		longitude = +longitude;

		if ( !isNaN( latitude ) && !isNaN( longitude ) ) {
			return [ latitude, longitude ];
		}
	}

	/**
	 * Formats zoom if valid.
	 *
	 * @param {string|number} zoom
	 * @return {number|undefined}
	 * @private
	 */
	function validZoom( zoom ) {
		zoom = +zoom;

		if ( !isNaN( zoom ) ) {
			return zoom;
		}
	}

	/**
	 * This code will be executed once the article is rendered and ready.
	 *
	 * @ignore
	 */
	mw.hook( 'wikipage.content' ).add( function ( ) {

		// `wikipage.content` may be fired more than once.
		$.each( maplinks, function () {
			maplinks.pop().$container.off( 'click.kartographer' );
		} );

		// Some links might be displayed outside of $content, so we need to
		// search outside. This is an anti-pattern and should be improved...
		// Meanwhile #content is better than searching the full document.
		$( '.mw-kartographer-maplink', '#content' ).each( function ( index ) {
			var data = getMapData( this );

			maplinks[ index ] = kartobox.link( {
				container: this,
				center: data.latitude && data.latitude ? [ data.latitude, data.longitude ] : 'auto',
				zoom: data.zoom || 'auto',
				dataGroups: data.overlays,
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
		router.route( /maplink\/([0-9]+)(?:\/([0-9]+))?(?:\/([\-\+]?\d+\.?\d{0,5})?\/([\-\+]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
			var link = maplinks[ maptagId ];

			if ( !link ) {
				router.navigate( '' );
				return;
			}

			link.openFullScreen( {
				center: validCenter( latitude, longitude ),
				zoom: validZoom( zoom )
			} );
		} );

		// Check if we need to open a map in full screen.
		router.checkRoute();
	} );

	return maplinks;
} )(
	jQuery,
	mediaWiki,
	require( 'mediawiki.router' ),
	require( 'ext.kartographer.box' )
);
