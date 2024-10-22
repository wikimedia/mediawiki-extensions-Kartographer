/**
 * Static Frame module.
 *
 * Once the page is loaded and ready, turn all `<mapframe/>` tags into links
 * that open a full screen map.
 *
 * @borrows Kartographer.StaticFrame as ext.kartographer.staticframe
 * @class Kartographer.StaticFrame
 * @singleton
 */
const util = require( 'ext.kartographer.util' );
const kartolink = require( 'ext.kartographer.linkbox' );
const router = require( 'mediawiki.router' );
/**
 * References the maplinks wrapping static mapframe containers of the page.
 *
 * @type {Kartographer.Linkbox.LinkClass[]}
 */
const maplinks = [];
/**
 * @private
 */
let routerInited = false;

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
	const $caption = $el.parent().find( '.thumbcaption' );
	let captionText = '';

	if ( $caption[ 0 ] ) {
		captionText = $caption.get( 0 ).innerText;
	}

	return {
		latitude: +$el.data( 'lat' ),
		longitude: +$el.data( 'lon' ),
		zoom: +$el.data( 'zoom' ),
		lang: $el.data( 'lang' ) || util.getDefaultLanguage(),
		style: $el.data( 'style' ),
		overlays: $el.data( 'overlays' ) || [],
		captionText: captionText
	};
}

/**
 * This code will be executed once the article is rendered and ready.
 */
mw.hook( 'wikipage.content' ).add( ( $content ) => {
	// `wikipage.content` may be fired more than once.
	while ( maplinks.length ) {
		maplinks.pop().$container.off( 'click.kartographer' );
	}

	// Attributes starting with "data-mw" are banned from user content in Sanitizer; checking for their presence
	// guarantees that they were generated by Kartographer
	$content.find( '.mw-kartographer-map[data-mw-kartographer], .mw-kartographer-map[data-mw="interface"]' )
		.each( function ( index ) {
			const container = this;
			const $container = $( container );

			mw.loader.using( 'oojs-ui', () => {
				const button = new OO.ui.ButtonWidget( {
					// In static mode this button is just a visual hint but doesn't have its own action
					tabIndex: -1,
					icon: 'fullScreen',
					framed: true
				} );

				$container.append(
					$( '<div>' ).addClass( 'mw-kartographer-fullScreen' ).append( button.$element ),
					$( '<div>' ).addClass( 'mw-kartographer-attribution' ).html(
						mw.message( 'kartographer-attribution-short' ).parse()
					)
				);
			} );

			$container.attr( {
				title: mw.msg( 'kartographer-fullscreen-text' ),
				href: '#/map/' + index
			} );

			const data = getMapData( container );

			data.enableFullScreenButton = true;

			maplinks[ index ] = kartolink.link( {
				featureType: 'mapframe',
				container: container,
				center: [ data.latitude, data.longitude ],
				zoom: data.zoom,
				lang: data.lang,
				dataGroups: data.overlays,
				captionText: data.captionText,
				fullScreenRoute: '/map/' + index
			} );
		} );

	// Allow customizations of interactive maps in article.
	if ( routerInited ) {
		return;
	}
	// execute this piece of code only once
	routerInited = true;

	// Opens a map in full screen. #/map(/:zoom)(/:latitude)(/:longitude)
	// Examples:
	//     #/map/0
	//     #/map/0/5
	//     #/map/0/16/-122.4006/37.7873

	router.addRoute( /map\/([0-9]+)(?:\/([0-9]+))?(?:\/([+-]?\d+\.?\d{0,5})?\/([+-]?\d+\.?\d{0,5})?)?/, ( maptagId, zoom, latitude, longitude ) => {
		const link = maplinks[ maptagId ];

		if ( !link ) {
			router.navigate( '' );
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

	// Check if we need to open a map in full screen.
	router.checkRoute();
} );

module.exports = maplinks;
