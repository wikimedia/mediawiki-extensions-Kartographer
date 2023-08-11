/* eslint-disable no-underscore-dangle */
/**
 * Control to allow users to switch between different layers on the map.
 *
 * See [L.Control.Layers](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-control-layers/)
 * documentation for more details.
 *
 * @alternateClassName ControlLayers
 * @class Kartographer.Wikivoyage.ControlLayers
 * @extends L.Control.Layers
 * @private
 */
var wikivoyage = require( './wikivoyage.js' ),
	ControlLayers;

/**
 * @memberof Kartographer.Wikivoyage.ControlLayers
 */
ControlLayers = L.Control.Layers.extend( {

	/**
	 * @override
	 */
	onAdd: function ( map ) {
		var container = L.Control.Layers.prototype.onAdd.call( this, map );
		container.className += ' leaflet-bar';
		return container;
	},

	/**
	 * @override
	 * @private
	 */
	_addItem: function ( obj ) {
		var label = L.Control.Layers.prototype._addItem.call( this, obj );
		if ( !obj.overlay && label.childNodes[ 0 ].childNodes[ 0 ].checked ) {
			this._previousSelected = label.childNodes[ 0 ].childNodes[ 0 ];
		}
		if ( obj.layer.isDataGroup ) {
			label.childNodes[ 0 ].className += ' leaflet-control-layers-data-layer';
		}
	},

	/**
	 * @override
	 * @private
	 */
	_onInputClick: function ( event ) {
		var self = this,
			proto = L.Control.Layers.prototype._onInputClick,
			input = event && event.target,
			obj;

		if ( input &&
			event.type === 'click' &&
			input.className.indexOf( 'leaflet-control-layers-selector' ) !== -1
		) {
			obj = this._getLayer( input.layerId );
			if ( this._map.hasLayer( obj.layer ) ) {
				proto.call( self );
			} else {
				event.stopPropagation();
				if ( !obj.overlay && this._previousSelected ) {
					this._previousSelected.checked = true;
				}
				input.checked = false;
				this._expand();
				wikivoyage.isAllowed( obj.layer )
					.done( function () {
						input.checked = true;
						proto.call( self );
					} );
			}
		} else {
			proto.call( this );
		}
	}
} );

module.exports = ControlLayers;
