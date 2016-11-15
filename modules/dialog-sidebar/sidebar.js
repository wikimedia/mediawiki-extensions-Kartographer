/* globals module */
/**
 * Sidebar class for displaying map details and other services.
 *
 * @class Kartographer.DialogSideBar
 */
module.exports = ( function ( $, mw ) {

	/**
	 * @constructor
	 * @param {Object} options
	 */
	var SideBar = function ( options ) {
			/**
			 * @property {Kartographer.Dialog.DialogClass}
			 */
			this.dialog = options.dialog;

			/**
			 * @property {Object}
			 */
			this.metadata = mw.config.get( 'wgKartographerExternalLinks' );
			this.parseExternalLinks();
		},
		MODULE_NAME = 'ext.kartographer.dialog.sidebar',
		selectedType;

	/**
	 * Replaces link variables with contextual data.
	 *
	 * @param {string} url
	 * @return {string}
	 */
	SideBar.prototype.formatLink = function ( url ) {
		var mapPosition = this.mapPosition,
			link = url.replace( new RegExp( '{latitude}', 'g' ), mapPosition.center.lat );
		link = link.replace( new RegExp( '{longitude}', 'g' ), mapPosition.center.lng );
		link = link.replace( new RegExp( '{zoom}', 'g' ), mapPosition.zoom );
		link = link.replace( new RegExp( '{title}', 'g' ), mw.config.get( 'wgTitle' ) );
		link = link.replace( new RegExp( '{language}', 'g' ), mw.config.get( 'wgContentLanguage' ) || mw.config.get( 'wgUserLanguage' ) );
		return link;
	};

	// eslint-disable-next-line valid-jsdoc
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

	// eslint-disable-next-line valid-jsdoc
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
		$container = sidebar.$el = $( '<div class="mw-kartographer-mapDialog-sidebar">' );

		/**
		 * @property {Object}
		 */
		sidebar.mapPosition = map.getMapPosition( { scaled: true } );

		sidebar.createCloseButton().$element.appendTo( $container );

		/**
		 * @property {jQuery}
		 */
		sidebar.$mapDetailsContainer = $( '<div>' ).addClass( 'mw-kartographer-mapdetails' ).appendTo( $container );

		/**
		 * @property {jQuery}
		 */
		sidebar.$descriptionContainer = $( '<div>' ).addClass( 'mw-kartographer-description' ).appendTo( $container );

		/**
		 * @property {jQuery}
		 */
		sidebar.$filterContainer = $( '<div>' ).addClass( 'mw-kartographer-filterservices' ).appendTo( $container );

		/**
		 * @property {jQuery}
		 */
		sidebar.$servicesContainer = $( '<div>' ).addClass( 'mw-kartographer-externalservices' ).appendTo( $container );

		sidebar.renderMapDetails();
		sidebar.renderDescription();
		sidebar.renderTypeFilter();
		sidebar.renderExternalServices();

		$container.appendTo( sidebar.dialog.$body );

		map.on( 'move', sidebar.onMapMove, sidebar );

		sidebar.$servicesContainer.on( 'click', 'a', function () {
			mw.track( 'mediawiki.kartographer', {
				action: 'sidebar-click',
				isFullScreen: true,
				service: $( this ).data( 'service' ),
				type: selectedType,
				feature: map.parentMap || map.parentLink
			} );
		} );

		return sidebar;
	};

	/**
	 * Throttled function that is called on map `move` events, and that
	 * re-renders the parts of the sidebar that depend on the map position.
	 */
	SideBar.prototype.onMapMove = OO.ui.throttle( function () {
		this.mapPosition = this.dialog.map.getMapPosition( { scaled: true } );

		this.renderMapDetails();
		this.renderExternalServices();
	}, 350 );

	/**
	 * Renders the map details partial into its container.
	 */
	SideBar.prototype.renderMapDetails = function () {
		var sidebar = this,
			partial = mw.template.get( MODULE_NAME, 'dialog-sidebar-mapdetails.mustache' ).render( {
				LBL_COORDINATES: mw.msg( 'kartographer-sidebar-coordinates' ),
				LBL_LATITUDE: mw.msg( 'kartographer-sidebar-latitude' ),
				LBL_LONGITUDE: mw.msg( 'kartographer-sidebar-longitude' ),
				LBL_MAP_DETAILS: mw.msg( 'kartographer-sidebar-mapdetails' ),
				latitude: sidebar.mapPosition.center.lat,
				longitude: sidebar.mapPosition.center.lng,
				zoom: sidebar.mapPosition.zoom
			} );

		sidebar.$mapDetailsContainer.html( partial );
	};

	/**
	 * Renders the description partial into its container.
	 */
	SideBar.prototype.renderDescription = function () {
		this.$descriptionContainer.text( mw.msg( 'kartographer-sidebar-description' ) );
	};

	/**
	 * Renders the type filter dropdown into its container.
	 */
	SideBar.prototype.renderTypeFilter = function () {
		var sidebar = this,
			dropdown = sidebar.createFilterDropdown(),
			defaultType = sidebar.metadata.types[ 0 ],
			first = true;

		dropdown.getMenu().on( 'select', function ( item ) {
			selectedType = item.getData();
			sidebar.renderExternalServices();

			// First selection is the default, skip it.
			if ( !first ) {
				mw.track( 'mediawiki.kartographer', {
					action: 'sidebar-type',
					isFullScreen: true,
					type: selectedType,
					feature: sidebar.dialog.map.parentMap || sidebar.dialog.map.parentLink
				} );
			}
			first = false;
		} );
		dropdown.getMenu().selectItemByData( selectedType || defaultType );

		sidebar.$filterContainer.append( dropdown.$element );
	};

	/**
	 * Renders the external services partial into its container.
	 */
	SideBar.prototype.renderExternalServices = function () {
		var sidebar = this,
			services = sidebar.byType[ selectedType ],
			featured = [],
			regular = [],
			partial;

		if ( !selectedType ) {
			return;
		}

		$.each( services, function ( serviceId, links ) {
			// Only one link is supported per type per service for now.
			var link = links[ 0 ],
				service = sidebar.byService[ serviceId ],
				formatted = service.featured ? featured : regular;

			formatted.push( {
				id: serviceId,
				name: service.name,
				featured: service.featured,
				linkLabel: sidebar.metadata.localization[ selectedType ],
				link: sidebar.formatLink( link.url )
			} );
		} );

		partial = mw.template.get( MODULE_NAME, 'dialog-sidebar-externalservices.mustache' ).render(
			{
				LBL_EXTERNAL_SERVICES: mw.msg( 'kartographer-sidebar-externalservices' ),
				LBL_SERVICE: mw.msg( 'kartographer-sidebar-service' ),
				services: featured.concat( regular ),
				latitude: sidebar.mapPosition.center.lat,
				longitude: sidebar.mapPosition.center.lng,
				zoom: sidebar.mapPosition.zoom
			}
		);

		sidebar.$servicesContainer.html( partial );
	};

	/**
	 * Parses external links and builds the convenient {@link #byService} and
	 * {@link #byType} catalogs of services.
	 */
	SideBar.prototype.parseExternalLinks = function () {
		var services = this.metadata.services,
			byService = {},
			byType = {};

		$.each( services, function ( key, service ) {
			byService[ service.id ] = service;
			service.byType = {};

			$.each( service.links, function ( key, link ) {
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
	 * Creates a close button instance.
	 *
	 * @return {OO.ui.ButtonWidget}
	 */
	SideBar.prototype.createCloseButton = function () {
		// Add close button to the sidebar
		var sidebar = this,
			button = new OO.ui.ButtonWidget( {
				icon: 'close',
				title: mw.msg( 'kartographer-fullscreen-close' ),
				framed: false,
				classes: [ 'mw-kartographer-mapDialog-closeButton' ]
			} ).connect( this, { click: sidebar.dialog.toggleSideBar.bind( sidebar.dialog, false ) } );
		return button;
	};

	/**
	 * Create a filter dropdown instance.
	 *
	 * @return {OO.ui.DropdownWidget}
	 */
	SideBar.prototype.createFilterDropdown = function () {
		var items = [],
			labels = this.metadata.localization;

		$.each( this.metadata.types, function ( key, type ) {
			items.push(
				new OO.ui.MenuOptionWidget( {
					data: type,
					label: labels[ type ],
					title: labels[ type ]
				} )
			);
		} );

		return new OO.ui.DropdownWidget( {
			label: mw.msg( 'kartographer-sidebar-filterdropdown' ),
			menu: {
				items: items
			}
		} );
	};

	// eslint-disable-next-line valid-jsdoc
	/**
	 * Detaches events and removes the element.
	 *
	 * @chainable
	 */
	SideBar.prototype.tearDown = function () {
		this.dialog.map.off( 'move', this.onMapMove, this );

		this.$el.remove();
		this.$el = null;
		return this;
	};

	return SideBar;
}( jQuery, mediaWiki ) );
