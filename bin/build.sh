#!/usr/bin/env bash

set -e

node_modules/.bin/browserify lib/mapbox/mapbox-main.js -o lib/mapbox/mapbox-lib.js
cp -rv node_modules/mapbox.js/theme/* lib/mapbox/
rm lib/mapbox/images/render.sh

cp -v node_modules/leaflet-sleep/Leaflet.Sleep.js lib/leaflet.sleep.js

cp -v node_modules/prunecluster/dist/PruneCluster.js \
    node_modules/prunecluster/dist/LeafletStyleSheet.css \
    lib/prunecluster/

node_modules/.bin/rollup --config rollup.config.js
