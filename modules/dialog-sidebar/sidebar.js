/**
 * Sidebar class for displaying map details and other services.
 *
 * @class Kartographer.DialogSideBar
 */

var storage = require( 'mediawiki.storage' ).local,
	/** Storage key for Last known selected map type in sidebar */
	SELECTEDTYPE_KEY = 'ext.kartographer.sidebar.selectedType';

/**
 * @constructor
 * @param {Object} options
 */
function SideBar( options ) {
	/**
	 * @property {Kartographer.Dialog.DialogClass}
	 */
	this.dialog = options.dialog;

	this.showAllServices = false;
	this.toggleShowServices = null;

	/**
	 * @property {Object}
	 */
	this.initialMapPosition = this.dialog.map.getInitialMapPosition();

	/**
	 * @property {Object}
	 */
	this.metadata = require( '../../externalLinks.json' );
	this.parseExternalLinks();
}

/**
 * Replaces link variables with contextual data.
 *
 * @param {string} url
 * @return {string}
 */
SideBar.prototype.formatLink = function ( url ) {
	var scale = Math.round( Math.pow( 2, Math.min( 3, Math.max( 0, 18 - this.initialMapPosition.zoom ) ) ) * 1000 );
	url = url.replace( /{latitude}/g, this.initialMapPosition.center.lat );
	url = url.replace( /{longitude}/g, this.initialMapPosition.center.lng );
	url = url.replace( /{zoom}/g, this.initialMapPosition.zoom || mw.config.get( 'wgKartographerFallbackZoom' ) );
	url = url.replace( /{title}/g, mw.config.get( 'wgTitle' ) );
	url = url.replace( /{language}/g, this.dialog.map.lang );
	url = url.replace( /{scale}/g, scale );

	return url;
};

/**
 * Toggles the sidebar
 *
 * @param {boolean} open Whether to open the sidebar or close it.
 * @chainable
 */
SideBar.prototype.toggle = function ( open ) {

	if ( this.$el ) {
		this.tearDown();
	}
	if ( !open ) {
		return;
	}

	this.render();
	return this;
};

/**
 * Renders the sidebar.
 *
 * @chainable
 */
SideBar.prototype.render = function () {
	var sidebar = this,
		map = sidebar.dialog.map,
		$container;

	/**
	 * @property {jQuery}
	 */
	$container = sidebar.$el = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-sidebar' );

	/**
	 * @property {Object}
	 */
	sidebar.mapPosition = map.getMapPosition( { scaled: true } );
	/**
	 * @property {jQuery}
	 */
	sidebar.$mapDetailsContainer = $( '<div>' ).addClass( 'mw-kartographer-mapdetails' ).appendTo( $container );

	/**
	 * @property {jQuery}
	 */
	sidebar.$filterContainer = $( '<div>' ).addClass( 'mw-kartographer-filterservices' ).appendTo( $container );

	/**
	 * @property {jQuery}
	 */
	sidebar.$servicesContainer = $( '<div>' ).addClass( 'mw-kartographer-externalservices' ).appendTo( $container );

	sidebar.renderMapDetails();
	sidebar.renderTypeFilter();
	sidebar.renderExternalServices();

	$container.appendTo( sidebar.dialog.$body );

	map.on( 'move', sidebar.onMapMove, sidebar );

	return sidebar;
};

/**
 * Throttled function that is called on map `move` events, and that
 * re-renders the parts of the sidebar that depend on the map position.
 */
SideBar.prototype.onMapMove = OO.ui.throttle( function () {
	if ( !this.dialog.map ) {
		return;
	}
	this.mapPosition = this.dialog.map.getMapPosition( { scaled: true } );

	this.renderMapDetails();
}, 350 );

/**
 * Renders the map details partial into its container.
 */
