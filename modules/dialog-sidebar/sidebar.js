/**
 * Sidebar class for displaying map details and other services.
 *
 * @class Kartographer.DialogSideBar
 */

const storage = require( 'mediawiki.storage' ).local;

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
	this.metadata = require( './externalLinks.json' );
	this.parseExternalLinks();
}

/**
 * Storage key for Last known selected map type in sidebar
 */
SideBar.prototype.SELECTEDTYPE_KEY = 'ext.kartographer.sidebar.selectedType';

/**
 * Replaces link variables with contextual data.
 *
 * @param {string} url
 * @return {string}
 */
SideBar.prototype.formatLink = function ( url ) {
	const scale = Math.round( Math.pow( 2, Math.min( 3, Math.max( 0, 18 - this.initialMapPosition.zoom ) ) ) * 1000 );
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
	if ( open ) {
		this.render();
	}
	return this;
};

/**
 * Renders the sidebar.
 *
 * @chainable
 */
SideBar.prototype.render = function () {
	const map = this.dialog.map;

	/**
	 * @property {jQuery}
	 */
	const $container = this.$el = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-sidebar' );

	/**
	 * @property {Object}
	 */
	this.mapPosition = map.getMapPosition( { scaled: true } );
	/**
	 * @property {jQuery}
	 */
	this.$mapDetailsContainer = $( '<div>' ).addClass( 'mw-kartographer-mapdetails' ).appendTo( $container );

	/**
	 * @property {jQuery}
	 */
	this.$filterContainer = $( '<div>' ).addClass( 'mw-kartographer-filterservices' ).appendTo( $container );

	/**
	 * @property {jQuery}
	 */
	this.$servicesContainer = $( '<div>' ).addClass( 'mw-kartographer-externalservices' ).appendTo( $container );

	this.renderMapDetails();
	this.renderTypeFilter();
	this.renderExternalServices();

	$container.appendTo( this.dialog.$mapBody );

	map.on( 'move', this.onMapMove, this );

	return this;
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
	let $coords = $( '.mw-kartographer-mapdetails-coordinates' );

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
			icon: 'next',
			invisibleLabel: true,
			label: mw.msg( 'kartographer-sidebar-close-button' )
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
	const dropdown = this.createFilterDropdown();
	const defaultType = this.metadata.types[ 0 ];

	dropdown.getMenu().on( 'select', ( item ) => {
		storage.set( this.SELECTEDTYPE_KEY, item.getData() );
		this.renderExternalServices();
	} );
	dropdown.getMenu().selectItemByData(
		storage.get( this.SELECTEDTYPE_KEY ) ||
		defaultType
	);

	this.$filterContainer.append(
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
	const selectedType = storage.get( this.SELECTEDTYPE_KEY );
	let $list = this.$servicesContainer.find( '.mw-kartographer-filterservices-list' );

	const toggleShowServicesState = function ( state ) {
		this.showAllServices = state !== undefined ? !!state : !this.showAllServices;
	};

	const populateListItems = ( bypassAndShowAllServices ) => {
		const featured = [];
		const regular = [];
		const services = this.byType[ selectedType ];

		// eslint-disable-next-line no-jquery/no-each-util
		$.each( services, ( serviceId, links ) => {
			// Only one link is supported per type per service for now.
			const link = links[ 0 ];
			const service = this.byService[ serviceId ];
			const formatted = service.featured ? featured : regular;
			const $item = $( '<div>' )
				.addClass( 'mw-kartographer-filterservices-list-item' )
				.toggleClass( 'mw-kartographer-filterservices-list-item-featured', service.featured )
				.append(
					new OO.ui.ButtonWidget( {
						framed: false,
						href: this.formatLink( link.url ),
						target: '_blank',
						classes: [ 'mw-kartographer-filterservices-list-item-button' ],
						icon: 'newWindow',
						label: service.name
					} ).$element
				);

			formatted.push( $item );
		} );

		$list.empty();
		const items = ( bypassAndShowAllServices || this.showAllServices ) ?
			featured.concat( regular ) : featured;

		// Update message
		this.toggleShowServices.setLabel(
			( bypassAndShowAllServices || this.showAllServices ) ?
				mw.msg( 'kartographer-sidebar-externalservices-show-featured' ) :
				mw.msg( 'kartographer-sidebar-externalservices-show-all' )
		);

		$list.append( items );
		return items;
	};
	const onToggleShowServicesButton = () => {
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
	if ( Object.keys( this.byType[ selectedType ] ).length <= 7 ) {
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
	const services = this.metadata.services;
	const byService = {};
	const byType = {};

	services.forEach( ( service ) => {
		byService[ service.id ] = service;
		service.byType = {};

		service.links.forEach( ( link ) => {
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
	const labels = this.metadata.localization;

	// eslint-disable-next-line no-jquery/no-map-util
	const items = $.map( this.metadata.types, ( type ) => new OO.ui.MenuOptionWidget( {
		data: type,
		label: labels[ type ]
	} ) );

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
