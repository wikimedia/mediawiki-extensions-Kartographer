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
	var panel;

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.initialize.call( this );

	this.$mapContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-mapWidget' );
	this.$map = $( '<div>' ).appendTo( this.$mapContainer );
	this.map = null;
	this.mapPromise = null;
	this.scalable = null;
	this.updatingGeoJson = false;

	this.dimensions = new ve.ui.DimensionsWidget();

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
	this.input.connect( this, {
		change: 'updateGeoJson',
		resize: 'updateSize'
	} );
	this.dimensions.connect( this, {
		widthChange: 'onDimensionsChange',
		heightChange: 'onDimensionsChange'
	} );

	panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-size' )
	} );

	this.geoJsonField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-geojson' )
	} );

	panel.$element.append( this.dimensionsField.$element, this.$mapContainer, this.geoJsonField.$element );
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
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWMapsDialog.prototype.updateMwData = function ( mwData ) {
	var center, latitude, longitude, zoom,
		dimensions = this.scalable.getBoundedDimensions(
			this.dimensions.getDimensions()
		);

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.updateMwData.call( this, mwData );

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
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.setupMap();
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return ve.ui.MWMapsDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.input.clearUndoStack();

			this.actions.setMode( this.selectedNode ? 'edit' : 'insert' );

			if ( this.selectedNode instanceof ve.dm.MWMapsNode ) {
				this.scalable = this.selectedNode.getScalable();
			} else {
				this.scalable = ve.dm.MWMapsNode.static.createScalable( { width: 400, height: 300 } );
			}

			// TODO: Support block/inline conversion

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() );
		}, this );
};

/**
 * Setup the map control
 */
ve.ui.MWMapsDialog.prototype.setupMap = function () {
	var dialog = this;

	if ( this.mapPromise ) {
		return;
	}

	this.mapPromise = mw.loader.using( 'ext.kartographer.editor' ).then( function () {
		var latitude, longitude, zoom, geoJsonLayer, drawControl,
			mwData = dialog.selectedNode && dialog.selectedNode.getAttribute( 'mw' ),
			mwAttrs = mwData && mwData.attrs,
			defaultShapeOptions = { shapeOptions: L.mapbox.simplestyle.style( {} ) };

		if ( mwAttrs && mwAttrs.zoom ) {
			latitude = +mwAttrs.latitude;
			longitude = +mwAttrs.longitude;
			zoom = +mwAttrs.zoom;
		} else {
			latitude = 0;
			longitude = 0;
			zoom = 2;
		}

		dialog.map = mw.kartographer.createMap( dialog.$map[ 0 ], {
			latitude: latitude,
			longitude: longitude,
			zoom: zoom
			// TODO: Support style editing
		} );

		dialog.updateGeoJson();
		dialog.onDimensionsChange();

		geoJsonLayer = mw.kartographer.getKartographerLayer( dialog.map );
		drawControl = new L.Control.Draw( {
			edit: { featureGroup: geoJsonLayer },
			draw: {
				circle: false,
				// TODO: Determine metric preference from locale information
				polyline: defaultShapeOptions,
				polygon: defaultShapeOptions,
				rectangle: defaultShapeOptions,
				marker: { icon: L.mapbox.marker.icon( {} ) }
			}
		} ).addTo( dialog.map );

		function update() {
			// Prevent circular update of map
			dialog.updatingGeoJson = true;
			try {
				dialog.input.setValue( JSON.stringify( geoJsonLayer.toGeoJSON(), null, '  ' ) );
			} finally {
				dialog.updatingGeoJson = false;
			}
		}

		function created( e ) {
			e.layer.addTo( geoJsonLayer );
			update();
		}

		dialog.map
			.on( 'draw:edited', update )
			.on( 'draw:deleted', update )
			.on( 'draw:created', created );
	} );
};

/**
 * Update the GeoJSON layer from the current input state
 */
ve.ui.MWMapsDialog.prototype.updateGeoJson = function () {
	var isValid;

	if ( !this.map || this.updatingGeoJson ) {
		return;
	}

	isValid = mw.kartographer.updateKartographerLayer( this.map, this.input.getValue() );
	this.input.setValidityFlag( isValid );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.dimensions.clear();
			if ( this.map ) {
				this.map.remove();
				this.map = null;
				this.mapPromise = null;
			}
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWMapsDialog );
