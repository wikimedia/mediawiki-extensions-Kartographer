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
			return $.when.apply( $, promises ).then( function () {
				// Cast function parameters to an array of resolved values.
				return Array.prototype.slice.call( arguments );
			} );
		},
		isEmptyObject: function () {
			return $.isEmptyObject.apply( $, arguments );
		},
		isPlainObject: function () {
			return $.isPlainObject.apply( $, arguments );
		},
		extend: function () {
			return $.extend.apply( $, arguments );
		},
		getJSON: function () {
			return $.getJSON.apply( $, arguments );
		},
		bind: function () {
			return $.proxy.apply( $, arguments );
		},
		mwApi: function ( data ) {
			return ( new mw.Api() ).get( data );
		},
		clientStore: mw.config.get( 'wgKartographerLiveData' )
	} );
};
