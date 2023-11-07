/**
 * Wikivoyage Map class.
 *
 * @borrows Kartographer.Wikivoyage.WVMap as WVMap
 * @class Kartographer.Wikivoyage.WVMap
 */
const WVMapLayers = require( './WVMapLayers.js' );

/* eslint-disable no-underscore-dangle */

/**
 * @constructor
 * @memberof Kartographer.Wikivoyage.WVMapLayers
 * @param {L.Map} map
 */
function WVMap( map ) {
	this.map = map;
}

/**
 * Adds the layer switcher control to the map.
 *
 * @return {Kartographer.Wikivoyage.WVMapLayers}
 */
WVMap.prototype.controlLayers = function () {
	this._controlLayers = this._controlLayers || new WVMapLayers( this.map );
	return this._controlLayers;
};

/**
 * Adds the scale control to the map.
 */
WVMap.prototype.scale = function () {
	mw.log( 'Map scale is now added by default on all maps. Please remove the call to `.scale()`.' );
};

module.exports = WVMap;
