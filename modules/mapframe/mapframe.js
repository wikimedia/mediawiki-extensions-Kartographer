/* globals require */
/**
 * Frame module.
 *
 * Once the page is loaded and ready, turn all `<mapframe/>` tags into
 * interactive maps.
 *
 * @alias Frame
 * @alias ext.kartographer.frame
 * @class Kartographer.Frame
 * @singleton
 */
module.exports = ( function ( util, kartobox, router ) {

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
	 * @return {Object} Map properties
	 * @return {number} return.latitude
	 * @return {number} return.longitude
	 * @return {number} return.zoom
	 * @return {string} return.lang Language code
	 * @return {string} return.style Map style
	 * @return {string[]} return.overlays Overlay groups
	 * @return {string} return.captionText
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
	 * @param {Object} data
	 * @param {jQuery} $container
	 * @return {Object} map KartographerMap
	 */
	function initMapBox( data, $container ) {
		var map,
			index = maps.length,
			container = $container.get( 0 );

		data.enableFullScreenButton = true;

		map = kartobox.map( {
			featureType: 'mapframe',
			container: container,
			center: [ data.latitude, data.longitude ],
			zoom: data.zoom,
			lang: data.lang,
			fullScreenRoute: '/map/' + index,
			allowFullScreen: true,
			dataGroups: data.overlays,
			captionText: data.captionText
		} );

		$container.removeAttr( 'href' );

		map.doWhenReady( function () {
			map.$container.css( 'backgroundImage', '' );
			map.$container.find( '.leaflet-marker-icon' ).each( function () {
				var height = $( this ).height();
				$( this ).css( {
					clip: 'rect(auto auto ' + ( ( height / 2 ) + 10 ) + 'px auto)'
				} );
			} );
		} );

		maps[ index ] = map;

		// Special case for collapsible maps.
		// When the container is hidden Leaflet is not able to
		// calculate the expected size when visible. We need to force
		// updating the map to the new container size on `expand`.
		if ( !$container.is( ':visible' ) ) {
			$container.closest( '.mw-collapsible' )
				.on( 'afterExpand.mw-collapsible', function () {
					map.invalidateSize();
				} );
		}

		return map;
	}

	/**
	 * Create a mapbox from a given element.
	 *
	 * @param {HTMLElement} element Parsed <mapframe> element
	 */
	function initMapframeFromElement( element ) {
		var map,
			container = element,
			$container = $( element ),
			data = getMapData( container );

		map = initMapBox( data, $container );
		mw.hook( 'wikipage.maps' ).fire( [ map ], false /* isFullScreen */ );
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
		while ( maps.length ) {
			maps.pop().remove();
		}

		$content.find( '.mw-kartographer-map[data-mw="interface"]' ).each( function () {
			var data,
				container = this,
				$container = $( this ),
				deferred = $.Deferred();

			data = getMapData( container );

			mapsInArticle.push( initMapBox( data, $container ) );

			promises.push( deferred.promise() );
		} );

		// Allow customizations of interactive maps in article.
		$.when( promises ).then( function () {
			mw.hook( 'wikipage.maps' ).fire( mapsInArticle, false /* isFullScreen */ );

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
				var map = maps[ maptagId ],
					position;

				if ( !map ) {
					router.navigate( '' );
					return;
				}

				if ( zoom !== undefined && latitude !== undefined && longitude !== undefined ) {
					position = {
						center: [ +latitude, +longitude ],
						zoom: +zoom
					};
				} else {
					position = map.getInitialMapPosition();
				}

				map.openFullScreen( position );
			} );

			// Check if we need to open a map in full screen.
			router.checkRoute();
		} );
	} );

	return {
		initMapframeFromElement: initMapframeFromElement
	};
}(
	require( 'ext.kartographer.util' ),
	require( 'ext.kartographer.box' ),
	require( 'mediawiki.router' )
) );