SideBar.prototype.renderMapDetails = function () {
	// FIXME: Store $coords in memory to avoid selector here.
	// eslint-disable-next-line no-jquery/no-global-selector
	var $coords = $( '.mw-kartographer-mapdetails-coordinates' );

	if ( !$coords.length ) {
		// Only re-create the DOM elements if they don't already
		// exist and are attached
		this.labelLongtitude = new OO.ui.LabelWidget( {
			classes: [ 'longitude' ],
			title: mw.msg( 'kartographer-sidebar-longitude' ),
			label: String( this.mapPosition.center.lng )
		} );
		this.labelLatitude = new OO.ui.LabelWidget( {
			classes: [ 'latitude' ],
			title: mw.msg( 'kartographer-sidebar-latitude' ),
			label: String( this.mapPosition.center.lat )
		} );

		$coords = $( '<div>' )
			.addClass( 'mw-kartographer-mapdetails-coordinates' )
			.append(
				new OO.ui.LabelWidget( {
					classes: [ 'mw-kartographer-mapdetails-coordinates-title' ],
					label: mw.msg( 'kartographer-sidebar-coordinates' )
				} ).$element,
				$( '<div>' )
					.addClass( 'mw-kartographer-mapdetails-coordinates-latlon' )
					.append(
						this.labelLatitude.$element,
						',&nbsp;', // Comma and space
						this.labelLongtitude.$element
					)
			);

		this.closeButton = new OO.ui.ButtonWidget( {
			framed: false,
			classes: [ 'mw-kartographer-mapdetails-title-arrow' ],
			icon: 'next'
		} );

		// Event
		this.closeButton.connect( this.dialog, {
			click: [ 'toggleSideBar', false ]
		} );

		// Append to container
		this.$mapDetailsContainer.append(
			$( '<div>' )
				.addClass( 'mw-kartographer-mapdetails-title' )
				.append(
					new OO.ui.LabelWidget( {
						classes: [ 'mw-kartographer-mapdetails-title-label' ],
						label: mw.msg( 'kartographer-sidebar-mapdetails' )
					} ).$element,
					this.closeButton.$element
				),
			$coords
		);
	}

	// Update the information
	this.labelLongtitude.setLabel( String( this.mapPosition.center.lng ) );
	this.labelLatitude.setLabel( String( this.mapPosition.center.lat ) );
};

/**
 * Renders the type filter dropdown into its container.
 */
SideBar.prototype.renderTypeFilter = function () {
	var sidebar = this,
		dropdown = sidebar.createFilterDropdown(),
		defaultType = sidebar.metadata.types[ 0 ];

	dropdown.getMenu().on( 'select', function ( item ) {
		storage.set( SELECTEDTYPE_KEY, item.getData() );
		sidebar.renderExternalServices();
	} );
	dropdown.getMenu().selectItemByData(
		storage.get( SELECTEDTYPE_KEY ) ||
		defaultType
	);

	sidebar.$filterContainer.append(
		new OO.ui.LabelWidget( {
			classes: [ 'mw-kartographer-filterservices-title' ],
			label: mw.msg( 'kartographer-sidebar-externalservices' )
		} ).$element,
		dropdown.$element
	);
};

/**
 * Renders the external services partial into its container.
 */
