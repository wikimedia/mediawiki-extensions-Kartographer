/*!
 * VisualEditor DataModel MWMapsNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel MW Maps node.
 *
 * @class
 * @extends ve.dm.MWBlockExtensionNode
 * @mixins ve.dm.ResizableNode
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

ve.dm.MWMapsNode.static.toDataElement = function () {
	var dataElement = ve.dm.MWMapsNode.super.static.toDataElement.apply( this, arguments );

	dataElement.attributes.width = +dataElement.attributes.mw.attrs.width;
	dataElement.attributes.height = +dataElement.attributes.mw.attrs.height;

	return dataElement;
};

ve.dm.MWMapsNode.static.getUrl = function ( dataElement, width, height ) {
	var mwAttrs = dataElement.attributes.mw.attrs,
		util = require( 'ext.kartographer.util' ),
		lang = mwAttrs.lang || util.getDefaultLanguage();

	return mw.config.get( 'wgKartographerMapServer' ) + '/img/' +
		mw.config.get( 'wgKartographerDfltStyle' ) + ',' +
		mwAttrs.zoom + ',' +
		mwAttrs.latitude + ',' +
		mwAttrs.longitude + ',' +
		( width || mwAttrs.width ) + 'x' +
		( height || mwAttrs.height ) +
		'.jpeg?' + $.param( { lang: lang } );
};

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
			width: 1000,
			height: 1000
		}
	} );
};

ve.dm.MWMapsNode.prototype.getCurrentDimensions = function () {
	return {
		width: +this.getAttribute( 'mw' ).attrs.width,
		height: +this.getAttribute( 'mw' ).attrs.height
	};
};

/* Methods */

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
	var containsDynamicFeatures = this.usesAutoPositioning() || this.usesExternalData();
	return !this.usesMapData() || !containsDynamicFeatures;
};

/**
 * Checks whether the map uses auto-positioning.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesAutoPositioning = function () {
	var mwAttrs = this.getAttribute( 'mw' ).attrs;
	return !( mwAttrs.latitude && mwAttrs.longitude && mwAttrs.zoom );
};

/**
 * Checks whether the map uses external data.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesExternalData = function () {
	var mwData = this.getAttribute( 'mw' ),
		geoJson = ( mwData.body && mwData.body.extsrc ) || '';
	return /ExternalData/.test( geoJson );
};

/**
 * Checks whether the map contains any map data.
 *
 * @return {boolean}
 */
ve.dm.MWMapsNode.prototype.usesMapData = function () {
	var mwData = this.getAttribute( 'mw' );
	return !!( mwData.body && mwData.body.extsrc );
};

/**
 * Gets the language used on this map.
 * @return {string} Language code
 */
ve.dm.MWMapsNode.prototype.getLanguage = function () {
	var mwAttrs = this.getAttribute( 'mw' ).attrs,
		util = require( 'ext.kartographer.util' );
	return mwAttrs.lang || util.getDefaultLanguage();
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWMapsNode );
