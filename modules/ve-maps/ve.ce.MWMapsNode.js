/*!
 * VisualEditor ContentEditable MWMapsNode class.
 *
 * @copyright See AUTHORS.txt
 */
/**
 * ContentEditable paragraph node.
 *
 * @class
 * @extends ve.ce.MWBlockExtensionNode
 * @mixes ve.ce.ResizableNode
 *
 * @constructor
 * @param {ve.dm.MWMapsNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWMapsNode = function VeCeMWMaps( model, config ) {
	this.$map = $( '<div>' ).addClass( 'mw-kartographer-map' );
	this.$thumbinner = $( '<div>' ).addClass( 'thumbinner' );

	// HACK: Copy caption from originalDomElements
	const store = model.doc.getStore();
	const contents = store.value( store.hashOfValue( null, OO.getHash( [ model.getHashObjectForRendering(), null ] ) ) );
	const $caption = $( contents ).find( '.thumbcaption' );

	this.$caption = $caption.length ? $caption.clone() : $( '<div>' ).addClass( 'thumbcaption' );
	this.previewedCaption = model.getAttribute( 'mw' ).attrs.text;

	// Parent constructor
	ve.ce.MWMapsNode.super.apply( this, arguments );

	// Mixin constructors
	ve.ce.ResizableNode.call( this, this.$map, config );

	this.$imageLoader = null;
	this.geoJson = null;
	this.mapData = {};

	this.updateMapPosition = OO.ui.debounce( this.updateMapPosition.bind( this ), 300 );
	this.updateGeoJson = OO.ui.debounce( this.updateGeoJson.bind( this ), 300 );

	// Events
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );
	this.connect( this, { focus: 'onMapFocus' } );

	// Ensure we have the styles to render the map node
	mw.loader.load( 'ext.kartographer' );

	// DOM changes
	this.$element
		.empty()
		.addClass( 've-ce-mwMapsNode mw-kartographer-container thumb' )
		.append(
			this.$thumbinner.append(
				this.$map, this.$caption
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
	const mwData = this.model.getAttribute( 'mw' );

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
	const requiresInteractive = this.requiresInteractive();
	const mwAttrs = this.model.getAttribute( 'mw' ).attrs;
	const isFullWidth = mwAttrs.width === 'full' || mwAttrs.width === '100%';
	const align = !isFullWidth &&
			( mwAttrs.align || ( this.model.doc.getDir() === 'ltr' ? 'right' : 'left' ) );
	const alignClasses = {
		left: 'floatleft',
		center: 'center',
		right: 'floatright'
	};
	const frameless = 'frameless' in mwAttrs && !mwAttrs.text;

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
		// Preload larger static map for resizing
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
		default:
			this.showHandles( [] );
	}

	if ( mwAttrs.text !== this.previewedCaption ) {
		this.previewedCaption = mwAttrs.text;
		// Same basic sanitization as in Sanitizer::decodeTagAttributes()
		const caption = ( mwAttrs.text || '' ).trim().replace( /\s+/g, ' ' );
		if ( !caption ) {
			this.$caption.empty();
		} else {
			const $caption = this.$caption;
			new mw.Api()
				.parse( caption, {
					// Minimize the JSON we get back
					prop: 'text',
					wrapoutputclass: '',
					disablelimitreport: 1,
					disabletoc: 1
				} )
				.done( ( html ) => {
					$caption.html( html );
				} );
		}
	}

	this.$thumbinner.remove();
	// Classes documented in removeClass
	// eslint-disable-next-line mediawiki/class-doc
	this.$element
		.append( frameless ? this.$map : this.$thumbinner.prepend( this.$map ) )
		.removeClass( 'floatleft center floatright' )
		.addClass( alignClasses[ align ] );
	const dim = this.model.getCurrentDimensions();
	if ( isFullWidth ) {
		dim.width = '100%';
	}
	this.$map
		.css( dim );
	this.$thumbinner
		.css( 'width', dim.width );
};

/**
 * Setup an interactive map
 */
ve.ce.MWMapsNode.prototype.setupMap = function () {
	const mwData = this.model.getAttribute( 'mw' );
	const mwAttrs = mwData && mwData.attrs;
	const util = require( 'ext.kartographer.util' );

	this.map = require( 'ext.kartographer.box' ).map( {
		container: this.$map[ 0 ],
		center: [ +mwAttrs.latitude, +mwAttrs.longitude ],
		zoom: +mwAttrs.zoom,
		captionText: mwAttrs.text,
		lang: mwAttrs.lang || util.getDefaultLanguage()
		// TODO: Support style editing
	} );
	this.map.on( 'layeradd', this.updateMapPosition, this );
	this.map.doWhenReady( () => {
		this.updateGeoJson();

		// Disable interaction
		this.map.dragging.disable();
		this.map.touchZoom.disable();
		this.map.doubleClickZoom.disable();
		this.map.scrollWheelZoom.disable();
		this.map.keyboard.disable();
	} );
};

/**
 * Update the GeoJSON layer from the current model state
 */
ve.ce.MWMapsNode.prototype.updateGeoJson = function () {
	if ( !this.model ) {
		return;
	}

	const mwData = this.model.getAttribute( 'mw' );
	const geoJson = ve.getProp( mwData, 'body', 'extsrc' );

	if ( geoJson !== this.geoJson ) {
		require( 'ext.kartographer.editing' ).updateKartographerLayer( this.map, geoJson ).then( this.updateMapPosition.bind( this ) );
		this.geoJson = geoJson;
	}
};

/**
 * Updates the map position (center and zoom) from the current model state.
 */
ve.ce.MWMapsNode.prototype.updateMapPosition = function () {
	if ( !this.model ) {
		return;
	}

	const mwData = this.model.getAttribute( 'mw' );
	const mapData = this.mapData;
	const updatedData = mwData && mwData.attrs;

	if ( !updatedData ) {
		// auto calculate the position
		this.map.setView( null, mapData.zoom );
		const current = this.map.getMapPosition();
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
 * @param {number} [width]
 * @param {number} [height]
 */
ve.ce.MWMapsNode.prototype.updateStatic = function ( width, height ) {
	if ( !this.model.getCurrentDimensions().width ) {
		return;
	}

	if ( this.$imageLoader ) {
		this.$imageLoader.off();
		this.$imageLoader = null;
	}

	const url = this.model.getUrl( width, height );

	this.$imageLoader = $( '<img>' ).on( 'load', () => {
		this.$map.css( 'backgroundImage', 'url(' + url + ')' );
	} ).attr( 'src', url );
};

/**
 * @inheritdoc
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
 * @inheritdoc
 * @param {number} width
 * @param {number} height
 * @return {Object}
 */
ve.ce.MWMapsNode.prototype.getAttributeChanges = function ( width, height ) {
	const mwData = ve.copy( this.model.getAttribute( 'mw' ) );

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
