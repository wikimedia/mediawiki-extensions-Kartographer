<?php

namespace Kartographer\Tests;

use Kartographer\ExternalDataLoader;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @group Kartographer
 * @covers \Kartographer\ExternalDataLoader
 * @license MIT
 */
class ExternalDataLoaderTest extends MediaWikiUnitTestCase {

	private const WIKITEXT_JSON = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122, 37]
    },
    "properties": {
      "title": "Test",
      "description": "[[Link to nowhere]]",
      "marker-symbol": "-number"
    }
  }';

	private const JSON_EXTERNAL_LINK = '{
	  "type": "ExternalData",
	  "service": "geopoint",
	  "url": "https://maps.wikimedia.org/geopoint?getgeojson=1&query=' .
		'SELECT+DISTINCT+%3Fid+%3Fgeo+%28IF%28%3Ftype+' .
		'%3D+wd%3AQ33506%2C+%27museum%27%2C+%27%27%29+AS+%3F' .
		'marker_symbol%29+WHERE+%7B+%3Fid+wdt%3AP17+wd%3AQ235%3B+' .
		'wdt%3AP31+%3Ftype%3B+wdt%3AP625+%3Fgeo.%7D+LIMIT+3",
	  "properties": {
		"marker-size": "large",
		"marker-symbol": "circle",
		"marker-color": "#FF5733"
	  }
	}';

	private const JSON_EXTERNAL_RETURN = '{
	  "type": "FeatureCollection",
	  "features": [
		{
		  "type": "Feature",
		  "id": "Q3685392",
		  "properties": {
			"marker-symbol": "museum"
		  },
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.41974,
			  43.7312
			]
		  }
		},
		{
		  "type": "Feature",
		  "id": "Q3886628",
		  "properties": {
			"marker-symbol": "",
			"marker-color": "#FF5703"
		  },
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.41043,
			  43.73
			]
		  }
		},
		{
		  "type": "Feature",
		  "id": "Q5150059",
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.42,
			  43.72722222
			]
		  }
		}
	  ]
	}';

	private const JSON_EXTENDED = '{
	  "type": "FeatureCollection",
	  "service": "geopoint",
	  "url": "https://maps.wikimedia.org/geopoint?getgeojson=1&query=' .
		'SELECT+DISTINCT+%3Fid+%3Fgeo+%28IF%28%3Ftype+' .
		'%3D+wd%3AQ33506%2C+%27museum%27%2C+%27%27%29+AS+%3F' .
		'marker_symbol%29+WHERE+%7B+%3Fid+wdt%3AP17+wd%3AQ235%3B+' .
		'wdt%3AP31+%3Ftype%3B+wdt%3AP625+%3Fgeo.%7D+LIMIT+3",
	 "properties": {
		"marker-size": "large",
		"marker-symbol": "circle",
		"marker-color": "#FF5733"
	  },
	  "features": [
		{
		  "type": "Feature",
		  "id": "Q3685392",
		  "properties": {
			"marker-size": "large",
			"marker-symbol": "museum",
			"marker-color": "#FF5733"
		  },
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.41974,
			  43.7312
			]
		  }
		},
		{
		  "type": "Feature",
		  "id": "Q3886628",
		  "properties": {
			"marker-size": "large",
			"marker-symbol": "",
			"marker-color": "#FF5703"
		  },
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.41043,
			  43.73
			]
		  }
		},
		{
		  "type": "Feature",
		  "id": "Q5150059",
		  "properties": {
			"marker-size": "large",
			"marker-symbol": "circle",
			"marker-color": "#FF5733"
		  },
		  "geometry": {
			"type": "Point",
			"coordinates": [
			  7.42,
			  43.72722222
			]
		  }
		}
	  ]
	}';

	private const JSON_EXTERNAL_DATA_PAGE = '{
	  "type": "ExternalData",
	  "service": "page",
	  "title": "Neighbourhoods/New York City.map"
	}';

	public function testExternalDataPage() {
		$geoJson = [ json_decode( self::JSON_EXTERNAL_DATA_PAGE ) ];

		$fetcher = new ExternalDataLoader( $this->createMock( HttpRequestFactory::class ) );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::JSON_EXTERNAL_DATA_PAGE ), $geoJson[0] );
	}

	public function testParseWithoutExternalData() {
		$geoJson = [ json_decode( self::WIKITEXT_JSON ) ];

		$fetcher = new ExternalDataLoader( $this->createMock( HttpRequestFactory::class ) );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::WIKITEXT_JSON ), $geoJson[0] );
	}

	public function testParseWithExternalData() {
		$geoJson = [ json_decode( self::JSON_EXTERNAL_LINK ) ];

		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( \Status::newGood() );
		$request->method( 'getContent' )
			->willReturn( self::JSON_EXTERNAL_RETURN );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )
			->willReturn( $request );

		$fetcher = new ExternalDataLoader( $factory );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::JSON_EXTENDED ), $geoJson[0] );
	}

}
