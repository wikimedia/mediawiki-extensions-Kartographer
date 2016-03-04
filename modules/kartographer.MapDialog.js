mw.kartographer.MapDialog = function MwKartographerMapDialog() {
	// Parent method
	mw.kartographer.MapDialog.super.apply( this, arguments );
};

OO.inheritClass( mw.kartographer.MapDialog, OO.ui.ProcessDialog );

mw.kartographer.MapDialog.static.size = 'full';

mw.kartographer.MapDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwmapsdialog-title' );

mw.kartographer.MapDialog.static.actions = [
	{
		label: OO.ui.deferMsg( 'kartographer-fullscreen-close' ),
		flags: [ 'safe', 'back' ],
		modes: [ 'edit', 'insert' ]
	}
];

mw.kartographer.MapDialog.prototype.initialize = function () {
	// Parent method
	mw.kartographer.MapDialog.super.prototype.initialize.apply( this, arguments );

	this.$map = $( '<div>' ).addClass( 'mw-kartographer-mapDialog-map' );
	this.map = null;

	this.$body.append( this.$map );
};

mw.kartographer.MapDialog.prototype.getSetupProcess = function ( data ) {
	return mw.kartographer.MapDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			this.map = mw.kartographer.createMap( this.$map[ 0 ], data );
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