SideBar.prototype.renderExternalServices = function () {
	var sidebar = this,
		selectedType = storage.get( SELECTEDTYPE_KEY ),
		$list = this.$servicesContainer.find( '.mw-kartographer-filterservices-list' ),
		toggleShowServicesState = function ( state ) {
			sidebar.showAllServices = state !== undefined ? !!state : !sidebar.showAllServices;
		},
		populateListItems = function ( bypassAndShowAllServices ) {
			var items,
				featured = [],
				regular = [],
				services = sidebar.byType[ selectedType ];

			// eslint-disable-next-line no-jquery/no-each-util
			$.each( services, function ( serviceId, links ) {
				// Only one link is supported per type per service for now.
				var link = links[ 0 ],
					service = sidebar.byService[ serviceId ],
					formatted = service.featured ? featured : regular,
					$item = $( '<div>' )
						.addClass( 'mw-kartographer-filterservices-list-item' )
						.toggleClass( 'mw-kartographer-filterservices-list-item-featured', service.featured )
						.append(
							new OO.ui.ButtonWidget( {
								framed: false,
								href: sidebar.formatLink( link.url ),
								target: '_blank',
								classes: [ 'mw-kartographer-filterservices-list-item-button' ],
								icon: 'newWindow',
								label: service.name
							} ).$element
						);

				formatted.push( $item );
			} );

			$list.empty();
			items = ( bypassAndShowAllServices || sidebar.showAllServices ) ?
				featured.concat( regular ) : featured;

			// Update message
			sidebar.toggleShowServices.setLabel(
				( bypassAndShowAllServices || sidebar.showAllServices ) ?
					mw.msg( 'kartographer-sidebar-externalservices-show-featured' ) :
					mw.msg( 'kartographer-sidebar-externalservices-show-all' )
			);

			$list.append( items );
			return items;
		},
		onToggleShowServicesButton = function () {
			toggleShowServicesState();
			populateListItems();
		};

	if ( !selectedType ) {
		return;
	}

	if ( !$list.length ) {
		$list = $( '<div>' )
			.addClass( 'mw-kartographer-filterservices-list' );
		this.$servicesContainer.append( $list );
	}

	if ( !this.toggleShowServices ) {
		this.toggleShowServices = new OO.ui.ButtonWidget( {
			framed: false,
			flags: [ 'progressive' ],
			classes: [ 'mw-kartographer-filterservices-toggleButton' ]
		} );

		this.toggleShowServices.on( 'click', onToggleShowServicesButton );
		this.$servicesContainer.append( this.toggleShowServices.$element );
	}

	this.toggleShowServices.toggle( true );
	if ( Object.keys( sidebar.byType[ selectedType ] ).length <= 7 ) {
		populateListItems( true );
		this.toggleShowServices.toggle( false );
	} else {
		populateListItems( false );
	}
};

/**
 * Parses external links and builds the convenient {@link #byService} and
 * {@link #byType} catalogs of services.
 */
SideBar.prototype.parseExternalLinks = function () {
	var services = this.metadata.services,
		byService = {},
		byType = {};

	services.forEach( function ( service ) {
		byService[ service.id ] = service;
		service.byType = {};

		service.links.forEach( function ( link ) {
			service.byType[ link.type ] = service.byType[ link.type ] || [];
			service.byType[ link.type ].push( link );

			byType[ link.type ] = byType[ link.type ] || {};
			byType[ link.type ][ service.id ] = [];
			byType[ link.type ][ service.id ].push( link );
		} );
	} );

	/**
	 * @property {Object}
	 */
	this.byService = byService;

	/**
	 * @property {Object}
	 */
	this.byType = byType;
};

/**
 * Create a filter dropdown instance.
 *
 * @return {OO.ui.DropdownWidget}
 */
SideBar.prototype.createFilterDropdown = function () {
	var items,
		labels = this.metadata.localization;

	// eslint-disable-next-line no-jquery/no-map-util
	items = $.map( this.metadata.types, function ( type ) {
		return new OO.ui.MenuOptionWidget( {
			data: type,
			label: labels[ type ],
			title: labels[ type ]
		} );
	} );

	return new OO.ui.DropdownWidget( {
		label: mw.msg( 'kartographer-sidebar-filterdropdown' ),
		menu: {
			items: items
		}
	} );
};

/**
 * Detaches events and removes the element.
 *
 * @chainable
 */
SideBar.prototype.tearDown = function () {
	this.dialog.map.off( 'move', this.onMapMove, this );
	this.dialog.sideBar = null;
	this.$el.remove();
	this.$el = null;
	return this;
};

module.exports = SideBar;
