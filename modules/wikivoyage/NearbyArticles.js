/* globals module */
/**
 * Module to fetch nearby articles.
 *
 * @alias NearbyArticles
 * @class Kartographer.Wikivoyage.NearbyArticles
 * @singleton
 */
module.NearbyArticles = ( function ( $ ) {

	var fetchArticlesDeferred,
		data,
		config = {};

	return {
		/**
		 * Configure the module.
		 *
		 * @param {Object} obj
		 * @param {Object} obj.url The API (script file) containing the nearby
		 *   articles.
		 */
		setConfig: function ( obj ) {
			var key;
			for ( key in obj ) {
				config[ key ] = obj[ key ];
			}
		},

		/**
		 * Gets a configuration parameter.
		 *
		 * @param {string} configParam
		 * @return {*}
		 */
		getConfig: function ( configParam ) {
			return config[ configParam ];
		},

		/**
		 * Fetches nearby articles.
		 *
		 * @return {jQuery.Promise} Promise which resolves with the data array
		 *   once the map is initialized.
		 */
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
