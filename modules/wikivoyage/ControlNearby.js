/**
 * Control to switch to displaying nearby articles.
 *
 * See [L.Control](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-control/)
 * documentation for more details.
 *
 * @alternateClassName ControlNearby
 * @class Kartographer.Wikivoyage.ControlNearby
 * @extends L.Control
 * @private
 */
const wikivoyage = require( './wikivoyage.js' );
const NearbyArticles = require( './NearbyArticles.js' );
const pruneClusterLib = require( 'ext.kartographer.lib.prunecluster' );
const PruneCluster = pruneClusterLib.PruneCluster;
const PruneClusterForLeaflet = pruneClusterLib.PruneClusterForLeaflet;
const articlePath = mw.config.get( 'wgArticlePath' );

/**
 * @param {L.Marker} marker
 * @param {Object} data
 */
function prepareMarker( marker, data ) {
	marker.setIcon( L.mapbox.marker.icon( {
		'marker-color': 'a2a9b1'
	} ) );
	marker.bindPopup( data.title, { closeButton: false } );
	marker.on( 'click', function () {
		this.openPopup();
	} );
}

/**
 * @param {string} wgPageName
 * @param {string} thumbnail
 * @return {string} HTML
 */
function createPopupHtml( wgPageName, thumbnail ) {
	const img = mw.html.element( 'img', {
			src: NearbyArticles.getConfig( 'thumbPath' ) + thumbnail + '/120px-' + thumbnail.slice( 5 )
		} ),
		link = mw.html.element( 'a', {
			href: mw.format( articlePath, wgPageName ),
			target: '_blank'
		}, wgPageName ),
		title = mw.html.element( 'div', {
			class: 'marker-title'
		}, new mw.html.Raw( link ) ),
		description = mw.html.element( 'div', {
			class: 'marker-description'
		}, new mw.html.Raw( img ) );
	return title + description;
}

/**
 * See https://tools.wmflabs.org/wikivoyage/w/data/en-articles.js for an example where this data
 * comes from.
 *
 * @param {number} latitude
 * @param {number} longitude
 * @param {string} wgArticle
 * @param {string} thumbnail
 * @return {PruneCluster.Marker}
 */
function createMarker( latitude, longitude, wgArticle, thumbnail ) {
	return new PruneCluster.Marker(
		latitude,
		longitude,
		{
			title: createPopupHtml( wgArticle, thumbnail )
		}
	);
}

/* eslint-disable no-underscore-dangle */
const ControlNearby = L.Control.extend( {
	options: {
		// Do not switch for RTL because zoom also stays in place
		position: 'topleft'
	},

	/**
	 * @override
	 */
	onAdd: function ( map ) {
		const container = L.DomUtil.create( 'div', 'leaflet-bar' ),
			link = L.DomUtil.create( 'a', 'mw-kartographer-icon-nearby', container ),
			pruneCluster = new PruneClusterForLeaflet( 70 );

		link.href = '#';
		link.title = mw.msg( 'kartographer-wv-nearby-articles-control' );
		pruneCluster.options = {
			wvIsOverlay: true,
			wvIsExternal: true,
			wvName: 'nearby-articles'
		};

		this.map = map;
		this.link = link;
		this.map._pruneCluster = this.pruneCluster = pruneCluster;

		L.DomEvent.addListener( link, 'click', this._onToggleNearbyLayer, this );
		L.DomEvent.disableClickPropagation( container );

		map.on( 'overlayadd', this._onOverlayAdd, this );
		map.on( 'overlayremove', this._onOverlayRemove, this );

		return container;
	},

	/**
	 * @protected
	 * @param {Object} obj
	 */
	_onOverlayAdd: function ( obj ) {
		const control = this,
			pruneCluster = this.pruneCluster;

		if ( pruneCluster !== obj.layer ) {
			return;
		}
		// Zoom out to get a better picture of the markers nearby.
		control._previousZoom = this.map.getZoom();
		if ( control._previousZoom >= 12 ) {
			this.map.setZoom( 10 );
		}
		this._toggleActiveClass( true );
		control._toggleDataLayers( false );
		if ( pruneCluster._objectsOnMap.length > 0 ) {
			return;
		}
		NearbyArticles.fetch().done( function ( addressPoints ) {
			for ( let i = 0; i < addressPoints.length; i++ ) {
				pruneCluster.RegisterMarker(
					createMarker.apply( null, addressPoints[ i ] )
				);
				pruneCluster.PrepareLeafletMarker = prepareMarker;
			}
			pruneCluster.ProcessView();
		} ).fail( function () {
			control._toggleLayer( false );
		} );
	},

	/**
	 * @protected
	 * @param {Object} obj
	 */
	_onOverlayRemove: function ( obj ) {
		if ( this.pruneCluster !== obj.layer ) {
			return;
		}
		this._toggleDataLayers( true );
		this.map.setZoom( this._previousZoom );
		this._toggleActiveClass( false );
	},

	/**
	 * @protected
	 * @param {boolean} [enabled]
	 */
	_toggleActiveClass: function ( enabled ) {
		enabled = ( enabled !== undefined ) ? enabled : this.isEnabled();
		$( this.link ).toggleClass( 'mapbox-icon-nearby-active', enabled );
	},

	/**
	 * Checks whether the map has the layer.
	 *
	 * @return {boolean}
	 */
	isEnabled: function () {
		return this.map.hasLayer( this.pruneCluster );
	},

	/**
	 * @protected
	 * @param {Event} e
	 */
	_onToggleNearbyLayer: function ( e ) {
		L.DomEvent.stop( e );
		this._toggleLayer();
	},

	/**
	 * @protected
	 * @param {boolean} [enabled]
	 */
	_toggleLayer: function ( enabled ) {
		const control = this;

		enabled = ( enabled !== undefined ) ? enabled : this.isEnabled();

		if ( !enabled ) {
			wikivoyage.isAllowed( this.pruneCluster )
				.done( function () {
					control.map.addLayer( control.pruneCluster );
				} );
		} else {
			this.map[ enabled ? 'removeLayer' : 'addLayer' ]( this.pruneCluster );
		}
	},

	/**
	 * @protected
	 * @param {boolean} [enabled]
	 */
	_toggleDataLayers: function ( enabled ) {
		const control = this;

		// Toggling this layer toggles data layers. We do not want to trigger
		// events like if the user manually toggled these layers. That's why this
		// boolean is temporarily set.
		control.map._preventTracking = true;

		control.map.dataLayers.forEach( function ( layer ) {
			control.map[ enabled ? 'addLayer' : 'removeLayer' ]( layer );
		} );

		control.map.$container.find( '.leaflet-control-layers-data-layer' ).each( function () {
			$( this ).prop( 'checked', enabled );
		} );

		control.map._preventTracking = false;
	}
} );

module.exports = ControlNearby;
