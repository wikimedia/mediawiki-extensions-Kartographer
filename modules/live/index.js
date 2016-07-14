/* globals module */
/**
 * Module containing elements required to display an interactive map on the
 * page.
 *
 * ```
 * mw.loader.using( 'ext.kartographer.live', function() {
 *
 *     var kartographer = mw.loader.require('ext.kartographer.init'),
 *         kartoLive = mw.loader.require('ext.kartographer.live'),
 *
 *         // Get map container
 *         mapContainer = document.getElementById('#map-selector'),
 *
 *         // Get initial configuration
 *         initialMapData = kartographer.getMapData( mapContainer ),
 *         kartoLiveMap;
 *
 *         // Start the "init" phase.
 *         kartoLiveMap = kartoLive.MWMap( mapContainer, initialMapData );
 *
 *         // Bind the "ready" hook
 *         kartoLiveMap.ready( function( map, mapData ) {
 *             console.log( 'The map was created successfully !' );
 *             console.log( '- Kartographer.Live "map" object: ', kartoLiveMap );
 *             console.log( '- Leaflet/Mapbox "map" object: ', map );
 *             console.log( '- "mapData" object: ', mapData );
 *         } );
 * } );
 * ```
 *
 * @alias Live
 * @alias ext.kartographer.live
 * @class Kartographer.Live
 * @singleton
 */
module.exports = {
	/**
	 * @type {Kartographer.Live.FullScreenControl}
	 */
	FullScreenControl: module.FullScreenControl,

	/**
	 * @type {Kartographer.Live.ControlScale}
	 */
	ControlScale: module.ControlScale,

	/**
	 * @type {Kartographer.Live.MWMap}
	 */
	MWMap: module.MWMap
};
