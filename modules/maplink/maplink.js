/**
 * Link module.
 *
 * Once the page is loaded and ready, turn all `<maplink/>` tags into a link
 * that opens the map in full screen mode.
 *
 * @alternateClassName Link
 * @alternateClassName ext.kartographer.link
 * @class Kartographer.Link
 * @singleton
 */
const router = require( 'mediawiki.router' );
const kartolink = require( 'ext.kartographer.linkbox' );
/**
 * References the maplinks of the page.
 *
 * @type {Kartographer.Linkbox.LinkClass[]}
 */
const maplinks = [];

/**
 * Gets the map data attached to an element.
 *
 * @param {HTMLElement} element Element
 * @return {Object} Map properties
 * @return {number} return.latitude
 * @return {number} return.longitude
 * @return {number} return.zoom
 * @return {string} return.style Map style
 * @return {string[]} return.overlays Overlay groups
 */
function getMapData( element ) {
	const $el = $( element );

	return {
		latitude: +$el.data( 'lat' ),
		longitude: +$el.data( 'lon' ),
		zoom: +$el.data( 'zoom' ),
		lang: $el.data( 'lang' ),
		style: $el.data( 'style' ),
		captionText: $el.get( 0 ).innerText,
		overlays: $el.data( 'overlays' ) || []
	};
}

/**
 * Attach the maplink handler.
 *
 * @param {jQuery} $content jQuery element with the content
 */
function handleMapLinks( $content ) {
	// Attributes starting with "data-mw" are banned from user content in Sanitizer; checking for their presence
	// guarantees that they were generated by Kartographer
	$content.find( '.mw-kartographer-maplink[data-mw-kartographer]' )
		.each( function () {
			const data = getMapData( this );

			const index = maplinks.length;
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

	// Check if we need to open a map in full screen.
	router.checkRoute();
}

/**
 * Activate the router for the full screen mode.
 */
function activateRouter() {
	// Opens a maplink in full screen. #/maplink(/:zoom)(/:latitude)(/:longitude)
	// Examples:
	//     #/maplink/0
	//     #/maplink/0/5
	//     #/maplink/0/16/-122.4006/37.7873
	router.route( /maplink\/([0-9]+)(?:\/([0-9]+))?(?:\/([+-]?\d+\.?\d{0,5})?\/([+-]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
		const link = maplinks[ maptagId ];

		if ( !link ) {
			return;
		}

		let position;
		if ( zoom !== undefined && latitude !== undefined && longitude !== undefined ) {
			position = {
				center: [ +latitude, +longitude ],
				zoom: +zoom
			};
		}
		link.openFullScreen( position );
	} );
}

activateRouter();

mw.hook( 'wikipage.indicators' ).add( handleMapLinks );
mw.hook( 'wikipage.content' ).add( handleMapLinks );

module.exports = maplinks;
