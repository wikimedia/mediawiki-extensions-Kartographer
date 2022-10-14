/**
 * DataModel node for a frameless <mapframe> extension tag.
 *
 * @class
 * @extends ve.dm.MWMapsNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWFramelessMapsNode = function VeDmMWFramelessMaps() {
	// Parent constructor
	ve.dm.MWFramelessMapsNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWFramelessMapsNode, ve.dm.MWMapsNode );

/* Static Properties */

ve.dm.MWFramelessMapsNode.static.name = 'mwFramelessMaps';

ve.dm.MWFramelessMapsNode.static.isContent = true;

ve.dm.MWFramelessMapsNode.static.matchTagNames = [ 'a' ];

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWFramelessMapsNode );
