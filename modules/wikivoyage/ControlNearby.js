/* globals module, require */
/**
 * Control to switch to displaying nearby articles.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-control/)
 * documentation for more details.
 *
 * @alias ControlNearby
 * @class Kartographer.Wikivoyage.ControlNearby
 * @extends L.Control
 * @private
 */
module.ControlNearby = ( function ( $, mw, L, wikivoyage, NearbyArticles, pruneClusterLib, undefined ) {

	var articlePath = mw.config.get( 'wgArticlePath' );

	function mousepopup( marker, data ) {
		marker.bindPopup( data.title, { minWidth: 140, maxWidth: 140, closeButton: false } );
		marker.on( 'click', function () {
			this.openPopup();
		} );
	}

	function createPopupHtml( wgPageName, thumbnail ) {
		var img = mw.html.element( 'img', {
				src: NearbyArticles.getConfig( 'thumbPath' ) + thumbnail + '/120px-' + thumbnail.substring( 5 )
			} ),
			link = mw.html.element( 'a', {
				href: mw.format( articlePath, wgPageName ),
				target: '_blank'
			}, wgPageName );
		return img + link;
	}

	function createMarker( latitude, longitude, wgArticle, thumbnail ) {
		return new pruneClusterLib.PruneCluster.Marker(
			latitude,
			longitude,
			{
				title: createPopupHtml( wgArticle, thumbnail )
			}
		);
	}

	/*jscs:disable disallowDanglingUnderscores, requireVarDeclFirst */
	return L.Control.extend( {
		options: {
			// Do not switch for RTL because zoom also stays in place
			position: 'topleft'
		},

		/**
		 * @override
		 */
		onAdd: function ( map ) {
			var container = L.DomUtil.create( 'div', 'leaflet-bar' ),
				link = L.DomUtil.create( 'a', 'mw-kartographer-icon-nearby', container ),
				pruneCluster = new pruneClusterLib.PruneClusterForLeaflet( 70 );

			link.href = '#';
			link.title = mw.msg( 'kartographer-wv-nearby-articles-control' );
			pruneCluster.options = {
				wvIsOverlay: true,
				wvIsExternal: true,
				wvName: 'nearby-articles'
			};

			this.map = map;
			this.link = link;
			this.pruneCluster = pruneCluster;

			L.DomEvent.addListener( link, 'click', this._onToggleNearbyLayer, this );
			L.DomEvent.disableClickPropagation( container );

			map.on( 'overlayadd', this._onOverlayAdd, this );
			map.on( 'overlayremove', this._onOverlayRemove, this );

			return container;
		},

		/**
		 * @protected
		 */
		_onOverlayAdd: function ( obj ) {
			var control = this,
				pruneCluster = this.pruneCluster;

			if ( pruneCluster !== obj.layer ) {
				return;
			}
			// Zoom out to get a better picture of the markers nearby.
			if ( this.map.getZoom() >= 12 ) {
				this.map.setZoom( 10 );
			}
			this._toggleActiveClass( true );
			if ( pruneCluster._objectsOnMap.length > 0 ) {
				return;
			}
			NearbyArticles.fetch().done( function ( addressPoints ) {
				var i = 0,
					total = addressPoints.length;

				for ( i; i < total; i++ ) {
					pruneCluster.RegisterMarker(
						createMarker.apply( null, addressPoints[ i ] )
					);
					pruneCluster.PrepareLeafletMarker = mousepopup;
				}
				pruneCluster.ProcessView();
			} ).fail( function () {
				control._toggleLayer( false );
			} );
		},

		/**
		 * @protected
		 */
		_onOverlayRemove: function ( obj ) {
			if ( this.pruneCluster !== obj.layer ) {
				return;
			}
			this._toggleActiveClass( false );
		},

		/**
		 * @protected
		 */
		_toggleActiveClass: function ( enabled ) {
			enabled = ( enabled !== undefined ) ? enabled : this.isEnabled();
			$( this.link ).toggleClass( 'mapbox-icon-nearby-active', enabled );
		},

		/**
		 * Checks whether the map has the layer.
		 */
		isEnabled: function () {
			return this.map.hasLayer( this.pruneCluster );
		},

		/**
		 * @protected
		 */
		_onToggleNearbyLayer: function ( e ) {
			L.DomEvent.stop( e );
			this._toggleLayer();
		},

		/**
		 * @protected
		 */
		_toggleLayer: function ( enabled ) {
			var control = this;

			enabled = ( enabled !== undefined ) ? enabled : this.isEnabled();

			if ( !enabled ) {
				wikivoyage.isAllowed( this.pruneCluster )
					.done( function () {
						control.map.addLayer( control.pruneCluster );
					} );
			} else {
				this.map[ enabled ? 'removeLayer' : 'addLayer' ]( this.pruneCluster );
			}
		}
	} );
} )(
	jQuery,
	mediaWiki,
	L,
	module.wikivoyage,
	module.NearbyArticles,
	require( 'ext.kartographer.lib.prunecluster' )
);
