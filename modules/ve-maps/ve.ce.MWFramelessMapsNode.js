/*!
 * VisualEditor ContentEditable MWFramelessMapsNode class.
 *
 * @copyright See AUTHORS.txt
 */
/**
 * @class
 * @extends ve.ce.MWMapsNode
 *
 * @constructor
 * @param {ve.dm.MWFramelessMapsNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWFramelessMapsNode = function VeCeMWFramelessMaps() {
	// Parent constructor
	ve.ce.MWFramelessMapsNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWFramelessMapsNode, ve.ce.MWMapsNode );

/* Static Properties */

ve.ce.MWFramelessMapsNode.static.name = 'mwFramelessMaps';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWFramelessMapsNode );
