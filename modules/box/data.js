const DataManager = require( 'ext.kartographer.data' );

module.exports = function () {
	return DataManager( {
		createPromise: function ( callback ) {
			const promise = $.Deferred();
			try {
				callback( promise.resolve.bind( promise ), promise.reject.bind( promise ) );
			} catch ( err ) {
				promise.reject( err );
			}
			return promise;
		},
		whenAllPromises: function ( promises ) {
			return $.when.apply( $, promises ).then(
				// Cast function parameters to an array of resolved values.
				( ...args ) => args
			);
		},
		isEmptyObject: function ( ...args ) {
			return $.isEmptyObject( ...args );
		},
		isPlainObject: function ( ...args ) {
			return $.isPlainObject( ...args );
		},
		extend: function ( ...args ) {

			return $.extend( ...args );
		},
		getJSON: function ( ...args ) {
			return $.getJSON( ...args );
		},
		bind: function ( ...args ) {
			// eslint-disable-next-line no-jquery/no-proxy
			return $.proxy( ...args );
		},
		mwApi: function ( data ) {
			return ( new mw.Api() ).get( data );
		},
		clientStore: mw.config.get( 'wgKartographerLiveData' )
	} );
};
