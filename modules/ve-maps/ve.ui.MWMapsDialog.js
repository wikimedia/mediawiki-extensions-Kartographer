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

ve.ui.MWMapsDialog.static.modelClasses = [ ve.dm.MWMapsNode, ve.dm.MWInlineMapsNode ];

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.initialize = function () {
	var panel, modeField;

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.initialize.call( this );

	this.$mapContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-mapWidget' );
	this.$map = $( '<div>' ).appendTo( this.$mapContainer );
	this.map = null;
	this.scalable = null;

	this.dimensions = new ve.ui.DimensionsWidget();

	this.modeSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'interactive', label: ve.msg( 'visualeditor-mwmapsdialog-mode-interactive' ) } ),
		new OO.ui.ButtonOptionWidget( { data: 'static', label: ve.msg( 'visualeditor-mwmapsdialog-mode-static' ) } ),
		new OO.ui.ButtonOptionWidget( { data: 'link', label: ve.msg( 'visualeditor-mwmapsdialog-mode-link' ) } ),
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

	// Events
	this.input.connect( this, { resize: 'updateSize' } );
	this.dimensions.connect( this, {
		widthChange: 'onDimensionsChange',
		heightChange: 'onDimensionsChange'
	} );
	this.modeSelect.connect( this, { select: 'onModeSelectSelect' } );

	panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-size' )
	} );

	modeField = new OO.ui.FieldLayout( this.modeSelect, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-mode' )
	} );

	this.geoJsonField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-geojson' )
	} );

	panel.$element.append( this.dimensionsField.$element, modeField.$element, this.$mapContainer, this.geoJsonField.$element );
	this.$body.append( panel.$element );
};

/**
 * Handle change events on the dimensions widget
 *
 * @param {string} newValue
 */
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
 * Handle select events on the mode select widget
 *
 * @param {OO.ui.OptionWidget|null} item Selected item
 */
ve.ui.MWMapsDialog.prototype.onModeSelectSelect = function ( item ) {
	var mode = item && item.getData();

	this.geoJsonField.toggle( mode !== 'static' );
	this.dimensionsField.toggle( mode !== 'data' );
	this.$mapContainer.toggleClass( 'oo-ui-element-hidden', mode === 'data' );

	this.updateSize();

	if ( mode !== 'data' ) {
		this.setupMap();
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

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.updateMwData.call( this, mwData );

	if ( mode !== 'data' ) {
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
		mwData.attrs.zoom = zoom.toString();
		mwData.attrs.width = dimensions.width.toString();
		mwData.attrs.height = dimensions.height.toString();
	} else {
		delete mwData.attrs.latitude;
		delete mwData.attrs.longitude;
		delete mwData.attrs.zoom;
		delete mwData.attrs.width;
		delete mwData.attrs.height;
	}

	mwData.attrs.mode = mode;
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			var attributes = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs,
				mode = attributes && attributes.mode || 'interactive';

			if ( mode !== 'data' ) {
				this.setupMap();
			}
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
				mode = attributes && attributes.mode || 'interactive',
				isInline = this.selectedNode instanceof ve.dm.MWInlineMapsNode;

			this.modeSelect.selectItemByData( mode );

			this.actions.setMode( this.selectedNode ? 'edit' : 'insert' );

			if ( this.selectedNode instanceof ve.dm.MWMapsNode ) {
				this.scalable = this.selectedNode.getScalable();
			} else if ( this.selectedNode && mode === 'link' ) {
				this.scalable = ve.dm.MWMapsNode.static.createScalable( {
					width: this.selectedNode.getAttribute( 'width' ),
					height: this.selectedNode.getAttribute( 'height' )
				} );
			} else {
				this.scalable = ve.dm.MWMapsNode.static.createScalable( { width: 400, height: 300 } );
			}

			// TODO: Support block/inline conversion
			this.modeSelect.getItemFromData( 'interactive' ).toggle( !isInline );
			this.modeSelect.getItemFromData( 'static' ).toggle( !isInline );
			this.modeSelect.getItemFromData( 'link' ).toggle( isInline );
			this.modeSelect.getItemFromData( 'data' ).toggle( isInline );

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() );
		}, this );
};

/**
 * Setup the map control
 *
 * @return {jQuery.Promise} Promise which resolves when the map is setup
 */
ve.ui.MWMapsDialog.prototype.setupMap = function () {
	var dialog = this;

	if ( this.map ) {
		return $.Deferred().resolve().promise();
	}

	return mw.loader.using( 'ext.kartographer.live' ).then( function () {
		var latitude, longitude, zoom, geoJson,
			mwData = dialog.selectedNode && dialog.selectedNode.getAttribute( 'mw' ),
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

		dialog.map = mw.kartographer.createMap( dialog.$map[ 0 ], {
			latitude: latitude,
			longitude: longitude,
			zoom: zoom,
			// TODO: Support style editing
			geoJson: geoJson
		} );

		dialog.onDimensionsChange();
	} );
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
