#!/usr/bin/env bash

set -e

node_modules/.bin/browserify lib/external/mapbox/mapbox-main.js -o lib/external/mapbox/mapbox-lib.js
cp -rv node_modules/mapbox.js/theme/* lib/external/mapbox/
rm lib/external/mapbox/images/render.sh

cp -v node_modules/leaflet-sleep/Leaflet.Sleep.js lib/external/leaflet.sleep.js
cp -v node_modules/wmui-base/wmui-base.less lib/external/wmui-base.less

cp -v node_modules/prunecluster/dist/PruneCluster.js \
    node_modules/prunecluster/dist/LeafletStyleSheet.css \
    lib/external/prunecluster/

node_modules/.bin/rollup --config rollup.config.js
