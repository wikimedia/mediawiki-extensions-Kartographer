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
	var mwAttrs = dataElement.attributes.mw.attrs;

	return 'https://maps.wikimedia.org/img/osm-intl,' +
		mwAttrs.zoom + ',' +
		mwAttrs.latitude + ',' +
		mwAttrs.longitude + ',' +
		( width || mwAttrs.width ) + 'x' +
		( height || mwAttrs.height ) +
		'.jpeg';
};

ve.dm.MWMapsNode.static.createScalable = function ( dimensions ) {
	return new ve.dm.Scalable( {
		fixedRatio: false,
		currentDimensions: {
			width: dimensions.width,
			height: dimensions.height
		},
		minDimensions: {
			width: 10,
			height: 10
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

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWMapsNode );
