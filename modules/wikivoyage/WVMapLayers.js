/**
 * Module to add {@link Kartographer.Wikivoyage.ControlLayers} control to the
 * map and add the tile layers and overlays.
 *
 * @alias WVMapLayers
 * @class Kartographer.Wikivoyage.WVMapLayers
 * @private
 */
module.WVMapLayers = ( function ( $, mw, wikivoyage, ControlLayers ) {

	/* eslint-disable no-underscore-dangle */
	var WVMapLayers = function ( map ) {
		this.map = map;
		this.control = new ControlLayers();
		this.control.addTo( this.map );

		// Add wikimedia basemap
		this.addLayer(
			this.map.wikimediaLayer,
			wikivoyage.formatLayerName( mw.msg( 'kartographer-wv-layer-wikimedia' ), { wvIsWMF: true } )
		);
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Adds a layer.
	 *
	 * @param {string} layer A unique id for the layer.
	 * @param {string} name A label for the layer
	 * @param {boolean} overlay Whether it is a base layer or an overlay.
	 * @chainable
	 */
	WVMapLayers.prototype.addLayer = function ( layer, name, overlay ) {
		this.control._addLayer( layer, name, overlay );
		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Refreshes the list of layers displayed in the control dropdown.
	 *
	 * @chainable
	 */
	WVMapLayers.prototype.update = function () {
		this.control._update();
		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Adds a base map.
	 *
	 * @param {string} id The layer id.
	 * @chainable
	 */
	WVMapLayers.prototype.basemap = function ( id ) {
		var tileLayer = wikivoyage.createTileLayer( id );
		this.addLayer( tileLayer.layer, tileLayer.name );
		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Adds an overlay.
	 *
	 * @param {string} id The layer id.
	 * @chainable
	 */
	WVMapLayers.prototype.overlay = function ( id ) {
		var tileLayer = wikivoyage.createTileLayer( id );
		this.addLayer( tileLayer.layer, tileLayer.name, true );
		return this;
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Adds a data layer.
	 *
	 * @param {string} id The layer id.
	 * @param {L.GeoJSON} layer The data layer.
	 * @chainable
	 */
	WVMapLayers.prototype.datalayer = function ( id, layer ) {
		var self = this;
		if ( typeof id === 'object' ) {
			$.each( id, function ( group, groupLayer ) {
				self.datalayer( group, groupLayer );
			} );
			return this;
		}

		this.addLayer(
			layer,
			layer.options.name || wikivoyage.formatLayerName( mw.msg( 'kartographer-wv-group' ) + ' ' + id ),
			true
		);
		return this;
	};

	return WVMapLayers;
}(
	jQuery,
	mediaWiki,
	module.wikivoyage,
	module.ControlLayers
) );
