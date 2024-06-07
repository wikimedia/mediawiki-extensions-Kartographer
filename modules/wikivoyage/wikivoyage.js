/**
 * Utility methods.
 *
 * @borrows Kartographer.Wikivoyage.wikivoyage as wikivoyage
 * @class Kartographer.Wikivoyage.wikivoyage
 * @singleton
 */
const tileLayerDefs = {};
let areExternalAllowed;
let windowManager;
let messageDialog;
const STORAGE_KEY = 'mwKartographerExternalSources';
const pathToKartographerImages = mw.config.get( 'wgExtensionAssetsPath' ) +
'/Kartographer/modules/wikivoyage/images/';

/**
 * @return {OO.ui.WindowManager}
 */
function getWindowManager() {
	if ( windowManager ) {
		return windowManager;
	}
	messageDialog = new OO.ui.MessageDialog();
	windowManager = new OO.ui.WindowManager();
	$( document.body ).append( windowManager.$element );
	windowManager.addWindows( [ messageDialog ] );
	return windowManager;
}

/**
 * @return {OO.ui.WindowInstance}
 */
function alertExternalData() {
	return getWindowManager().openWindow( messageDialog, {
		title: mw.msg( 'kartographer-wv-warning-external-source-title' ),
		message: mw.msg( 'kartographer-wv-warning-external-source-message' ),
		actions: [
			{ label: mw.msg( 'kartographer-wv-warning-external-source-disagree' ), action: 'bad' },
			{
				label: mw.msg( 'kartographer-wv-warning-external-source-agree' ),
				action: 'good'
			}
		]
	} );
}

module.exports = {
	/**
	 * Adds a tile layer definition to the internal store.
	 *
	 * @param {string} id A unique id to identify the layer.
	 * @param {string} url The tile url.
	 * @param {Object} options
	 * @param {Array} [options.attribs] A list of attributions.
	 * @chainable
	 */
	addTileLayer: function ( id, url, options ) {
		options.wvLayerId = id;
		options.attribution = options.attribution || '';
		( options.attribs || [] ).forEach( ( attrib ) => {
			options.attribution += mw.html.escape( attrib.label ) + ' ' +
			mw.html.element( 'a', { href: attrib.url }, attrib.name );
		} );

		tileLayerDefs[ id.toString() ] = {
			url: url,
			options: options
		};
		return this;
	},

	/**
	 * @param {string} id
	 * @return {{layer: L.TileLayer, name: string}}
	 */
	createTileLayer: function ( id ) {
		const layerDefs = tileLayerDefs[ id ];
		return {
			layer: new L.TileLayer( layerDefs.url, layerDefs.options ),
			name: this.formatLayerName( layerDefs.options.wvName, layerDefs.options )
		};
	},

	/**
	 * @param {string} name
	 * @param {Object} [options]
	 * @param {boolean} [options.wvIsExternal]
	 * @param {boolean} [options.wvIsWMF]
	 * @return {string} HTML
	 */
	formatLayerName: function ( name, options ) {
		let icon = '';
		options = options || {};
		if ( options.wvIsExternal ) {
			icon = new OO.ui.IconWidget( {
				icon: 'linkExternal',
				title: mw.msg( 'kartographer-wv-warning-external-source-message' ),
				classes: [ 'leaflet-control-layers-oo-ui-icon' ]
			} );
			icon = icon.$element[ 0 ].outerHTML;
		} else if ( options.wvIsWMF ) {
			icon = mw.html.element( 'img', {
				src: pathToKartographerImages + 'Wikimedia-logo.png',
				srcset: pathToKartographerImages + 'Wikimedia-logo@1.5x.png 1.5x, ' +
				pathToKartographerImages + 'Wikimedia-logo@2x.png 2x',
				class: 'leaflet-control-layers-wm-icon'
			} );
		}
		return mw.html.escape( name ) + '&nbsp;' + icon;
	},

	/**
	 * Checks if the layer is allowed.
	 *
	 * Some layers may load content hosted externally, enabling them shares
	 * the user's data with other sites. This method checks whether the
	 * layer is external and warns the user with a confirmation dialog.
	 * Once the user agrees, a setting with [mw.storage](https://doc.wikimedia.org/mediawiki-core/master/js/#!/api/mw.storage)
	 * so the user won't be prompted with a confirmation dialog anymore.
	 *
	 * @param {L.GeoJSON} layer
	 * @return {jQuery.Promise}
	 */
	isAllowed: function ( layer ) {
		return mw.loader.using( 'mediawiki.storage' ).then( () => {
			if ( areExternalAllowed === undefined ) {
				areExternalAllowed = mw.storage.get( STORAGE_KEY ) === '1';
			}

			if ( !layer.options.wvIsExternal || areExternalAllowed ) {
				return;
			}
			return alertExternalData().closed.then( ( data ) => {
				if ( data && data.action && data.action === 'good' ) {
					areExternalAllowed = true;
					mw.storage.set( STORAGE_KEY, '1' );
				} else {
					return $.Deferred().reject().promise();
				}
			} );
		} );
	}
};
