/**
 * # Kartographer Link class
 *
 * Binds a `click` event to a `container`, that opens a {@link Kartographer.Box.MapClass map with
 * layers, markers, and interactivity} in a full screen dialog.
 *
 * @class Kartographer.Linkbox.LinkClass
 */

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
 * @param {string} [options.lang] Language code
 * @param {string} [options.fullScreenRoute] Route associated to this map
 *   _(internal, used by "`<maplink>`")_.
 * @memberof Kartographer.Linkbox.LinkClass
 * @type {Kartographer.Linkbox.LinkClass}
 * @method
 */
function Link( options ) {
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
	this.lang = options.lang || require( 'ext.kartographer.util' ).getDefaultLanguage();

	this.opened = false;

	this.useRouter = !!options.fullScreenRoute;
	this.fullScreenRoute = options.fullScreenRoute || null;
	this.captionText = options.captionText || '';
	this.dataGroups = options.dataGroups;
	this.data = options.data;
	this.featureType = options.featureType;

	/**
	 * @property {Kartographer.Box.MapClass} [fullScreenMap=null] Reference
	 *   to the associated full screen map.
	 * @protected
	 */
	this.fullScreenMap = null;

	if ( this.useRouter && this.container.tagName === 'A' ) {
		this.container.href = '#' + this.fullScreenRoute;
	} else {
		this.$container.on( 'click.kartographer', () => {
			this.openFullScreen();
		} );
	}
}

/**
 * Opens the map associated to the link in a full screen dialog.
 *
 * **Uses Resource Loader module: {@link Kartographer.Dialog ext.kartographer.dialog}**
 *
 * @param {Object} [position] Map `center` and `zoom`.
 * @memberof Kartographer.Linkbox.LinkClass
 */
Link.prototype.openFullScreen = function ( position ) {
	const map = this.fullScreenMap;

	position = position || {};
	position.center = position.center || this.center;
	position.zoom = typeof position.zoom === 'number' ? position.zoom : this.zoom;

	/* eslint-disable no-underscore-dangle */
	if ( map && map._container._leaflet_id ) {
		map.setView(
			position.center,
			position.zoom
		);

		mw.loader.using( 'ext.kartographer.dialog' ).then( () => {
			require( 'ext.kartographer.dialog' ).render( map );
		} );
	/* eslint-enable no-underscore-dangle */
	} else {
		const el = document.createElement( 'div' );
		el.className = 'mw-kartographer-mapDialog-map';
		const mapObject = {
			container: el,
			featureType: this.featureType,
			fullscreen: true,
			link: true,
			parentLink: this,
			center: position.center,
			zoom: position.zoom,
			lang: this.lang,
			captionText: this.captionText,
			dataGroups: this.dataGroups,
			data: this.data,
			fullScreenRoute: this.fullScreenRoute
		};

		mw.loader.using( 'ext.kartographer.dialog' ).then( () => {
			require( 'ext.kartographer.dialog' ).renderNewMap( mapObject ).then( ( m ) => {
				this.fullScreenMap = m;
			} );
		} );
	}
};

module.exports = Link;
