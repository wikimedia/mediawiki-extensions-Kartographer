/*!
 * VisualEditor UserInterface MWMapsDialog class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for editing MW maps.
 *
 * @class
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWMapsDialog = function VeUiMWMapsDialog() {
	// Parent constructor
	ve.ui.MWMapsDialog.super.apply( this, arguments );

	this.mapsApiPromise = null;
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsDialog, ve.ui.MWExtensionDialog );

/* Static Properties */

ve.ui.MWMapsDialog.static.name = 'mwMaps';

ve.ui.MWMapsDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwmapsdialog-title' );

ve.ui.MWMapsDialog.static.size = 'larger';

ve.ui.MWMapsDialog.static.allowedEmpty = true;

ve.ui.MWMapsDialog.static.modelClasses = [ ve.dm.MWMapsNode ];

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.initialize = function () {
	var panel, dimensionsField, modeField, geoJsonField;

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.initialize.call( this );

	this.$mapContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-mapWidget' );
	this.$map = $( '<div>' ).appendTo( this.$mapContainer );
	this.map = null;
	this.scalable = null;

	this.dimensions = new ve.ui.DimensionsWidget().connect( this, {
		widthChange: 'onDimensionsChange',
		heightChange: 'onDimensionsChange'
	} );

	this.modeSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'interactive', label: ve.msg( 'visualeditor-mwmapsdialog-mode-interactive' ) } ),
		new OO.ui.ButtonOptionWidget( { data: 'static', label: ve.msg( 'visualeditor-mwmapsdialog-mode-static' ) } ),
		new OO.ui.ButtonOptionWidget( { data: 'data', label: ve.msg( 'visualeditor-mwmapsdialog-mode-data' ) } )
	] );

	this.input = new ve.ui.MWAceEditorWidget( {
		multiline: true,
		autosize: true,
		maxRows: 10,
		classes: [ 've-ui-mwMapsDialog-geoJSONWidget' ]
	} )
		.setLanguage( 'json' )
		.toggleLineNumbers( false )
		.setRTL( false );

	this.input.on( 'resize', this.updateSize.bind( this ) );

	panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-size' )
	} );

	modeField = new OO.ui.FieldLayout( this.modeSelect, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-mode' )
	} );

	geoJsonField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-geojson' )
	} );

	panel.$element.append( dimensionsField.$element, modeField.$element, this.$mapContainer, geoJsonField.$element );
	this.$body.append( panel.$element );
};

ve.ui.MWMapsDialog.prototype.onDimensionsChange = function () {
	var dimensions, center;

	if ( !this.map ) {
		return;
	}

	dimensions = this.dimensions.getDimensions();
	center = this.map && this.map.getCenter();

	// Set container width for centering
	this.$mapContainer.css( { width: dimensions.width } );
	this.$map.css( dimensions );
	this.updateSize();

	this.map.invalidateSize();
	if ( center ) {
		this.map.setView( center );
	}
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWMapsDialog.prototype.updateMwData = function ( mwData ) {
	var center, latitude, longitude, zoom,
		mode = this.modeSelect.getSelectedItem().getData(),
		dimensions = this.scalable.getBoundedDimensions(
			this.dimensions.getDimensions()
		);

	if ( this.map ) {
		center = this.map.getCenter();
		latitude = center.lat;
		longitude = center.lng;
		zoom = this.map.getZoom();
	} else {
		// Map not loaded in insert, can't insert
		return;
	}

	mwData.attrs.latitude = latitude.toString();
	mwData.attrs.longitude = longitude.toString();
	mwData.attrs.width = dimensions.width.toString();
	mwData.attrs.height = dimensions.height.toString();
	mwData.attrs.zoom = zoom.toString();

	mwData.attrs.mode = mode;
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			var dialog = this;
			return mw.loader.using( 'ext.kartographer.live' ).then( function () {
				dialog.setupMap();
			} );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return ve.ui.MWMapsDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs,
				mode = attributes && attributes.mode || 'interactive';

			this.modeSelect.selectItemByData( mode );

			this.actions.setMode( this.selectedNode ? 'edit' : 'insert' );

			this.scalable = this.selectedNode ?
				this.selectedNode.getScalable() :
				ve.dm.MWMapsNode.static.createScalable( { width: 400, height: 300 } );

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() );
		}, this );
};

/**
 * Setup the map control
 */
ve.ui.MWMapsDialog.prototype.setupMap = function () {
	var latitude, longitude, zoom, geoJson,
		mwData = this.selectedNode && this.selectedNode.getAttribute( 'mw' ),
		mwAttrs = mwData && mwData.attrs;

	if ( mwAttrs && mwAttrs.zoom ) {
		latitude = +mwAttrs.latitude;
		longitude = +mwAttrs.longitude;
		zoom = +mwAttrs.zoom;
	} else {
		latitude = 0;
		longitude = 0;
		zoom = 2;
	}

	try {
		geoJson = mwData && JSON.parse( mwData.body.extsrc );
	} catch ( e ) {}

	this.map = mw.kartographer.createMap( this.$map[ 0 ], {
		latitude: latitude,
		longitude: longitude,
		zoom: zoom,
		// TODO: Support style editing
		geoJson: geoJson
	} );

	this.onDimensionsChange();
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.dimensions.clear();
			this.map.remove();
			this.map = null;
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWMapsDialog );
