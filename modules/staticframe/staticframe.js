/**
 * Static Frame module.
 *
 * Once the page is loaded and ready, turn all `<mapframe/>` tags into links
 * that open a full screen map.
 *
 * @alternateClassName ext.kartographer.staticframe
 * @class Kartographer.StaticFrame
 * @singleton
 */
var util = require( 'ext.kartographer.util' ),
	kartolink = require( 'ext.kartographer.linkbox' ),
	router = require( 'mediawiki.router' ),
	/**
	 * References the maplinks wrapping static mapframe containers of the page.
	 *
	 * @type {Kartographer.Linkbox.LinkClass[]}
	 */
	maplinks = [],
	/**
	 * @private
	 * @ignore
	 */
	routerInited = false;

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
	var $el = $( element ),
		$caption = $el.parent().find( '.thumbcaption' ),
		captionText = '';

	if ( $caption[ 0 ] ) {
		captionText = $caption.text();
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
 *
 * @ignore
 */
mw.hook( 'wikipage.content' ).add( function ( $content ) {
	// `wikipage.content` may be fired more than once.
	while ( maplinks.length ) {
		maplinks.pop().$container.off( 'click.kartographer' );
	}

	$content.find( '.mw-kartographer-map[data-mw="interface"]' ).each( function ( index ) {
		var container = this,
			$container = $( container ),
			link,
			data;

		mw.loader.using( 'oojs-ui', function () {
			var button = new OO.ui.ButtonWidget( {
					icon: 'fullScreen',
					title: mw.msg( 'kartographer-fullscreen-text' ),
					framed: true
				} ),
				$div = $( '<div>' ).addClass( 'mw-kartographer-fullScreen' ).append( button.$element );

			$container.append( $div );
			$container.append(
				'<div class="mw-kartographer-attribution">' +
				mw.message( 'kartographer-attribution-short' ).parse() +
				'</div>'
			);
		} );

		$container.attr( 'href', '#/map/' + index );

		data = getMapData( container );

		data.enableFullScreenButton = true;

		link = kartolink.link( {
			featureType: 'mapframe',
			container: container,
			center: [ data.latitude, data.longitude ],
			zoom: data.zoom,
			lang: data.lang,
			dataGroups: data.overlays,
			captionText: data.captionText,
			fullScreenRoute: '/map/' + index
		} );

		maplinks[ index ] = link;
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
	router.route( /map\/([0-9]+)(?:\/([0-9]+))?(?:\/([+-]?\d+\.?\d{0,5})?\/([+-]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
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

module.exports = maplinks;
