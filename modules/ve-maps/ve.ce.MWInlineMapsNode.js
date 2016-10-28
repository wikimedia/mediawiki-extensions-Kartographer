/*!
 * VisualEditor ContentEditable MWInlineMapsNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable paragraph node.
 *
 * @class
 * @extends ve.ce.MWInlineExtensionNode
 *
 * @constructor
 * @param {ve.dm.MWInlineMapsNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWInlineMapsNode = function VeCeMWInlineMaps() {
	// Parent constructor
	ve.ce.MWInlineMapsNode.super.apply( this, arguments );

	// Ensure we have the styles to render the map node
	mw.loader.using( 'ext.kartographer' );

	// DOM changes
	this.$element.addClass( 've-ce-mwInlineMapsNode' );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWInlineMapsNode, ve.ce.MWInlineExtensionNode );

/* Static Properties */

ve.ce.MWInlineMapsNode.static.name = 'mwInlineMaps';

ve.ce.MWInlineMapsNode.static.tagName = 'a';

ve.ce.MWInlineMapsNode.static.primaryCommandName = 'mwMaps';

ve.ce.MWInlineMapsNode.static.iconWhenInvisible = 'map';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWInlineMapsNode );
