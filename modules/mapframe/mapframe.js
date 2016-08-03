/* globals module, require */
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
module.exports = ( function ( $, mw, kartobox, router ) {

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
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var mapsInArticle = [],
			promises = [];

		// `wikipage.content` may be fired more than once.
		$.each( maps, function () {
			maps.pop().remove();
		} );

		// need to find .mw-kartographer-interactive:not(.mw-kartographer-map) for backward compatibility
		$content.find( '.mw-kartographer-map, .mw-kartographer-interactive:not(.mw-kartographer-map)' ).each( function ( index ) {
			var map, data,
				container = this,
				$container = $( this ),
				deferred = $.Deferred();

			data = getMapData( container );

			if ( data ) {
				data.enableFullScreenButton = true;

				map = kartobox.map( {
					container: container,
					center: validCenter( data.latitude, data.longitude ),
					zoom: validZoom( data.zoom ),
					fullScreenRoute: '/map/' + index,
					allowFullScreen: true,
					dataGroups: data.overlays
				} );

				mapsInArticle.push( map );
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

				promises.push( deferred.promise() );
			}
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
			router.route( /map\/([0-9]+)(?:\/([0-9]+))?(?:\/([\-\+]?\d+\.?\d{0,5})?\/([\-\+]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
				var map = maps[ maptagId ];

				if ( !map ) {
					router.navigate( '' );
					return;
				}

				map.openFullScreen( {
					center: validCenter( latitude, longitude ),
					zoom: validZoom( zoom )
				} );
			} );

			// Check if we need to open a map in full screen.
			router.checkRoute();
		} );
	} );

	return maps;
} )(
	jQuery,
	mediaWiki,
	require( 'ext.kartographer.box' ),
	require( 'mediawiki.router' )
);
