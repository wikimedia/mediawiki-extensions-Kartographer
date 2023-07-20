/**
 * # Control to close the full screen dialog.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-control/)
 * documentation for more details.
 *
 * @class Kartographer.Dialog.CloseFullScreenControl
 * @extends L.Control
 */
const CloseFullScreenControl = L.Control.extend( {
	options: {
		position: 'topright'
	},

	/**
	 * Creates the control element.
	 *
	 * @override
	 * @protected
	 */
	onAdd: function () {
		const container = L.DomUtil.create( 'div', 'leaflet-bar' );
		const link = L.DomUtil.create( 'a', 'oo-ui-icon-close', container );

		link.href = '#';
		link.title = mw.msg( 'kartographer-fullscreen-close' );
		link.role = 'button';
		link.tabIndex = '0';

		L.DomEvent.addListener( link, 'click', this.closeFullScreen, this );
		L.DomEvent.disableClickPropagation( container );

		return container;
	},

	/**
	 * Closes the full screen dialog on `click`.
	 *
	 * @param {Event} e
	 * @protected
	 */
	closeFullScreen: function ( e ) {
		L.DomEvent.stop( e );
		// eslint-disable-next-line no-underscore-dangle
		this._map.closeFullScreen();
	}
} );

module.exports = CloseFullScreenControl;
