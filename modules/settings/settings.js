/**
 * Module configuring mapbox.
 *
 * See [Mapbox.js](https://www.mapbox.com/mapbox.js/api/v2.3.0) documentation
 * for more details:
 *
 * - [L.mapbox.config.FORCE_HTTPS](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-mapbox-config-force_https/)
 * - [L.mapbox.config.HTTP_URL](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-mapbox-config-http_url/)
 * - [L.mapbox.config.HTTPS_URL](https://www.mapbox.com/mapbox.js/api/v2.3.0/l-mapbox-config-https_url/)
 *
 * @alias Settings
 * @alias ext.kartographer.settings
 * @class Kartographer.Settings
 * @singleton
 */
( function ( $, mw ) {

	var mapServer = mw.config.get( 'wgKartographerMapServer' ),
		forceHttps = mapServer[ 4 ] === 's',
		config = L.mapbox.config;

	config.REQUIRE_ACCESS_TOKEN = false;
	config.FORCE_HTTPS = forceHttps;
	config.HTTP_URL = forceHttps ? false : mapServer;
	config.HTTPS_URL = !forceHttps ? false : mapServer;

}( jQuery, mediaWiki ) );
