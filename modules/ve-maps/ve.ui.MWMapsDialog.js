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

	this.updateMapContentsDebounced = OO.ui.debounce( this.updateMapContents.bind( this ), 300 );

	this.$content.addClass( 've-ui-mwMapsDialog' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWMapsDialog, ve.ui.MWExtensionDialog );

/* Static Properties */

ve.ui.MWMapsDialog.static.name = 'mwMaps';

ve.ui.MWMapsDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwmapsdialog-title' );

ve.ui.MWMapsDialog.static.size = 'large';

ve.ui.MWMapsDialog.static.allowedEmpty = true;

ve.ui.MWMapsDialog.static.selfCloseEmptyBody = true;

ve.ui.MWMapsDialog.static.modelClasses = [ ve.dm.MWMapsNode, ve.dm.MWInlineMapsNode ];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWMapsDialog.super.prototype.initialize.call( this );

	var helpLink = new OO.ui.ButtonWidget( {
		icon: 'help',
		classes: [ 've-ui-mwMapsDialog-help' ],
		title: ve.msg( 'visualeditor-mwmapsdialog-help-title' ),
		href: 'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:VisualEditor/Maps',
		target: '_blank'
	} );

	// Map
	this.$mapContainer = $( '<div>' ).addClass( 've-ui-mwMapsDialog-mapWidget' );
	this.$map = $( '<div>' ).appendTo( this.$mapContainer );
	this.map = null;

	// Panels
	this.indexLayout = new OO.ui.IndexLayout( {
		expanded: false,
		classes: [ 've-ui-mwMapsDialog-indexLayout' ]
	} );
	this.areaPanel = new OO.ui.TabPanelLayout( 'area', {
		expanded: false,
		label: ve.msg( 'visualeditor-mwmapsdialog-area' )
	} );
	this.contentPanel = new OO.ui.TabPanelLayout( 'content', {
		expanded: false,
		label: ve.msg( 'visualeditor-mwmapsdialog-content' )
	} );
	var optionsPanel = new OO.ui.TabPanelLayout( 'options', {
		expanded: false,
		label: ve.msg( 'visualeditor-mwmapsdialog-options' ),
		classes: [ 've-ui-mwMapsDialog-optionsPanel' ]
	} );

	// Map area panel
	this.scalable = null;

	this.latitude = new OO.ui.TextInputWidget();
	var latitudeField = new OO.ui.FieldLayout( this.latitude, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwmapsdialog-position-lat' )
	} );

	this.longitude = new OO.ui.TextInputWidget();
	var longitudeField = new OO.ui.FieldLayout( this.longitude, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwmapsdialog-position-lon' )
	} );

	this.zoom = new OO.ui.NumberInputWidget( { min: 1, max: 19, step: 1 } );
	var zoomField = new OO.ui.FieldLayout( this.zoom, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwmapsdialog-position-zoom' )
	} );

	this.dimensions = new ve.ui.DimensionsWidget();
	this.dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwmapsdialog-size' )
	} );

	this.areaPanel.$element.append(
		this.$areaMap,
		latitudeField.$element,
		longitudeField.$element,
		zoomField.$element,
		this.dimensionsField.$element
	);

	// Map content panel
	this.updatingGeoJson = false;

	this.input = new ve.ui.MWAceEditorWidget( {
		autosize: true,
		maxRows: 10,
		classes: [ 've-ui-mwMapsDialog-geoJSONWidget' ]
	} )
		.setLanguage( 'json' )
		.toggleLineNumbers( false )
		.setDir( 'ltr' );
	var geoJsonField = new OO.ui.FieldLayout( this.input, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-geojson' )
	} );

	this.contentPanel.$element.append(
		this.$contentMap,
		geoJsonField.$element
	);

	// Options panel
	this.caption = new ve.ui.MWMapsCaptionInputWidget();
	var captionField = new OO.ui.FieldLayout( this.caption, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-caption' ),
		help: ve.msg( 'visualeditor-mwmapsdialog-caption-help' ),
		$overlay: this.$body
	} );

	this.align = new ve.ui.AlignWidget( {
		dir: this.getDir()
	} );
	this.displayField = new OO.ui.FieldLayout( this.align, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-display' ),
		help: ve.msg( 'visualeditor-mwmapsdialog-display-help' ),
		$overlay: this.$body
	} );

	this.frame = new OO.ui.ToggleSwitchWidget();
	var frameField = new OO.ui.FieldLayout( this.frame, {
		label: ve.msg( 'visualeditor-mwmapsdialog-frame' ),
		classes: [ 've-ui-mwMapsDialog-frameField' ]
	} );

	// get languages and format them for combobox, initialize with the special `local` setting
	var languages = ve.init.platform.getLanguageCodes()
		.sort()
		.map( function ( languageCode ) {
			return {
				data: languageCode,
				label: ve.msg( 'visualeditor-mwmapsdialog-language-option',
					ve.init.platform.getLanguageName( languageCode ),
					languageCode
				)
			};
		} );
	languages.unshift( {
		data: 'local',
		label: ve.msg( 'visualeditor-mwmapsdialog-language-local', 'local' )
	} );

	this.language = new OO.ui.ComboBoxInputWidget( {
		options: languages,
		menu: {
			filterFromInput: true
		}
	} );
	var languageField = new OO.ui.FieldLayout( this.language, {
		align: 'top',
		label: ve.msg( 'visualeditor-mwmapsdialog-language' ),
		help: new OO.ui.HtmlSnippet( mw.message( 'visualeditor-mwmapsdialog-language-help' ).parse() ),
		// Allow the help popup to cover the entire dialog, not only the IndexLayout at the bottom
		$overlay: this.$body
	} );

	optionsPanel.$element.append(
		captionField.$element,
		this.displayField.$element,
		frameField.$element,
		languageField.$element
	);

	// Initialize
	this.indexLayout.addTabPanels( [
		this.areaPanel,
		this.contentPanel,
		optionsPanel
	] );
	this.$body.append(
		this.$mapContainer,
		this.indexLayout.$element,
		helpLink.$element
	);
};

