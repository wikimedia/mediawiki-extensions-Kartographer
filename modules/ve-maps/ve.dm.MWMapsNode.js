/*!
 * VisualEditor DataModel MWMapsNode class.
 *
 * @copyright See AUTHORS.txt
 */

/**
 * DataModel node for a <mapframe> extension tag.
 *
 * @class
 * @extends ve.dm.MWBlockExtensionNode
 * @mixes ve.dm.ResizableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWMapsNode = function VeDmMWMaps() {
	// Parent constructor
	ve.dm.MWMapsNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.ResizableNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWMapsNode, ve.dm.MWBlockExtensionNode );

OO.mixinClass( ve.dm.MWMapsNode, ve.dm.ResizableNode );

/* Static Properties */

ve.dm.MWMapsNode.static.name = 'mwMaps';

ve.dm.MWMapsNode.static.extensionName = 'mapframe';

ve.dm.MWMapsNode.static.matchTagNames = [ 'div' ];

/* Static methods */

/**
 * @return {Object}
 */
ve.dm.MWMapsNode.static.toDataElement = function () {
	const dataElement = ve.dm.MWMapsNode.super.static.toDataElement.apply( this, arguments );

	dataElement.attributes.width = +dataElement.attributes.mw.attrs.width;
	dataElement.attributes.height = +dataElement.attributes.mw.attrs.height;

	return dataElement;
};

/**
 * @param {Object} dataElement
 * @param {number} [width]
 * @param {number} [height]
 * @return {string}
 */
ve.dm.MWMapsNode.static.getUrl = function ( dataElement, width, height ) {
	const mwAttrs = dataElement.attributes.mw.attrs;
	const util = require( 'ext.kartographer.util' );
	const lang = mwAttrs.lang || util.getDefaultLanguage();

	width = width || mwAttrs.width;
	if ( width === 'full' || width === '100%' ) {
		width = mw.config.get( 'wgKartographerStaticFullWidth' );
	} else if ( !isFinite( width ) ) {
		// This fallback for deprecated percentages other than 100% is hard-coded in the backend
		width = 300;
	}

	return mw.config.get( 'wgKartographerMapServer' ) + '/img/' +
		mw.config.get( 'wgKartographerDfltStyle' ) + ',' +
		mwAttrs.zoom + ',' +
		mwAttrs.latitude + ',' +
		mwAttrs.longitude + ',' +
		width + 'x' +
		( height || mwAttrs.height ) +
		'.jpeg?' + $.param( { lang: lang } );
};

/**
 * @param {{width: number, height: number}} dimensions
 * @return {ve.dm.Scalable}
 */
ve.dm.MWMapsNode.static.createScalable = function ( dimensions ) {
	return new ve.dm.Scalable( {
		fixedRatio: false,
		currentDimensions: {
			width: dimensions.width,
			height: dimensions.height
		},
		minDimensions: {
			width: 200,
			height: 100
		},
		maxDimensions: {
			width: 2000,
			height: 1000
		}
	} );
};

/**
 * @return {{width: number, height: number}}
 */
ve.dm.MWMapsNode.prototype.getCurrentDimensions = function () {
	const mwAttrs = this.getAttribute( 'mw' ).attrs;
	let width = mwAttrs.width;
	if ( width === 'full' || width === '100%' ) {
		width = mw.config.get( 'wgKartographerStaticFullWidth' );
	} else if ( !isFinite( width ) ) {
		// This fallback for deprecated percentages other than 100% is hard-coded in the backend
		width = 300;
	}
	return {
		width: +width,
		height: +mwAttrs.height
	};
};

/* Methods */

/**
 * @param {number} [width]
 * @param {number} [height]
 * @return {string}
 */
ve.dm.MWMapsNode.prototype.getUrl = function ( width, height ) {
	return this.constructor.static.getUrl( this.element, width, height );
};

/**
 * @inheritdoc
 */
ve.dm.MWMapsNode.prototype.createScalable = function () {
	return this.constructor.static.createScalable( this.getCurrentDimensions() );
};

/**
 * Don't allow maps to be edited if they contain features that are not
 * supported not supported by the editor.
 *
 * @inheritdoc
 */
ve.dm.MWMapsNode.prototype.isEditable = function () {
	const containsDynamicFeatures = this.usesAutoPositioning() || this.usesExternalData();
	return !this.usesMapData() || !containsDynamicFeatures;
};

/**
 * Checks whether the map uses auto-positioning.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesAutoPositioning = function () {
	const mwAttrs = this.getAttribute( 'mw' ).attrs;
	return !( mwAttrs.latitude && mwAttrs.longitude && mwAttrs.zoom );
};

/**
 * Checks whether the map uses external data.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesExternalData = function () {
	const mwData = this.getAttribute( 'mw' );
	const geoJson = ( mwData.body && mwData.body.extsrc ) || '';
	return geoJson.indexOf( 'ExternalData' ) !== -1;
};

/**
 * Checks whether the map contains any map data.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesMapData = function () {
	const mwData = this.getAttribute( 'mw' );
	return !!( mwData.body && mwData.body.extsrc );
};

/**
 * Gets the language used on this map.
 *
 * @return {string} Language code
 */
ve.dm.MWMapsNode.prototype.getLanguage = function () {
	const mwAttrs = this.getAttribute( 'mw' ).attrs;
	const util = require( 'ext.kartographer.util' );
	return mwAttrs.lang || util.getDefaultLanguage();
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWMapsNode );
