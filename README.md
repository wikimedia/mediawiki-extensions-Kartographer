Kartographer is a MediaWiki extension that adds map capability
See https://www.mediawiki.org/wiki/Extension:Kartographer

You will need to run your own map tile server, or use one that is available to you.
See https://wiki.openstreetmap.org/wiki/Tile_servers

## Terminology

* DM – data model
* Dynamic as in "dynamic map frame" – scrollable, zoomable mini map in a wiki article, realized with Leaflet
* [GeoData](https://www.mediawiki.org/wiki/Extension:GeoData) – MediaWiki extension that provides the information for the nearby feature
* [GeoJSON](https://datatracker.ietf.org/doc/html/rfc7946) – standard format to describe features on a map, e.g. points and shapes
* Kartographer – MediaWiki extension for displaying prerendered and dynamic maps
* Kartotherian – tile server as well as backend rendering service required for Kartographer
* [Leaflet](https://leafletjs.com/) – JavaScript library for interactive maps
* [Maki](https://labs.mapbox.com/maki-icons/) – icon set used for the markers on a map
* Marker – a location on a map marked with a pin and/or an icon
* [MapBox](https://github.com/mapbox) – collection of JavaScript libraries on top of Leaflet, as well as the name of the company that created them
* MW – MediaWiki
* Nearby – in this context a feature that provides links to other articles on the same wiki that have coordinates close to a given location
* OSM – [OpenStreetMap](https://www.openstreetmap.org/), data source for the base layer of all maps
* [Simplestyle](https://github.com/mapbox/simplestyle-spec) – extension to the GeoJSON standard
* Static as in "static map frame" – the static .png rendering of a `<mapframe>` tag; note this is always present, but overlayed with a dynamic map frame, depending on the configuration
* Tile – see [en:Tiled web map](https://en.wikipedia.org/wiki/Tiled_web_map)
* [TopoJSON](https://github.com/topojson/topojson) – extension to the GeoJSON standard that encodes topology
* VE – VisualEditor
* [Wikivoyage](https://www.wikivoyage.org/) – primary user of dynamic maps and the nearby feature on the Wikimedia cluster
* WV – Wikivoyage

## Developer info

Many parts of the JavaScript that builds the dynamic maps and provides the GeoJSON might be used by tools and Gadgets
outside of the Wikimedia extension realm so dependencies and requirements on this interface may be hard to discover. Be
extra careful changing these without notice if they're not explicitly marked private and especially if they are marked
public.
