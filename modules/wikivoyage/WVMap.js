/* globals module */
/**
 * Wikivoyage Map class.
 *
 * @alias WVMap
 * @class Kartographer.Wikivoyage.WVMap
 * @private
 */
module.WVMap = ( function ( $, mw, wikivoyage, WVMapLayers, ControlNearby, undefined ) {

	/*jscs:disable disallowDanglingUnderscores, requireVarDeclFirst */
	var Map = function ( map ) {
		this.map = map;
	};

	/**
	 * Adds the nearby articles control to the map.
	 *
	 * @return {ControlNearby}
	 */
	Map.prototype.nearby = function () {
		var control = this._controlNearby;
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
	 * @return {WVMapLayers}
	 */
	Map.prototype.controlLayers = function () {
		this._controlLayers = this._controlLayers || new WVMapLayers( this.map );
		return this._controlLayers;
	};

	/**
	 * Adds the scale control to the map.
	 */
	Map.prototype.scale = function () {
		mw.log( 'Map scale is now added by default on all maps. Please remove the call to `.scale()`.' );
	};

	return Map;

} )(
	jQuery,
	mediaWiki,
	module.wikivoyage,
	module.WVMapLayers,
	module.ControlNearby
);
