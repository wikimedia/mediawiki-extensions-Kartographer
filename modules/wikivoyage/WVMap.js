/* globals module */
/**
 * Wikivoyage Map class.
 *
 * @alias WVMap
 * @class Kartographer.Wikivoyage.WVMap
 * @private
 */
module.WVMap = ( function ( $, mw, wikivoyage, WVMapLayers, ControlNearby, ControlScale, undefined ) {

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
	 *
	 * @return {ControlScale}
	 */
	Map.prototype.scale = function () {
		this._controlScale = this._controlScale || new ControlScale( { position: 'bottomright' } ).addTo( this.map );
		return this._controlScale;
	};

	return Map;

} )(
	jQuery,
	mediaWiki,
	module.wikivoyage,
	module.WVMapLayers,
	module.ControlNearby,
	module.ControlScale
);
