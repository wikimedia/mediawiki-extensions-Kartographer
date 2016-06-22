/* globals module, require */
module.FullScreenControl = ( function ( kartographer, router ) {

	return L.Control.extend( {
		options: {
			// Do not switch for RTL because zoom also stays in place
			position: 'topright'
		},

		onAdd: function ( map ) {
			var container = L.DomUtil.create( 'div', 'leaflet-bar' );

			this.link = L.DomUtil.create( 'a', 'oo-ui-icon-fullScreen', container );
			this.link.title = mw.msg( 'kartographer-fullscreen-text' );
			this.map = map;

			this.map.on( 'moveend', this.onMapMove, this );
			if ( !router.isSupported() ) {
				L.DomEvent.addListener( this.link, 'click', this.onShowFullScreen, this );
			}
			L.DomEvent.disableClickPropagation( container );
			this.updateHash();

			return container;
		},

		onMapMove: function () {
			/*jscs:disable disallowDanglingUnderscores */
			if ( !this.map._loaded ) {
				return false;
			}
			/*jscs:enable disallowDanglingUnderscores */
			this.updateHash();
		},

		updateHash: function () {
			var hash = mw.kartographer.getMapHash( this.options.mapData, this.map );
			this.link.href = '#' + hash;
		},

		onShowFullScreen: function ( e ) {
			L.DomEvent.stop( e );
			mw.kartographer.openFullscreenMap( this.map, kartographer.getMapPosition( this.map ) );
		}
	} );
} )( require( 'ext.kartographer.init' ), require( 'mediawiki.router' ) );
