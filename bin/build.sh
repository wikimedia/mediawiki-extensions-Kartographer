#!/usr/bin/env bash

set -e

node_modules/.bin/browserify lib/external/mapbox/mapbox-main.js -o lib/external/mapbox/mapbox-lib.js
node_modules/.bin/replace-in-files --regex='[\r]' --replacement='' lib/external/mapbox/mapbox-lib.js
cp -rv node_modules/mapbox.js/dist/images/images lib/external/mapbox
cp -rv node_modules/mapbox.js/dist/mapbox.css lib/external/mapbox/mapbox.css
rm lib/external/mapbox/images/render.sh

cp -v node_modules/@wikimedia/leaflet-sleep/Leaflet.Sleep.js lib/external/leaflet.sleep.js

cp -v node_modules/leaflet.markercluster/dist/leaflet.markercluster-src.js \
	node_modules/leaflet.markercluster/dist/MarkerCluster.css \
	node_modules/leaflet.markercluster/dist/MarkerCluster.Default.css \
	lib/external/leaflet.markercluster/

node_modules/.bin/rollup --config rollup.config.js
