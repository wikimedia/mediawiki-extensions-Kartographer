/* globals module */
/* eslint-disable no-underscore-dangle */
/**
 * Control to allow users to switch between different layers on the map.
 *
 * See [L.Control.Layers](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control-layers/)
 * documentation for more details.
 *
 * @alias ControlLayers
 * @class Kartographer.Wikivoyage.ControlLayers
 * @extends L.Control.Layers
 * @private
 */
module.ControlLayers = ( function ( $, mw, L, wikivoyage ) {

	return L.Control.Layers.extend( {

		/**
		 * @override
		 * @private
		 */
		_addItem: function ( obj ) {
			var label = L.Control.Layers.prototype._addItem.call( this, obj );
			if ( !obj.overlay && label.childNodes[ 0 ].checked ) {
				this._previousSelected = label.childNodes[ 0 ];
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
				/leaflet-control-layers-selector/.test( input.className )
			) {
				obj = this._layers[ input.layerId ];
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
} )(
	jQuery,
	mediaWiki,
	L,
	module.wikivoyage
);
