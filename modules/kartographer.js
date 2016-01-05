( function ( $, mw ) {

	// Load this script after lib/mapbox-lib.js

	var mapServer = mw.config.get( 'wgKartographerMapServer'),
		forceHttps = mapServer[4] === 's',
		config = L.mapbox.config;

	config.REQUIRE_ACCESS_TOKEN = false;
	config.FORCE_HTTPS = forceHttps;
	config.HTTP_URL = forceHttps ? false : mapServer;
	config.HTTPS_URL = !forceHttps ? false : mapServer;

	function bracketDevicePixelRatio() {
		var brackets = mw.config.get( 'wgKartographerSrcsetScales' ),
			baseRatio = window.devicePixelRatio || 1;
		if (!brackets) {
			return 1;
		}
		brackets.unshift(1);
		for (var i = 0; i < brackets.length; i++) {
			var scale = brackets[i];
			if (scale >= baseRatio || (baseRatio - scale) < 0.1) {
				return scale;
			}
		}
		return brackets[brackets.length - 1];
	}

	mw.hook( 'wikipage.content' ).add( function ( $content ) {

		var scale = bracketDevicePixelRatio();
		scale = (scale === 1) ? '' : ('@' + scale + 'x');
		var urlFmt = '/{z}/{x}/{y}' + scale + '.png';
		var mapData = mw.config.get( 'wgKartographerLiveData' ) || {};

		$content.find('.mw-kartographer-live').each(function () {
			var $this = $(this),
				style = $this.data('style'),
				zoom = $this.data('zoom'),
				lat = $this.data('lat'),
				lon = $this.data('lon'),
				overlays = $this.data('overlays'),
				map = L.map(this).setView([lat, lon], zoom);
			map.attributionControl.setPrefix('');
			L.tileLayer(mapServer + '/' + style + urlFmt, {
				maxZoom: 18,
				attribution: 'Wikimedia maps beta | Map data &copy; <a href="http://openstreetmap.org/copyright">OpenStreetMap contributors</a>'
			}).addTo(map);

			if (overlays) {
				var geoJson = [];
				$.each(overlays, function(_, group) {
					if (group === '*') {
						$.each(mapData, function (k, d) {
							if (k[0] !== '_') {
								geoJson = geoJson.concat(d);
							}
						});
					} else if (mapData.hasOwnProperty(group)) {
						geoJson = geoJson.concat(mapData[group]);
					}
				});
				var dataLayer = L.mapbox.featureLayer().addTo(map);
				dataLayer.setGeoJSON(geoJson);
			}
		});
	} );
}( jQuery, mediaWiki ) );
