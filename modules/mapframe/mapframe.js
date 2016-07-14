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
( function ( $, mw, kartographer, kartoLive, router ) {

	/**
	 * References the map containers of the page.
	 *
	 * @type {HTMLElement[]}
	 * @member mw.kartographer
	 */
	mw.kartographer.maps = [];

	/**
	 * Wraps a map container to make it (and its map) responsive on
	 * mobile (MobileFrontend).
	 *
	 * The initial `mapContainer`:
	 *
	 *     <div class="mw-kartographer-interactive" style="height: Y; width: X;">
	 *         <!-- this is the component carrying Leaflet.Map -->
	 *     </div>
	 *
	 * Becomes :
	 *
	 *     <div class="mw-kartographer-interactive mw-kartographer-responsive" style="max-height: Y; max-width: X;">
	 *         <div class="mw-kartographer-responder" style="padding-bottom: (100*Y/X)%">
	 *             <div>
	 *                 <!-- this is the component carrying Leaflet.Map -->
	 *             </div>
	 *         </div>
	 *     </div>
	 *
	 * **Note:** the container that carries the map data remains the initial
	 * `mapContainer` passed in arguments. Its selector remains `.mw-kartographer-interactive`.
	 * However it is now a sub-child that carries the map.
	 *
	 * **Note 2:** the CSS applied to these elements vary whether the map width
	 * is absolute (px) or relative (%). The example above describes the absolute
	 * width case.
	 *
	 * @param {HTMLElement} mapContainer Initial component to carry the map.
	 * @return {HTMLElement} New map container to carry the map.
	 * @private
	 */
	function responsiveContainerWrap( mapContainer ) {
		var $container = $( mapContainer ),
			$responder, $map,
			width = mapContainer.style.width,
			isRelativeWidth = width.slice( -1 ) === '%',
			height = +( mapContainer.style.height.slice( 0, -2 ) ),
			containerCss, responderCss;

		// Convert the value to a string.
		width = isRelativeWidth ? width : +( width.slice( 0, -2 ) );

		if ( isRelativeWidth ) {
			containerCss = {};
			responderCss = {
				// The inner container must occupy the full height
				height: height
			};
		} else {
			containerCss = {
				// Remove explicitly set dimensions
				width: '',
				height: '',
				// Prevent over-sizing
				'max-width': width,
				'max-height': height
			};
			responderCss = {
				// Use padding-bottom trick to maintain original aspect ratio
				'padding-bottom': ( 100 * height / width ) + '%'
			};
		}
		$container.addClass( 'mw-kartographer-responsive' ).css( containerCss );
		$responder = $( '<div>' ).addClass( 'mw-kartographer-responder' ).css( responderCss );

		$map = $( '<div>' );
		$container.append( $responder.append( $map ) );
		return $map[ 0 ];
	}

	/**
	 * This code will be executed once the article is rendered and ready.
	 *
	 * @ignore
	 */
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var mapsInArticle = [],
			isMobile = mw.config.get( 'skin' ) === 'minerva',
			promises = [];

		$content.find( '.mw-kartographer-interactive' ).each( function ( index ) {
			var MWMap, data,
				container = this,
				$container = $( this );

			$container.data( 'maptag-id', index );
			data = kartographer.getMapData( container );

			if ( data ) {
				data.enableFullScreenButton = true;

				if ( isMobile ) {
					container = responsiveContainerWrap( container );
				}

				MWMap = kartoLive.MWMap( container, data );
				MWMap.ready( function ( map, mapData ) {

					map.doubleClickZoom.disable();

					mapsInArticle.push( map );
					mw.kartographer.maps[ index ] = map;

					map.on( 'dblclick', function () {
						if ( router.isSupported() ) {
							router.navigate( kartographer.getMapHash( mapData, map ) );
						} else {
							kartographer.openFullscreenMap( map, kartographer.getMapPosition( map ) );
						}
					} );
				} );
				promises.push( MWMap.ready );
			}
		} );

		// Allow customizations of interactive maps in article.
		$.when( promises ).then( function () {
			mw.hook( 'wikipage.maps' ).fire( mapsInArticle, false /* isFullScreen */ );

			// Opens a map in full screen. #/map(/:zoom)(/:latitude)(/:longitude)
			// Examples:
			//     #/map/0
			//     #/map/0/5
			//     #/map/0/16/-122.4006/37.7873
			router.route( /map\/([0-9]+)(?:\/([0-9]+))?(?:\/([\-\+]?\d+\.?\d{0,5})?\/([\-\+]?\d+\.?\d{0,5})?)?/, function ( maptagId, zoom, latitude, longitude ) {
				var map = mw.kartographer.maps[ maptagId ];
				if ( !map ) {
					router.navigate( '' );
					return;
				}

				mw.kartographer.openFullscreenMap( map, kartographer.getFullScreenState( zoom, latitude, longitude ) );
			} );

			// Check if we need to open a map in full screen.
			router.checkRoute();
		} );
	} );
} )(
	jQuery,
	mediaWiki,
	require( 'ext.kartographer.init' ),
	require( 'ext.kartographer.live' ),
	require( 'mediawiki.router' )
);