/**
 * Handle change events on the latitude and longitude widgets
 */
ve.ui.MWMapsDialog.prototype.onCoordsChange = function () {
	this.updateActions();
	if ( this.wasDragging ) {
		return;
	}
	this.updateMapArea();
	this.resetMapPosition();
};

/**
 * Handle zoom change events
 */
ve.ui.MWMapsDialog.prototype.onZoomChange = function () {
	this.updateActions();
	this.updateMapArea();
	this.resetMapZoomAndPosition();
};

/**
 * Handle change events on the dimensions widget
 */
ve.ui.MWMapsDialog.prototype.onDimensionsChange = function () {
	this.updateActions();
	if ( this.wasDragging ) {
		return;
	}
	this.updateMapArea();
	this.resetMapZoomAndPosition();
};

/**
 * Update the greyed out selected map area shown on the map
 */
ve.ui.MWMapsDialog.prototype.updateMapArea = function () {
	var
		dimensions, centerCoord, zoom,
		centerPoint, boundPoints, boundCoords;

	if ( !this.map ) {
		return;
	}

	dimensions = this.dimensions.getDimensions();
	var width = this.dimensions.getWidth(),
		isFullWidth = width === 'full' || width === '100%',
		initialDimensions = this.scalable.getCurrentDimensions();
	if ( !isFinite( dimensions.width ) ) {
		// Fullscreen width is hard-coded; otherwise reuse the initial values from getSetupProcess
		dimensions.width = isFullWidth ? 800 : ( initialDimensions.width || 300 );
	}
	if ( !isFinite( dimensions.height ) ) {
		dimensions.height = initialDimensions.height || 300;
	}
	// The user input might be non-numeric, still try to visualize if possible
	centerCoord = [ parseFloat( this.latitude.getValue() ), parseFloat( this.longitude.getValue() ) ];
	zoom = this.zoom.getValue();

	centerPoint = this.map.project( centerCoord, zoom );
	boundPoints = [
		centerPoint.add( [ dimensions.width / 2, dimensions.height / 2 ] ),
		centerPoint.add( [ -dimensions.width / 2, -dimensions.height / 2 ] )
	];
	boundCoords = [
		this.map.unproject( boundPoints[ 0 ], zoom ),
		this.map.unproject( boundPoints[ 1 ], zoom )
	];

	this.mapArea.setBounds( boundCoords );
	// Re-render the drag markers
	this.mapArea.editing.disable();
	if ( !this.isReadOnly() ) {
		this.mapArea.editing.enable();
	}

	this.updateMapCutout( this.mapArea.getLatLngs() );
};

/**
 * @private
 * @param {Array} latLngs
 */
ve.ui.MWMapsDialog.prototype.updateMapCutout = function ( latLngs ) {
	// Show black overlay nicely when panning around the world (1/3):
	// * Add some massive bleed to the whole-world coordinates.
	var worldCoords = [
		[ -90, -180 * 2 * 10 ],
		[ -90, 180 * 2 * 10 ],
		[ 90, 180 * 2 * 10 ],
		[ 90, -180 * 2 * 10 ]
	];
	this.mapCutout.setLatLngs( [ worldCoords, latLngs ] );
};

