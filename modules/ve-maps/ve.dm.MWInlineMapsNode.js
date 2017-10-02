/*!
 * VisualEditor DataModel MWInlineMapsNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel MW Maps node.
 *
 * @class
 * @extends ve.dm.MWInlineExtensionNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWInlineMapsNode = function VeDmMWInlineMaps() {
	// Parent constructor
	ve.dm.MWInlineMapsNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWInlineMapsNode, ve.dm.MWInlineExtensionNode );

/* Static Properties */

ve.dm.MWInlineMapsNode.static.name = 'mwInlineMaps';

ve.dm.MWInlineMapsNode.static.extensionName = 'maplink';

/* Methods */

/**
 * Don't allow maps to be edited if they contain features that are not
 * supported not supported by the editor.
 *
 * @inheritdoc
 */
ve.dm.MWInlineMapsNode.prototype.isEditable = function () {
	var containsDynamicFeatures = this.usesAutoPositioning() || this.usesExternalData();
	return !this.usesMapData() || !containsDynamicFeatures;
};

/**
 * Checks whether the map uses auto-positioning.
 *
 * @return {boolean}
 */
ve.dm.MWInlineMapsNode.prototype.usesAutoPositioning = function () {
	var mwAttrs = this.getAttribute( 'mw' ).attrs;
	return !( mwAttrs.latitude && mwAttrs.longitude && mwAttrs.zoom );
};

/**
 * Checks whether the map uses external data.
 *
 * @return {boolean}
 */
ve.dm.MWInlineMapsNode.prototype.usesExternalData = function () {
	var mwData = this.getAttribute( 'mw' ),
		geoJson = ( mwData.body && mwData.body.extsrc ) || '';
	return /ExternalData/.test( geoJson );
};

/**
 * Checks whether the map contains any map data.
 *
 * @return {boolean}
 */
ve.dm.MWInlineMapsNode.prototype.usesMapData = function () {
	var mwData = this.getAttribute( 'mw' );
	return !!( mwData.body && mwData.body.extsrc );
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWInlineMapsNode );
