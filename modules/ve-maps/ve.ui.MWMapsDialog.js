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

	this.updateGeoJson = $.debounce( 300, $.proxy( this.updateGeoJson, this ) );
	this.resetMapPosition = $.debounce( 300, $.proxy( this.resetMapPosition, this ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsDialog, ve.ui.MWExtensionDialog );

/* Static Properties */

ve.ui.MWMapsDialog.static.name = 'mwMaps';

ve.ui.MWMapsDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwmapsdialog-title' );

ve.ui.MWMapsDialog.static.size = 'larger';

ve.ui.MWMapsDialog.static.allowedEmpty = true;

ve.ui.MWMapsDialog.static.selfCloseEmptyBody = true;

ve.ui.MWMapsDialog.static.modelClasses = [ ve.dm.MWMapsNode, ve.dm.MWInlineMapsNode ];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.initialize = function () {
	var panel,
		positionPopupButton,
		$currentPositionTable;

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.initialize.call( this );

	this.$mapContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-mapWidget' );
	this.$map = $( '<div>' ).appendTo( this.$mapContainer );
	this.map = null;
	this.scalable = null;
	this.updatingGeoJson = false;

	this.dimensions = new ve.ui.DimensionsWidget();

	this.align = new ve.ui.AlignWidget( {
		dir: this.getDir()
	} );

	this.language = new ve.ui.LanguageInputWidget( {
		classes: [ 've-ui-mwMapsDialog-languageInput' ],
		dirInput: 'none',
		fieldConfig: {
			align: 'right'
		}
	} );

	this.input = new ve.ui.MWAceEditorWidget( {
		autosize: true,
		maxRows: 10,
		classes: [ 've-ui-mwMapsDialog-geoJSONWidget' ]
	} )
		.setLanguage( 'json' )
		.toggleLineNumbers( false )
		.setDir( 'ltr' );

	this.resetMapButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-mwmapsdialog-reset-map' )
	} );

	panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );

	this.dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-size' )
	} );

	this.helpLink = new OO.ui.ButtonWidget( {
		icon: 'help',
		framed: false,
		classes: [ 've-ui-mwMapsDialog-help' ],
		title: ve.msg( 'visualeditor-mwmapsdialog-help-title' ),
		href: 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:VisualEditor/Maps',
		target: '_blank'
	} );

	this.alignField = new OO.ui.FieldLayout( this.align, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-align' )
	} );

	this.languageField = new OO.ui.FieldLayout( this.language, {
		align: 'right',
		label: ve.msg( 'visualeditor-mwmapsdialog-language' )
	} );

	this.$currentPositionLatField = $( '<td></td>' );
	this.$currentPositionLonField = $( '<td></td>' );
	this.$currentPositionZoomField = $( '<td></td>' );
	$currentPositionTable = $( '<table>' ).addClass( 've-ui-mwMapsDialog-position-table' )
		.append( $( '<tr>' ).append( '<th>' + ve.msg( 'visualeditor-mwmapsdialog-position-lat' ) + '</th>' ).append( this.$currentPositionLatField ) )
		.append( $( '<tr>' ).append( '<th>' + ve.msg( 'visualeditor-mwmapsdialog-position-lon' ) + '</th>' ).append( this.$currentPositionLonField ) )
		.append( $( '<tr>' ).append( '<th>' + ve.msg( 'visualeditor-mwmapsdialog-position-zoom' ) + '</th>' ).append( this.$currentPositionZoomField ) );

	positionPopupButton = new OO.ui.PopupButtonWidget( {
		$overlay: this.$overlay,
		label: ve.msg( 'visualeditor-mwmapsdialog-position-button' ),
		icon: 'info',
		framed: false,
		popup: {
			$content: $currentPositionTable,
			padded: true,
			align: 'forwards'
		}
	} );

	this.$mapPositionContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-position' );

	this.geoJsonField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-geojson' )
	} );

	panel.$element.append(
		this.helpLink.$element,
		this.dimensionsField.$element,
		this.alignField.$element,
		this.languageField.$element,
		this.$mapContainer,
		this.$mapPositionContainer.append( positionPopupButton.$element, this.resetMapButton.$element ),
		this.geoJsonField.$element
	);
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

	dimensions = this.scalable.getBoundedDimensions(
		this.dimensions.getDimensions()
	);
	center = this.map && this.map.getCenter();

	// Set container width for centering
	this.$mapContainer.css( { width: dimensions.width } );
	this.$map.css( dimensions );
	this.updateSize();

	if ( center ) {
		this.map.setView( center, this.map.getZoom() );
	}
	this.map.invalidateSize();
	this.updateActions();
};

/**
 * Reset the map's position
 */
