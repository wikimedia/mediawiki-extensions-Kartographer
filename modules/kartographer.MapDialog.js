/**
 * Dialog for full screen maps
 *
 * @class
 * @extends OO.ui.Dialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
mw.kartographer.MapDialog = function MwKartographerMapDialog() {
	// Parent method
	mw.kartographer.MapDialog.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( mw.kartographer.MapDialog, OO.ui.Dialog );

/* Static Properties */

mw.kartographer.MapDialog.static.size = 'full';

/* Methods */

mw.kartographer.MapDialog.prototype.initialize = function () {
	var closeButton;

	// Parent method
	mw.kartographer.MapDialog.super.prototype.initialize.apply( this, arguments );

	this.$map = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-map' );
	this.map = null;

	closeButton = new OO.ui.ButtonWidget( {
		icon: 'close',
		title: mw.msg( 'kartographer-fullscreen-close' ),
		classes: [ 'mw-kartographer-mapDialog-closeButton' ]
	} ).connect( this, { click: this.executeAction.bind( this, '' ) } );

	this.$body.append( closeButton.$element, this.$map );
};

mw.kartographer.MapDialog.prototype.getSetupProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.map = mw.kartographer.createMap( this.$map[ 0 ], data );
			mw.hook( 'wikipage.maps' ).fire( this.map, true /* isFullScreen */ );
		}, this );
};

mw.kartographer.MapDialog.prototype.getReadyProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.map.invalidateSize();
		}, this );
};

mw.kartographer.MapDialog.prototype.getTeardownProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getTeardownProcess.call( this, data )
		.next( function () {
			this.map.remove();
			this.map = null;
		}, this );
};
