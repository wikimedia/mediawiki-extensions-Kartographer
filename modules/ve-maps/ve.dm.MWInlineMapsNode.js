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

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWInlineMapsNode );
