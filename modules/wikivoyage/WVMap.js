/**
 * Wikivoyage Map class.
 *
 * @borrows Kartographer.Wikivoyage.WVMap as WVMap
 * @class Kartographer.Wikivoyage.WVMap
 */
const wikivoyage = require( './wikivoyage.js' );
const WVMapLayers = require( './WVMapLayers.js' );
const ControlNearby = require( './ControlNearby.js' );

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
 * Adds the nearby articles control to the map.
 *
 * @return {Kartographer.Wikivoyage.ControlNearby}
 */
WVMap.prototype.nearby = function () {
	if ( mw.config.get( 'wgKartographerWikivoyageNearby' ) === false ) {
		return;
	}

	let control = this._controlNearby;
	if ( control ) {
		return control;
	}

	control = this._controlNearby = new ControlNearby();
	control.addTo( this.map );

	this.controlLayers().addLayer(
		control.pruneCluster,
		wikivoyage.formatLayerName( mw.msg( 'kartographer-wv-layer-nearby-articles' ), control.pruneCluster.options ),
		true
	).update();

	return control;
};

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
