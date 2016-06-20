/* globals module */
/**
 * Module to fetch nearby articles.
 *
 * @alias NearbyArticles
 * @class Kartographer.Wikivoyage.NearbyArticles
 * @singleton
 * @private
 */
module.NearbyArticles = ( function ( $ ) {

	var fetchArticlesDeferred,
		data,
		config = {};

	return {
		setConfig: function ( obj ) {
			var key;
			for ( key in obj ) {
				config[ key ] = obj[ key ];
			}
		},

		getConfig: function ( configParam ) {
			return config[ configParam ];
		},

		fetch: function () {
			if ( fetchArticlesDeferred ) {
				return fetchArticlesDeferred;
			} else {
				fetchArticlesDeferred = $.Deferred();
			}

			if ( !config.url ) {
				fetchArticlesDeferred.reject( 'url for nearby articles is missing.' );
			}

			if ( !data ) {
				// fetch
				$.getScript( config.url )
					.done( function () {
						data = window.addressPoints;
						fetchArticlesDeferred.resolve( data ).promise();
					} );
			} else {
				fetchArticlesDeferred.resolve( data );
			}
			return fetchArticlesDeferred.promise();
		}
	};

} )( jQuery );
