/* globals module */
/**
 * Close control on full screen mode.
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
