/*!
 * VisualEditor ContentEditable MWMapsNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * ContentEditable paragraph node.
 *
 * @class
 * @extends ve.ce.MWBlockExtensionNode
 * @mixins ve.ce.ResizableNode
 *
 * @constructor
 * @param {ve.dm.MWMapsNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWMapsNode = function VeCeMWMaps( model, config ) {
	config = config || {};

	// Parent constructor
	ve.ce.MWMapsNode.super.apply( this, arguments );

	// Mixin constructors
	ve.ce.ResizableNode.call( this, this.$element, config );

	this.$imageLoader = null;

	// Events
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );
	this.connect( this, { focus: 'onMapFocus' } );

	// Ensure we have the styles to render the map node
	mw.loader.using( 'ext.kartographer' );

	// DOM changes
	this.$element
		.empty()
		.addClass( 've-ce-mwMapsNode' )
		.css( this.model.getCurrentDimensions() );
	this.update();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWMapsNode, ve.ce.MWBlockExtensionNode );

OO.mixinClass( ve.ce.MWMapsNode, ve.ce.ResizableNode );

/* Static Properties */

ve.ce.MWMapsNode.static.name = 'mwMaps';

ve.ce.MWMapsNode.static.tagName = 'div';

ve.ce.MWMapsNode.static.primaryCommandName = 'mwMaps';

/* Methods */

/**
 * Update the rendering of the 'align', src', 'width' and 'height' attributes
 * when they change in the model.
 *
 * @method
 * @param {string} key Attribute key
 * @param {string} from Old value
 * @param {string} to New value
 */
ve.ce.MWMapsNode.prototype.onAttributeChange = function () {
	this.update();
	$( '<img>' ).attr( 'src', this.model.getUrl( 1000, 1000 ) );
};

/**
 * Update the static rendering
 */
ve.ce.MWMapsNode.prototype.update = function ( width, height ) {
	var url, node = this;

	if ( !this.model.getCurrentDimensions().width ) {
		return;
	}

	if ( this.$imageLoader ) {
		this.$imageLoader.off();
		this.$imageLoader = null;
	}

	url = this.model.getUrl( width, height );

	this.$imageLoader = this.$( '<img>' ).on( 'load', function () {
		node.$element.css( 'backgroundImage', 'url(' + url + ')' );
	} ).attr( 'src', url );
};

/**
 * @inheritdoc ve.ce.ResizableNode
 */
ve.ce.MWMapsNode.prototype.onResizableResizing = function () {
	// Mixin method
	ve.ce.ResizableNode.prototype.onResizableResizing.apply( this, arguments );

	this.update( 1000, 1000 );
};

/**
 * @inheritdoc ve.ce.ResizableNode
 */
ve.ce.MWMapsNode.prototype.getAttributeChanges = function ( width, height ) {
	var mwData = ve.copy( this.model.getAttribute( 'mw' ) );

	mwData.attrs.width = width.toString();
	mwData.attrs.height = height.toString();

	return { mw: mwData };
};

/**
 * Handle focus events
 */
ve.ce.MWMapsNode.prototype.onMapFocus = function () {
	$( '<img>' ).attr( 'src', this.model.getUrl( 1000, 1000 ) );
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWMapsNode );
