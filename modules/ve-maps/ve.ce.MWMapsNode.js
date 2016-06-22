/*!
 * VisualEditor ContentEditable MWMapsNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */
/* globals require */
var kartoLive = require( 'ext.kartographer.live' ),
	kartoEditing = require( 'ext.kartographer.editing' );

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
	this.geoJson = null;

	this.updateGeoJson = $.debounce( 300, $.proxy( this.updateGeoJson, this ) );

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
 * A map requires interactive rendering
 *
 * Maps without GeoJSON can be rendered as static
 *
 * @return {boolean} Maps requires interactive rendering
 */
ve.ce.MWMapsNode.prototype.requiresInteractive = function () {
	var mwData = this.model.getAttribute( 'mw' );

	return mwData.body.extsrc;
};

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
};

/**
 * @inheritdoc
 */
ve.ce.MWMapsNode.prototype.onSetup = function () {
	ve.ce.MWMapsNode.super.prototype.onSetup.call( this );

	this.update();
};

/**
 * Update the map rendering
 */
ve.ce.MWMapsNode.prototype.update = function () {
	var requiresInteractive = this.requiresInteractive(),
		align = ve.getProp( this.model.getAttribute( 'mw' ), 'attrs', 'align' ) ||
			( this.model.doc.getDir() === 'ltr' ? 'right' : 'left' ),
		alignClasses = {
			left: 'floatleft',
			center: 'center',
			right: 'floatright'
		};

	if ( requiresInteractive ) {
		if ( !this.map && this.getRoot() ) {
			mw.loader.using( 'ext.kartographer.live' ).then( this.setupMap.bind( this ) );
		} else if ( this.map ) {
			this.map.invalidateSize();
			this.updateGeoJson();
		}
	} else {
		if ( this.map ) {
			// Node was previously interactive
			this.map.remove();
			this.map = null;
		}
		this.updateStatic();
		$( '<img>' ).attr( 'src', this.model.getUrl( 1000, 1000 ) );
	}
	this.$element
		.removeClass( 'floatleft center floatright' )
		.addClass( alignClasses[ align ] );
};

/**
 * Setup an interactive map
 */
ve.ce.MWMapsNode.prototype.setupMap = function () {
	var mwData = this.model.getAttribute( 'mw' ),
		mwAttrs = mwData && mwData.attrs,
		latitude = +mwAttrs.latitude,
		longitude = +mwAttrs.longitude,
		zoom = +mwAttrs.zoom;

	this.MWMap = kartoLive.MWMap( this.$element[ 0 ], {
		latitude: latitude,
		longitude: longitude,
		zoom: zoom
		// TODO: Support style editing
	} );
	this.map = this.MWMap.map;

	this.updateGeoJson();

	// Disable interaction
	this.map.dragging.disable();
	this.map.touchZoom.disable();
	this.map.doubleClickZoom.disable();
	this.map.scrollWheelZoom.disable();
	this.map.keyboard.disable();
};

/**
 * Update the GeoJSON layer from the current model state
 */
ve.ce.MWMapsNode.prototype.updateGeoJson = function () {
	var mwData = this.model.getAttribute( 'mw' ),
		geoJson = mwData && mwData.body.extsrc;

	if ( geoJson !== this.geoJson ) {
		kartoEditing.updateKartographerLayer( this.map, mwData && mwData.body.extsrc );
		this.geoJson = geoJson;
	}
};

/**
 * Update the static rendering
 */
ve.ce.MWMapsNode.prototype.updateStatic = function ( width, height ) {
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

	if ( !this.requiresInteractive() ) {
		this.updateStatic( 1000, 1000 );
	} else if ( this.map ) {
		this.map.invalidateSize();
	}
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