/**
 * Reset the map's zoom and position
 *
 * @param {boolean} [instant=false]
 */
ve.ui.MWMapsDialog.prototype.resetMapZoomAndPosition = function ( instant ) {
	if ( !this.map ) {
		return;
	}

	this.map.fitBounds( this.mapArea.getBounds(), { animate: !instant } );
};

/**
 * Reset the map's position
 *
 * @param {boolean} [instant=false]
 */
ve.ui.MWMapsDialog.prototype.resetMapPosition = function ( instant ) {
	if ( !this.map ) {
		return;
	}

	this.map.panTo( this.mapArea.getCenter(), { animate: !instant } );
};

/**
 * Handle index layout set events
 *
 * @param {OO.ui.TabPanelLayout} tabPanel
 */
ve.ui.MWMapsDialog.prototype.onIndexLayoutSet = function ( tabPanel ) {
	if ( tabPanel === this.areaPanel ) {
		this.contentsDraw.remove();
		this.mapArea.addTo( this.map );
	} else if ( tabPanel === this.contentPanel ) {
		this.mapArea.remove();
		if ( !this.isReadOnly() ) {
			this.contentsDraw.addTo( this.map );
		}
	} else {
		this.mapArea.remove();
		this.contentsDraw.remove();
	}
};

/**
 * @param {string} value
 */
ve.ui.MWMapsDialog.prototype.onCaptionChange = function ( value ) {
	var canBeFrameless = !value;
	if ( this.frame.isDisabled() === canBeFrameless ) {
		var isFramed = true;
		if ( canBeFrameless ) {
			// Reset back to the original value before the dialog opened
			var mwAttrs = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs;
			isFramed = !( mwAttrs && 'frameless' in mwAttrs );
		}
		this.frame.setDisabled( !canBeFrameless ).setValue( isFramed );
	}
	this.updateActions();
};

/**
 * Handle language change events
 *
 * @param {string} lang
 * @param {string} dir
 */
ve.ui.MWMapsDialog.prototype.onLanguageChange = function ( lang ) {
	var util = require( 'ext.kartographer.util' );
	if ( !this.map ) {
		return;
	}
	lang = lang || util.getDefaultLanguage();
	if ( lang.length > 1 ) {
		// Don't re-render if still typing a new lang code
		// TODO: Check lang is a valid code as well
		this.map.setLang( lang );
	}
	this.updateActions();
};

/**
 * @see ve.ui.MWExtensionWindow.insertOrUpdateNode
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
 * @see ve.ui.MWExtensionWindow.updateMwData
 * @param {Object} mwData
 */
