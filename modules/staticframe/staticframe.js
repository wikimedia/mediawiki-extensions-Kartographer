/* globals require */
/**
 * Static Frame module.
 *
 * Once the page is loaded and ready, turn all `<mapframe/>` tags into links
 * that open a full screen map.
 *
 * @alias ext.kartographer.staticframe
 * @class Kartographer.StaticFrame
 * @singleton
 */
module.exports = ( function ( $, mw, util, kartolink, router ) {

	/**
	 * References the mapframe containers of the page.
	 *
	 * @type {HTMLElement[]}
	 */
	var maps = [],
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
		var $el = $( element ),
			$caption = $el.parent().find( '.thumbcaption' ),
			captionText = '';

		// Prevent users from adding map divs directly via wikitext
		if ( $el.attr( 'mw-data' ) !== 'interface' ) {
			return null;
		}

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
		var mapsInArticle = [],
			promises = [];

		// `wikipage.content` may be fired more than once.
		$.each( maps, function () {
			maps.pop().$container.off( 'click.kartographer' );
		} );

		$content.find( '.mw-kartographer-map' ).each( function ( index ) {

			var container = this,
				$container = $( container ),
				link,
				data,
				deferred = $.Deferred();

			mw.loader.using( 'oojs-ui', function () {
				var button = new OO.ui.ButtonWidget( {
						icon: 'fullScreen',
						title: mw.msg( 'kartographer-fullscreen-text' ),
						framed: true
					} ),
					$div = $( '<div class="mw-kartographer-fullScreen"></div>' ).append( button.$element );

				$container.append( $div );
				$container.append(
					'<div class="mw-kartographer-attribution">' +
					mw.message( 'kartographer-attribution-short' ).parse() +
					'</div>'
				);
			} );

			$container.attr( 'href', '#/map/' + index );

			data = getMapData( container );

			if ( data ) {
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

				mapsInArticle.push( link );
				maps[ index ] = link;

				promises.push( deferred.promise() );
			}
		} );

		// Allow customizations of interactive maps in article.
		$.when( promises ).then( function () {

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
				var link = maps[ maptagId ],
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
	} );

	return maps;
}(
	jQuery,
	mediaWiki,
	require( 'ext.kartographer.util' ),
	require( 'ext.kartographer.linkbox' ),
	require( 'mediawiki.router' )
) );
