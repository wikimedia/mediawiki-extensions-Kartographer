/* globals module */
/*jscs:disable disallowDanglingUnderscores */
/**
 * # Control to close the full screen dialog.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control/)
 * documentation for more details.
 *
 * @class Kartographer.Dialog.CloseFullScreenControl
 * @extends L.Control
 */
module.CloseFullScreenControl = ( function () {

	var ControlClass,
		createControl = function ( options ) {
		var control = this;

		// Since the control is added to an existing map, by the time we get here,
		// `ext.kartographer.box` was loaded, so this will be synchronous.
		// We need this hack because `L` is undefined until a map was created.
		mw.loader.using( 'ext.kartographer.box' ).then( function () {
			ControlClass = ControlClass || L.Control.extend( {
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
						var container = L.DomUtil.create( 'div', 'leaflet-bar' ),
							link = L.DomUtil.create( 'a', 'oo-ui-icon-close', container );

						link.href = '';
						link.title = mw.msg( 'kartographer-fullscreen-close' );

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
						this._map.closeFullScreen();
					}
				} );

			control = new ControlClass( options );
		} );
		return control;
	};

	return createControl;

} )( );
