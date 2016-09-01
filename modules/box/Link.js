/* globals module */
/**
 * # Kartographer Link class
 *
 * Binds a `click` event to a `container`, that creates
 * {@link Kartographer.Box.MapClass a map with layers, markers, and
 * interactivity}, and opens it in a full screen dialog.
 *
 * @alias KartographerLink
 * @class Kartographer.Box.LinkClass
 */
module.Link = ( function ( $ ) {

	var Link;

	/*jscs:disable disallowDanglingUnderscores */
	/**
	 * @constructor
	 * @param {Object} options **Configuration and options:**
	 * @param {HTMLElement} options.container **Link container.**
	 * @param {string[]} [options.dataGroups] **List of known data groups,
	 *   fetchable from the server, to add as overlays onto the map.**
	 * @param {Object|Array} [options.data] **Inline GeoJSON features to
	 *   add to the map.**
	 * @param {Array|L.LatLng} [options.center] **Initial map center.**
	 * @param {number} [options.zoom] **Initial map zoom.**
	 * @param {string} [options.fullScreenRoute] Route associated to this map
	 *   _(internal, used by "`<maplink>`")_.
	 * @member Kartographer.Box.LinkClass
	 */
	Link = function ( options ) {
		/**
		 * Reference to the link container.
		 *
		 * @type {HTMLElement}
		 */
		this.container = options.container;

		/**
		 * Reference to the map container as a jQuery element.
		 *
		 * @type {jQuery}
		 */
		this.$container = $( this.container );
		this.$container.addClass( 'mw-kartographer-link' );

		this.center = options.center || 'auto';
		this.zoom = options.zoom || 'auto';

		this.opened = false;

		this.useRouter = !!options.fullScreenRoute;
		this.fullScreenRoute = options.fullScreenRoute || null;
		this.dataGroups = options.dataGroups;
		this.data = options.data;
		/**
		 * @property {Kartographer.Box.MapClass} [fullScreenMap=null] Reference
		 *   to the associated full screen map.
		 * @protected
		 */
		this.fullScreenMap = null;

		if ( this.useRouter && this.container.tagName === 'A' ) {
			this.container.href = '#' + this.fullScreenRoute;
		} else {
			this.$container.on( 'click.kartographer', L.Util.bind( function () {
				this.openFullScreen();
			}, this ) );
		}
	};

	/**
	 * Opens the map associated to the link in a full screen dialog.
	 *
	 * **Uses Resource Loader module: {@link Kartographer.Dialog ext.kartographer.dialog}**
	 *
	 * @param {Object} [position] Map `center` and `zoom`.
	 * @member Kartographer.Box.LinkClass
	 */
	Link.prototype.openFullScreen = function ( position ) {

		var map = this.map;

		position = position || {};
		position.center = position.center || this.center;
		position.zoom = typeof position.zoom === 'number' ? position.zoom : this.zoom;

		if ( this.fullScreenMap && this.fullScreenMap._container._leaflet ) {
			map = this.fullScreenMap;

			map.setView(
				position.center,
				position.zoom
			);
		} else {
			map = this.fullScreenMap = L.kartographer.map( {
				container: L.DomUtil.create( 'div', 'mw-kartographer-mapDialog-map' ),
				fullscreen: true,
				link: true,
				center: position.center,
				zoom: position.zoom,
				dataGroups: this.dataGroups,
				data: this.data,
				fullScreenRoute: this.fullScreenRoute
			} );
		}

		mw.loader.using( 'ext.kartographer.dialog' ).done( function () {
			mw.loader.require( 'ext.kartographer.dialog' ).render( map );
		} );
	};

	return Link;
} )( jQuery );
