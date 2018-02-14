/* globals require */
module.Data = ( function ( $, mw, DataManager ) {
	return DataManager( {
		createPromise: function ( callback ) {
			var promise = $.Deferred();
			try {
				callback( promise.resolve.bind( promise ), promise.reject.bind( promise ) );
			} catch ( err ) {
				promise.reject( err );
			}
			return promise;
		},
		whenAllPromises: function ( promises ) {
			return $.when.apply( $, promises );
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
		debounce: function () {
			return $.debounce.apply( $, arguments );
		},
		bind: function () {
			return $.proxy.apply( $, arguments );
		},
		mwApi: function ( data ) {
			return ( new mw.Api() ).get( data );
		},
		mwHtmlElement: function () {
			return mw.html.element.apply( mw.html, arguments );
		},
		mwMsg: function () {
			return mw.msg.apply( mw.msg, arguments );
		},
		mwUri: function ( data ) {
			return new mw.Uri( data );
		},
		clientStore: mw.config.get( 'wgKartographerLiveData' ),
		title: mw.config.get( 'wgPageName' )
	} );
}(
	jQuery,
	mediaWiki,
	require( 'ext.kartographer.data' )
) );
