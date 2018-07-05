#!/usr/bin/env bash

set -e

node_modules/.bin/browserify lib/external/mapbox/mapbox-main.js -o lib/external/mapbox/mapbox-lib.js
cp -rv node_modules/mapbox.js/theme/* lib/external/mapbox/
rm lib/external/mapbox/images/render.sh

cp -v node_modules/@wikimedia/leaflet-sleep/Leaflet.Sleep.js lib/external/leaflet.sleep.js
cp -v node_modules/wikimedia-ui-base/wikimedia-ui-base.less lib/external/wikimedia-ui-base.less

cp -v node_modules/prunecluster/dist/PruneCluster.js \
    node_modules/prunecluster/dist/LeafletStyleSheet.css \
    lib/external/prunecluster/

node_modules/.bin/rollup --config rollup.config.js