ve.ui.MWMapsDialog.prototype.resetMapPosition = function () {
	var position,
		dialog = this;

	if ( !this.map ) {
		return;
	}

	position = this.getInitialMapPosition();
	this.map.setView( position.center, position.zoom );

	this.updateActions();
	this.resetMapButton.setDisabled( true );

	this.map.once( 'moveend', function () {
		dialog.resetMapButton.setDisabled( false );
	} );
};

/**
 * Handle language change events
 *
 * @param {string} lang
 * @param {string} dir
 */
ve.ui.MWMapsDialog.prototype.onLanguageChange = function ( lang ) {
	var util = require( 'ext.kartographer.util' );
	lang = lang || util.getDefaultLanguage();
	if ( lang.length > 1 ) {
		// Don't re-render if still typing a new lang code
		// TODO: Check lang is a valid code as well
		this.map.setLang( lang );
	}
	this.updateActions();
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWMapsDialog.prototype.insertOrUpdateNode = function () {
	// Parent method
	ve.ui.MWMapsDialog.super.prototype.insertOrUpdateNode.apply( this, arguments );

	// Update scalable
	this.scalable.setCurrentDimensions(
		this.scalable.getBoundedDimensions(
			this.dimensions.getDimensions()
		)
	);
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWMapsDialog.prototype.updateMwData = function ( mwData ) {
	var center, scaled, latitude, longitude, zoom,
		lang = this.language.getLang(),
		util = require( 'ext.kartographer.util' ),
		dimensions = this.scalable.getBoundedDimensions(
			this.dimensions.getDimensions()
		);

	// Parent method
	ve.ui.MWMapsDialog.super.prototype.updateMwData.call( this, mwData );

	if ( this.map ) {
		center = this.map.getCenter();
		zoom = this.map.getZoom();
		scaled = this.map.getScaleLatLng( center.lat, center.lng, zoom );
		latitude = scaled[ 0 ];
		longitude = scaled[ 1 ];
	} else {
		// Map not loaded in insert, can't insert
		return;
	}
	mwData.attrs.latitude = latitude.toString();
	mwData.attrs.longitude = longitude.toString();
	mwData.attrs.zoom = zoom.toString();
	mwData.attrs.lang = ( lang && lang !== util.getDefaultLanguage() ) ? lang : undefined;
	if ( !( this.selectedNode instanceof ve.dm.MWInlineMapsNode ) ) {
		mwData.attrs.width = dimensions.width.toString();
		mwData.attrs.height = dimensions.height.toString();
		mwData.attrs.align = this.align.findSelectedItem().getData();
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getReadyProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.pushPending();
			this.setupMap()
				.then( this.popPending.bind( this ) );
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getSetupProcess = function ( data ) {
	data = data || {};
	return ve.ui.MWMapsDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var inline = this.selectedNode instanceof ve.dm.MWInlineMapsNode,
				mwAttrs = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs || {},
				util = require( 'ext.kartographer.util' );

			this.input.clearUndoStack();

			this.actions.setMode( this.selectedNode ? 'edit' : 'insert' );

			if ( this.selectedNode && !inline ) {
				this.scalable = this.selectedNode.getScalable();
			} else {
				this.scalable = ve.dm.MWMapsNode.static.createScalable(
					inline ? { width: 850, height: 400 } : { width: 400, height: 300 }
				);
			}

			// Events
			this.input.connect( this, {
				change: 'updateGeoJson',
				resize: 'updateSize'
			} );
			this.dimensions.connect( this, {
				widthChange: 'onDimensionsChange',
				heightChange: 'onDimensionsChange'
			} );
			this.align.connect( this, { choose: 'updateActions' } );
			this.resetMapButton.connect( this, { click: 'resetMapPosition' } );

			this.dimensionsField.toggle( !inline );

			this.alignField.toggle( !inline );

			// TODO: Support block/inline conversion
			this.align.selectItemByData( mwAttrs.align || 'right' );

			this.language.setLangAndDir( mwAttrs.lang || util.getDefaultLanguage() );
			this.language.connect( this, { change: 'onLanguageChange' } );

			this.resetMapButton.$element.toggle( !!this.selectedNode );

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() );

			this.updateActions();
		}, this );
};

/**
 * Setup the map control
 *
 * @return {jQuery.Promise} Promise that gets resolved when the map
 *  editor finishes loading
 */
ve.ui.MWMapsDialog.prototype.setupMap = function () {
	var dialog = this;

	if ( this.map ) {
		return $.Deferred.promise.resolve();
	}

	return mw.loader.using( 'ext.kartographer.editor' ).then( function () {
		var geoJsonLayer,
			deferred = $.Deferred(),
			editing = require( 'ext.kartographer.editing' ),
			util = require( 'ext.kartographer.util' ),
			defaultShapeOptions = { shapeOptions: L.mapbox.simplestyle.style( {} ) },
			mapPosition = dialog.getInitialMapPosition(),
			mwData = dialog.selectedNode && dialog.selectedNode.getAttribute( 'mw' ),
			mwAttrs = mwData && mwData.attrs;

		// TODO: Support 'style' editing
		dialog.map = require( 'ext.kartographer.box' ).map( {
			container: dialog.$map[ 0 ],
			center: mapPosition.center,
			zoom: mapPosition.zoom,
			lang: mwAttrs && mwAttrs.lang || util.getDefaultLanguage(),
			alwaysInteractive: true
		} );

		dialog.map.doWhenReady( function () {

			dialog.updateGeoJson();
			dialog.onDimensionsChange();
			// Wait for dialog to resize as this triggers map move events
			setTimeout( function () {
				dialog.resetMapPosition();
			}, OO.ui.theme.getDialogTransitionDuration() );

			// if geojson and no center, we need the map to automatically
			// position itself when the feature layer is added.
			if (
				dialog.input.getValue() &&
				( !mapPosition.center || isNaN( mapPosition.center[ 0 ] ) || isNaN( mapPosition.center[ 1 ] ) )
			) {
				dialog.map.on( 'layeradd', function () {
					dialog.map.setView( null, mapPosition.zoom );
					dialog.updateActions();
				} );
			}

			geoJsonLayer = editing.getKartographerLayer( dialog.map );
			new L.Control.Draw( {
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
				var geoJson;
				// Prevent circular update of map
				dialog.updatingGeoJson = true;
				try {
					geoJson = geoJsonLayer.toGeoJSON();
					// Undo the sanitization step's parsing of wikitext
					editing.restoreUnparsedText( geoJson );
					dialog.input.setValue( JSON.stringify( geoJson, null, '  ' ) );
				} finally {
					dialog.updatingGeoJson = false;
				}
				dialog.updateActions();
			}

			function created( e ) {
				e.layer.addTo( geoJsonLayer );
				update();
			}

			function updatePositionContainer() {
				var position = dialog.map.getMapPosition(),
					scaled = dialog.map.getScaleLatLng( position.center.lat, position.center.lng, position.zoom );
				dialog.$currentPositionLatField.text( scaled[ 0 ] );
				dialog.$currentPositionLonField.text( scaled[ 1 ] );
				dialog.$currentPositionZoomField.text( position.zoom );
			}

			function onMapMove() {
				dialog.updateActions();
				updatePositionContainer();
			}

			dialog.map
				.on( 'draw:edited', update )
				.on( 'draw:deleted', update )
				.on( 'draw:created', created )
				.on( 'moveend', onMapMove );
			deferred.resolve();
		} );
		return deferred.promise();
	} );
};

/**
 * Get the initial map position (coordinates and zoom level)
 *
 * @return {Object} Object containing latitude, longitude and zoom
 */
ve.ui.MWMapsDialog.prototype.getInitialMapPosition = function () {
	var latitude, longitude, zoom,
		pageCoords = mw.config.get( 'wgCoordinates' ),
		mwData = this.selectedNode && this.selectedNode.getAttribute( 'mw' ),
		mwAttrs = mwData && mwData.attrs;

	if ( mwAttrs && mwAttrs.zoom ) {
		latitude = +mwAttrs.latitude;
		longitude = +mwAttrs.longitude;
		zoom = +mwAttrs.zoom;
	} else if ( pageCoords ) {
		// Use page coordinates if Extension:GeoData is available
		latitude = pageCoords.lat;
		longitude = pageCoords.lon;
		zoom = 5;
	} else if ( !mwAttrs || !mwAttrs.extsrc ) {
		latitude = 30;
		longitude = 0;
		zoom = 2;
	}

	return {
		center: [ latitude, longitude ],
		zoom: zoom
	};
};

/**
 * Update the GeoJSON layer from the current input state
 */
ve.ui.MWMapsDialog.prototype.updateGeoJson = function () {
	var self = this;

	if ( !this.map || this.updatingGeoJson ) {
		return;
	}

	this.input.pushPending();
	require( 'ext.kartographer.editing' )
		.updateKartographerLayer( this.map, this.input.getValue() )
		.done( function () {
			self.input.setValidityFlag( true );
		} )
		.fail( function () {
			self.input.setValidityFlag( false );
		} )
		.always( function () {
			self.updateActions();
			self.input.popPending();
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWMapsDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			// Events
			this.input.disconnect( this );
			this.dimensions.disconnect( this );
			this.resetMapButton.disconnect( this );

			this.dimensions.clear();
			if ( this.map ) {
				this.map.remove();
				this.map = null;
			}
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWMapsDialog );
