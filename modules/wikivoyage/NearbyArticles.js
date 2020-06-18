/**
 * Module to fetch nearby articles.
 *
 * @alternateClassName NearbyArticles
 * @class Kartographer.Wikivoyage.NearbyArticles
 * @singleton
 */
var fetchArticlesPromise,
	config = {};

module.exports = {
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
		if ( fetchArticlesPromise ) {
			return fetchArticlesPromise;
		}

		if ( config.url ) {
			fetchArticlesPromise = $.getScript( config.url )
				.then( function () {
					return window.addressPoints;
				} );
		} else {
			fetchArticlesPromise = $.Deferred().reject( 'url for nearby articles is missing.' ).promise();
		}

		return fetchArticlesPromise;
	}
};
