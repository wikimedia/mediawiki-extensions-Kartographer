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
	var store, contents, $caption;

	config = config || {};

	this.$map = $( '<div>' ).addClass( 'mw-kartographer-map' );

	// Parent constructor
	ve.ce.MWMapsNode.super.apply( this, arguments );

	// Mixin constructors
	ve.ce.ResizableNode.call( this, this.$map, config );

	this.$imageLoader = null;
	this.geoJson = null;
	this.mapData = {};

	this.updateMapPosition = $.debounce( 300, $.proxy( this.updateMapPosition, this ) );
	this.updateGeoJson = $.debounce( 300, $.proxy( this.updateGeoJson, this ) );

	// Events
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );
	this.connect( this, { focus: 'onMapFocus' } );

	// Ensure we have the styles to render the map node
	mw.loader.load( 'ext.kartographer' );

	// HACK: Copy caption from originalDomElements
	store = this.model.doc.getStore();
	contents = store.value( store.hashOfValue( null, OO.getHash( [ this.model.getHashObjectForRendering(), null ] ) ) );
	$caption = $( contents ).find( '.thumbcaption' ).clone();

	// DOM changes
	this.$element
		.empty()
		.addClass( 've-ce-mwMapsNode mw-kartographer-container thumb' )
		.append(
			$( '<div>' ).addClass( 'thumbinner' ).append(
				this.$map, $caption
			)
		);
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

	return ( mwData.body && mwData.body.extsrc ) || isNaN( mwData.attrs.latitude ) || isNaN( mwData.attrs.zoom );
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
			mw.loader.using( [
				'ext.kartographer.box',
				'ext.kartographer.editing'
			] ).then( this.setupMap.bind( this ) );
		} else if ( this.map ) {
			this.updateGeoJson();
			this.updateMapPosition();
			this.map.setLang( this.model.getLanguage() );
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
	switch ( align ) {
		case 'right':
			this.showHandles( [ 'sw' ] );
			break;
		case 'left':
			this.showHandles( [ 'se' ] );
			break;
		case 'center':
			this.showHandles( [ 'sw', 'se' ] );
			break;
	}
	this.$element
		.removeClass( 'floatleft center floatright' )
		.addClass( alignClasses[ align ] );
	this.$map
		.css( this.model.getCurrentDimensions() );
};

/**
 * Setup an interactive map
 */
ve.ce.MWMapsNode.prototype.setupMap = function () {
	var mwData = this.model.getAttribute( 'mw' ),
		mwAttrs = mwData && mwData.attrs,
		util = require( 'ext.kartographer.util' ),
		node = this;

	this.map = require( 'ext.kartographer.box' ).map( {
		container: this.$map[ 0 ],
		center: [ +mwAttrs.latitude, +mwAttrs.longitude ],
		zoom: +mwAttrs.zoom,
		lang: mwAttrs.lang || util.getDefaultLanguage()
		// TODO: Support style editing
	} );
	this.map.on( 'layeradd', this.updateMapPosition, this );
	this.map.doWhenReady( function () {
		node.updateGeoJson();

		// Disable interaction
		node.map.dragging.disable();
		node.map.touchZoom.disable();
		node.map.doubleClickZoom.disable();
		node.map.scrollWheelZoom.disable();
		node.map.keyboard.disable();
	} );
};

/**
 * Update the GeoJSON layer from the current model state
 */
ve.ce.MWMapsNode.prototype.updateGeoJson = function () {
	var mwData, geoJson;
	if ( !this.model ) {
		return;
	}
	mwData = this.model.getAttribute( 'mw' );
	geoJson = ve.getProp( mwData, 'body', 'extsrc' );

	if ( geoJson !== this.geoJson ) {
		require( 'ext.kartographer.editing' ).updateKartographerLayer( this.map, geoJson ).then( this.updateMapPosition.bind( this ) );
		this.geoJson = geoJson;
	}
};

/**
 * Updates the map position (center and zoom) from the current model state.
 */
ve.ce.MWMapsNode.prototype.updateMapPosition = function () {
	var mwData, mapData, updatedData, current;
	if ( !this.model ) {
		return;
	}
	mwData = this.model.getAttribute( 'mw' );
	mapData = this.mapData;
	updatedData = mwData && mwData.attrs;

	if ( !updatedData ) {
		// auto calculate the position
		this.map.setView( null, mapData.zoom );
		current = this.map.getMapPosition();
		// update missing attributes with current position.
		mwData.attrs.latitude = mapData.latitude = current.center.lat.toString();
		mwData.attrs.longitude = mapData.longitude = current.center.lng.toString();
		mwData.attrs.zoom = mapData.zoom = current.zoom.toString();
	} else if (
		isNaN( updatedData.latitude ) || isNaN( updatedData.longitude ) || isNaN( updatedData.zoom ) ||
		mapData.latitude !== updatedData.latitude ||
		mapData.longitude !== updatedData.longitude ||
		mapData.zoom !== updatedData.zoom
	) {
		this.map.setView( [ updatedData.latitude, updatedData.longitude ], updatedData.zoom );
		mapData.latitude = updatedData.latitude;
		mapData.longitude = updatedData.longitude;
		mapData.zoom = updatedData.zoom;
	} else {
		this.map.invalidateSize();
	}
};

/**
 * Update the static rendering
 *
 * @param {number} width Width
 * @param {number} height Height
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
		node.$map.css( 'backgroundImage', 'url(' + url + ')' );
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
	if ( !this.requiresInteractive() ) {
		// Preload larger static map for resizing
		$( '<img>' ).attr( 'src', this.model.getUrl( 1000, 1000 ) );
	}
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWMapsNode );
