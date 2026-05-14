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

## Tile Server Configuration

Kartographer requires a raster tile server to render maps, configured via the `$wgKartographerMapServer` variable.

**Wikimedia tile server** (default, for local use and for Wikimedia projects):
```php
$wgKartographerMapServer = 'https://maps.wikimedia.org';
$wgKartographerDfltStyle = 'osm-intl';
```

**OpenStreetMap public tile server** (publicly available):
```php
$wgKartographerMapServer = 'https://tile.openstreetmap.org';
$wgKartographerDfltStyle = '';
```

You can also include this config via `config/MapServer/openstreetmap.php` and then include that file in your `LocalSettings.php`:

```php
require_once "$IP/extensions/Kartographer/config/MapServer/openstreetmap.php";
```

More tile server options can be found at https://wiki.openstreetmap.org/wiki/Raster_tile_providers

## Developer info

Many parts of the JavaScript that builds the dynamic maps and provides the GeoJSON might be used by tools and Gadgets
outside of the Wikimedia extension realm so dependencies and requirements on this interface may be hard to discover. Be
extra careful changing these without notice if they're not explicitly marked private and especially if they are marked
public.

### Seed a development mapframe page

To create (or reset) a development page with sample mapframe content, run the `Kartographer:seedMapframePage` maintenance script.

This script ensures the page `Extension/Kartographer/Mapframe` exists and has this content:

`<mapframe text="[[wikipedia:Stockholm|Stockholm]] in Wikipedia" width=250 height=250 zoom=7 latitude="59" longitude="18" />`
