/* eslint-disable no-underscore-dangle */
/**
 * # Control to open the map in a full screen dialog.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control/)
 * documentation for more details.
 *
 * @class Kartographer.Box.OpenFullScreenControl
 * @extends L.Control
 */
module.OpenFullScreenControl = L.Control.extend( {
	options: {
		// Do not switch for RTL because zoom also stays in place
		position: 'topright'
	},

	/**
	 * Creates the control element.
	 *
	 * @override
	 * @protected
	 */
	onAdd: function () {
		var container = L.DomUtil.create( 'div', 'leaflet-bar leaflet-control-static' );

		this.link = L.DomUtil.create( 'a', 'oo-ui-icon-fullScreen', container );
		this.link.title = mw.msg( 'kartographer-fullscreen-text' );

		if ( this._map.useRouter ) {
			this.updateHash();
			this._map.on( 'moveend', this.onMapMove, this );
		} else {
			// the router will handle it otherwise
			L.DomEvent.addListener( this.link, 'click', this.openFullScreen, this );
		}
		L.DomEvent.disableClickPropagation( container );

		return container;
	},

	/**
	 * Updates the hash on `moveend`.
	 *
	 * @override
	 * @protected
	 */
	onMapMove: function () {
		if ( !this._map._loaded ) {
			return false;
		}
		this.updateHash();
	},

	/**
	 * Updates the link with the latest map hash.
	 */
	updateHash: function () {
		this.link.href = '#' + this._map.getHash();
	},

	/**
	 * Opens the full screen dialog on `click`.
	 *
	 * @param {Event} e
	 * @protected
	 */
	openFullScreen: function ( e ) {
		L.DomEvent.stop( e );
		this._map.openFullScreen();
	}
} );