ve.ui.MWMapsDialog.prototype.updateMwData = function ( mwData ) {
	// Parent method
	ve.ui.MWMapsDialog.super.prototype.updateMwData.call( this, mwData );

	if ( !this.map ) {
		// Map not loaded in insert, can't insert
		return;
	}

	var latitude = this.latitude.getValue(),
		longitude = this.longitude.getValue(),
		zoom = this.zoom.getValue(),
		caption = this.caption.getValue(),
		lang = this.language.getValue(),
		util = require( 'ext.kartographer.util' ),
		dimensions = this.scalable.getBoundedDimensions(
			this.dimensions.getDimensions()
		),
		width = this.dimensions.getWidth(),
		isFullWidth = width === 'full' || width === '100%';

	mwData.attrs.latitude = latitude.toString();
	mwData.attrs.longitude = longitude.toString();
	mwData.attrs.zoom = zoom.toString();
	if ( caption ) {
		mwData.attrs.text = caption;
	} else if ( mwData.attrs.text ) {
		delete mwData.attrs.text;
	}
	mwData.attrs.lang = ( lang && lang !== util.getDefaultLanguage() ) ? lang : undefined;
	if ( !( this.selectedNode instanceof ve.dm.MWInlineMapsNode ) ) {
		var contentDirection = this.getFragment().getDocument().getDir(),
			defaultAlign = contentDirection === 'ltr' ? 'right' : 'left',
			align = this.align.findSelectedItem().getData();

		if ( isFullWidth ) {
			// Keep the original width="full" or width="100%" in the wikitext
			mwData.attrs.width = width;
		} else if ( dimensions.width ) {
			// Never write NaN to the wikitext
			mwData.attrs.width = dimensions.width.toString();
		}
		if ( dimensions.height ) {
			// Never write NaN to the wikitext
			mwData.attrs.height = dimensions.height.toString();
		}
		// Don't add the default align="…" to existing <mapframe> tags to avoid dirty diffs
		if ( align !== defaultAlign || 'align' in mwData.attrs ) {
			mwData.attrs.align = align;
		}
		// Delete meaningless frameless attribute when there is a text
		if ( this.frame.getValue() || mwData.attrs.text ) {
			delete mwData.attrs.frameless;
		// Keep whatever value was there before to not cause dirty diffs
		} else if ( !( 'frameless' in mwData.attrs ) ) {
			mwData.attrs.frameless = '1';
		}
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
				frameless = 'frameless' in mwAttrs && !mwAttrs.text,
				mapPosition = this.getInitialMapPosition(),
				util = require( 'ext.kartographer.util' ),
				isReadOnly = this.isReadOnly();

			this.input.clearUndoStack();

			if ( this.selectedNode && !inline ) {
				this.scalable = this.selectedNode.getScalable();
			} else {
				this.scalable = ve.dm.MWMapsNode.static.createScalable(
					inline ? { width: 850, height: 400 } : { width: 400, height: 300 }
				);
			}

			// Events
			this.indexLayout.connect( this, { set: 'onIndexLayoutSet' } );

			this.latitude.connect( this, { change: 'onCoordsChange' } );
			this.longitude.connect( this, { change: 'onCoordsChange' } );
			this.zoom.connect( this, { change: 'onZoomChange' } );
			this.dimensions.connect( this, {
				widthChange: 'onDimensionsChange',
				heightChange: 'onDimensionsChange'
			} );

			this.input.connect( this, {
				change: 'updateMapContentsDebounced',
				resize: 'updateSize'
			} );

			this.caption.connect( this, { change: 'onCaptionChange' } );
			this.align.connect( this, { choose: 'updateActions' } );
			this.frame.connect( this, { change: 'updateActions' } );
			this.language.connect( this, { change: 'onLanguageChange' } );

			// Initial values
			this.dimensionsField.toggle( !inline );
			this.displayField.toggle( !inline );

			this.latitude.setValue( String( mapPosition.center[ 0 ] ) ).setReadOnly( isReadOnly );
			this.longitude.setValue( String( mapPosition.center[ 1 ] ) ).setReadOnly( isReadOnly );
			this.zoom.setValue( String( mapPosition.zoom ) ).setReadOnly( isReadOnly );
			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() ).setReadOnly( isReadOnly );
			var width = mwAttrs.width,
				isFullWidth = width === 'full' || width === '100%';
			if ( isFullWidth ) {
				this.dimensions.setWidth( width );
			}

			this.caption.setValue( mwAttrs.text || '' );
			// TODO: Support block/inline conversion
			this.align.selectItemByData( mwAttrs.align || 'right' ).setDisabled( isReadOnly );
			this.frame.setValue( !frameless ).setDisabled( isReadOnly || mwAttrs.text );
			this.language.setValue( mwAttrs.lang || util.getDefaultLanguage() ).setReadOnly( isReadOnly );

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
			mwData = dialog.selectedNode && dialog.selectedNode.getAttribute( 'mw' ),
			mwAttrs = mwData && mwData.attrs;

		// TODO: Support 'style' editing
		dialog.map = require( 'ext.kartographer.box' ).map( {
			container: dialog.$map[ 0 ],
			lang: mwAttrs && mwAttrs.lang || util.getDefaultLanguage(),
			alwaysInteractive: true
		} );

		dialog.map.doWhenReady( function () {
			// Show black overlay nicely when panning around the world (2/3):
			// * Prevent wrapping around the antimeridian, so that we don't have to duplicate the drawings
			//   in imaginary parallel worlds.
			dialog.map.setMaxBounds( [ [ -90, -180 ], [ 90, 180 ] ] );

			dialog.mapArea = L.rectangle( [ [ 0, 0 ], [ 0, 0 ] ], {
				// Invisible
				stroke: false,
				fillOpacity: 0,
				// Prevent the area from affecting cursors (this is unrelated to editing)
				interactive: false
			} );
			dialog.mapArea.editing.enable();

			dialog.mapCutout = L.polygon( [], {
				stroke: false,
				color: 'black',
				// Prevent the area from affecting cursors
				interactive: false
			} );
			dialog.map.addLayer( dialog.mapCutout );

			// Show black overlay nicely when panning around the world (3/3):
			// * Allow large bleed when drawing map cutout area.
			dialog.map.getRenderer( dialog.mapCutout ).options.padding = 10;

			dialog.updateMapContents();
			dialog.updateMapArea();
			dialog.resetMapZoomAndPosition( true );

			function updateCutout() {
				dialog.updateMapCutout( dialog.mapArea.getLatLngs() );
			}

			function updateCoordsAndDimensions() {
				var
					center, bounds, scale, resized,
					topLeftPoint, topRightPoint, bottomLeftPoint,
					lat, lng, width, height;

				dialog.wasDragging = true;

				center = dialog.mapArea.getCenter();
				bounds = dialog.mapArea.getBounds();
				scale = dialog.map.getZoomScale( dialog.zoom.getValue(), dialog.map.getZoom() );

				// Calculate everything before setting anything, because that modifies the `bounds` object
				topLeftPoint = dialog.map.project( bounds.getNorthWest(), dialog.map.getZoom() );
				topRightPoint = dialog.map.project( bounds.getNorthEast(), dialog.map.getZoom() );
				bottomLeftPoint = dialog.map.project( bounds.getSouthWest(), dialog.map.getZoom() );
				width = Math.round( scale * ( topRightPoint.x - topLeftPoint.x ) );
				height = Math.round( scale * ( bottomLeftPoint.y - topLeftPoint.y ) );

				// Round lat/lng to 0.000001 deg (chosen per https://enwp.org/Decimal_degrees#Precision)
				lat = center.lat.toFixed( 6 );
				lng = center.lng.toFixed( 6 );

				// Ignore changes in size by 1px, they happen while moving due to rounding
				resized = Math.abs( dialog.dimensions.getDimensions().width - width ) > 1 ||
					Math.abs( dialog.dimensions.getDimensions().height - height ) > 1;

				dialog.latitude.setValue( lat );
				dialog.longitude.setValue( lng );
				dialog.dimensions.setDimensions( {
					width: width,
					height: height
				} );

				dialog.wasDragging = false;
				dialog.updateMapArea();
				if ( resized ) {
					dialog.resetMapZoomAndPosition();
				} else {
					dialog.resetMapPosition();
				}
			}

			dialog.mapArea
				.on( 'editdrag', updateCutout )
				.on( 'edit', updateCoordsAndDimensions );

			geoJsonLayer = editing.getKartographerLayer( dialog.map );
			dialog.contentsDraw = new L.Control.Draw( {
				edit: { featureGroup: geoJsonLayer },
				draw: {
					circle: false,
					circlemarker: false,
					// TODO: Determine metric preference from locale information
					polyline: defaultShapeOptions,
					polygon: defaultShapeOptions,
					rectangle: defaultShapeOptions,
					marker: { icon: L.mapbox.marker.icon( {} ) }
				}
			} );

			function update() {
				// Prevent circular update of map
				dialog.updatingGeoJson = true;
				try {
					var geoJson = geoJsonLayer.toGeoJSON();
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

			dialog.map
				.on( 'draw:edited', update )
				.on( 'draw:deleted', update )
				.on( 'draw:created', created );

			dialog.onIndexLayoutSet( dialog.indexLayout.getCurrentTabPanel() );
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
		// The wikitext might contain non-numeric user input, still try to visualize if possible
		latitude = parseFloat( mwAttrs.latitude );
		longitude = parseFloat( mwAttrs.longitude );
		zoom = parseInt( mwAttrs.zoom );
	} else if ( pageCoords ) {
		// Use page coordinates if Extension:GeoData is available
		latitude = pageCoords.lat;
		longitude = pageCoords.lon;
		zoom = 5;
	}

	return {
		center: [
			isFinite( latitude ) ? latitude : 30,
			isFinite( longitude ) ? longitude : 0
		],
		zoom: isFinite( zoom ) ? zoom : 2
	};
};

/**
 * Update the GeoJSON layer from the current input state
 */
ve.ui.MWMapsDialog.prototype.updateMapContents = function () {
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
			this.indexLayout.disconnect( this );

			this.latitude.disconnect( this );
			this.longitude.disconnect( this );
			this.zoom.disconnect( this );
			this.dimensions.disconnect( this );

			this.input.disconnect( this );

			this.caption.disconnect( this );
			this.align.disconnect( this );
			this.frame.disconnect( this );
			this.language.disconnect( this );

			if ( this.map ) {
				this.map.remove();
				this.map = null;
			}
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWMapsDialog.prototype.getBodyHeight = function () {
	return 1000;
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWMapsDialog );
