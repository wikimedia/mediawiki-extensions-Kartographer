/**
 * Module configuring mapbox.
 *
 * See [Mapbox.js](https://www.mapbox.com/mapbox.js/api/v3.3.1) documentation
 * for more details:
 *
 * - [L.mapbox.config.FORCE_HTTPS](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-mapbox-config-force_https/)
 * - [L.mapbox.config.HTTP_URL](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-mapbox-config-http_url/)
 * - [L.mapbox.config.HTTPS_URL](https://www.mapbox.com/mapbox.js/api/v3.3.1/l-mapbox-config-https_url/)
 *
 * @borrows Kartographer.Settings as Settings
 * @borrows Kartographer.Settings as ext.kartographer.settings
 * @class Kartographer.Settings
 * @singleton
 */
module.exports = {
	configure: function () {
		const mapServer = mw.config.get( 'wgKartographerMapServer' ),
			forceHttps = mapServer && mapServer[ 4 ] === 's',
			config = L.mapbox.config;

		config.REQUIRE_ACCESS_TOKEN = false;
		config.FORCE_HTTPS = forceHttps;
		config.HTTP_URL = forceHttps ? false : mapServer;
		config.HTTPS_URL = !forceHttps ? false : mapServer;

		// Disable hosted marker functionality
		if ( !mw.config.get( 'wgKartographerSimpleStyleMarkers' ) ) {
			L.Icon.Default.imagePath = mw.config.get( 'wgExtensionAssetsPath' ) + '/Kartographer/lib/external/mapbox/images/';
			L.icon = function () {
				return new L.Icon.Default();
			};
		}
	}
};
