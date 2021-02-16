# Use mapbox-gl-style specification for client-side rendering
As part of the work to [modernize the vector-tile infrastructure](https://phabricator.wikimedia.org/T263854) supported by the Product Infrastructure team, the Kartographer client should be able to render vector-tiles client-side with a well-established open-source technology.
 
## Author
Mateus Santos (msantos@wikimedia.org)

## Status
Accepted

## Context
In order to decrease maintenance overhead in the maps infrastructure, the tile server will receive improvements in the vector-tile generation using Tegola. Although Kartotherian already have a vector-tile endpoint, now there is some effort to evolve the platform as a whole, and it includes client-side rendering, that will take advantage of future capabilities supported by Tegola.

The client-side render process will provide decoupling of some server-side components and will make it possible to replace mapnik, which is a maintenance overhead.

After analyzing all the open-source options in the market, we have reached the decision of using the mapbox-gl-style specification as the next-level in terms of maps rendering. The reason is because it can be easily integrated with the top client-side maps frameworks: maplibre-gl, Leaflet, and OpenLayers.

Because of its flexibility and support across frameworks, it gives us freedom to experiment different engines in the early stages of this work.

## Decision
Accepted (02-16-2021)

## Consequences
Tangram WebGL renderer is not going to be supported and migrating to it will require efforts on rewriting the style to tangram spec.
