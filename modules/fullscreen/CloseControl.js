/* globals module */
/**
 * Control to close the full screen dialog.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control/)
 * documentation for more details.
 *
 * @alias FullScreenCloseControl
 * @class Kartographer.Fullscreen.CloseControl
 * @extends L.Control
 */
module.FullScreenCloseControl = L.Control.extend( {
	options: {
		position: 'topright'
	},

	onAdd: function () {
		var container = L.DomUtil.create( 'div', 'leaflet-bar' ),
			link = L.DomUtil.create( 'a', 'oo-ui-icon-close', container );

		this.href = '#';
		link.title = mw.msg( 'kartographer-fullscreen-close' );

		L.DomEvent.addListener( link, 'click', this.onClick, this );
		L.DomEvent.disableClickPropagation( container );

		return container;
	},

	onClick: function ( e ) {
		L.DomEvent.stop( e );

		this.options.dialog.executeAction( '' );
	}
} );
