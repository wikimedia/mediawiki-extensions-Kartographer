<?php

namespace Kartographer\Tests;

use Kartographer\ExternalDataLoader;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use Status;
use stdClass;

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
	  "title": "Neighbourhoods/New York City.map",
	  "url": "http://commons.test/w/api.php?format=json&formatversion=2&"
		"action=jsondata&title=Neighbourhoods%2FNew+York+City.map"
	}';

	public function provideTestGeoMaskData() {
		yield 'test with multi polygon' => [
			'input' => (object)[
				'type' => 'Feature',
				'features' => [
					(object)[
						'geometry' => (object)[
							'type' => 'MultiPolygon',
							'coordinates' => [
								[ 1, 2 ],
								[ 3, 4 ],
								[ 5, 6 ]
							]
						]
					]
				]
			],
			'expected' => (object)[
				'type' => 'Feature',
				'geometry' => [
					'type' => 'Polygon',
					'coordinates' => [
						[
							[ 3600, -180 ],
							[ 3600, 180 ],
							[ -3600, 180 ],
							[ -3600, -180 ]
						],
						1, 3, 5
					]
				]
			]
		];

		yield 'test with single polygon' => [
			'input' => (object)[
				'type' => 'Feature',
				'features' => [
					(object)[
						'geometry' => (object)[
							'type' => 'Polygon',
							'coordinates' => [
								[ 1, 2 ]
							]
						]
					]
				]
			],
			'expected' => (object)[
				'type' => 'Feature',
				'geometry' => [
					'type' => 'Polygon',
					'coordinates' => [
						[
							[ 3600, -180 ],
							[ 3600, 180 ],
							[ -3600, 180 ],
							[ -3600, -180 ]
						],
						[ 1, 2 ]
					]
				]
			]
		];

		yield 'test with no geometry' => [
			'input' => (object)[
				'type' => 'Feature',
				'features' => []
			],
			'expected' => (object)[
				'type' => 'Feature',
				'geometry' => [
					'type' => 'Polygon',
					'coordinates' => [
						[
							[ 3600, -180 ],
							[ 3600, 180 ],
							[ -3600, 180 ],
							[ -3600, -180 ]
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider provideTestGeoMaskData
	 */
	public function testGeoMaskData( stdClass $input, stdClass $expected ) {
		$fetcher = new ExternalDataLoader( $this->createMock( HttpRequestFactory::class ) );
		$this->assertEquals( $expected, $fetcher->handleMaskGeoData( $input ) );
	}

	public function provideTestParseData() {
		yield 'test with geomask' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'geomask'
			] ],
			'extendCount' => 1,
			'maskGeoDataCount' => 1
		];

		yield 'test with geoline' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'geoline'
			] ],
			'extendCount' => 1,
			'maskGeoDataCount' => 0
		];

		yield 'test with page' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'page'
			] ],
			'extendCount' => 1,
			'maskGeoDataCount' => 0
		];

		yield 'test with missing service' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
			] ],
			'extendCount' => 0,
			'maskGeoDataCount' => 0
		];

		yield 'test with missing type' => [
			'input' => [ (object)[
				'service' => 'geomask'
			] ],
			'extendCount' => 0,
			'maskGeoDataCount' => 0
		];
	}

	/**
	 * @dataProvider provideTestParseData
	 */
	public function testParse( array $input, $extendCount, $maskGeoDataCount ) {
		$fetcher = $this->getMockBuilder( ExternalDataLoader::class )
			->setConstructorArgs( [ $this->createMock( HttpRequestFactory::class ) ] )
			->onlyMethods( [ 'extend', 'handleMaskGeoData' ] )
			->getMock();

		$fetcher->expects( $this->exactly( $extendCount ) )
			->method( 'extend' )
			->will( $this->returnValue( $input[0] ) );

		$fetcher->expects( $this->exactly( $maskGeoDataCount ) )
			->method( 'handleMaskGeoData' );

		$fetcher->parse( $input );
	}

	public function testParseWithoutExternalData() {
		$geoJson = [ json_decode( self::WIKITEXT_JSON ) ];

		$fetcher = new ExternalDataLoader( $this->createMock( HttpRequestFactory::class ) );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::WIKITEXT_JSON ), $geoJson[0] );
	}

	public function testHttpRequestFails() {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( Status::newFatal( '' ) );
		$request->expects( $this->never() )
			->method( 'getContent' );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )
			->willReturn( $request );

		$geoJson = '{"type":"ExternalData","url":""}';
		$extendedGeoJson = [ json_decode( $geoJson ) ];
		( new ExternalDataLoader( $factory ) )->parse( $extendedGeoJson );

		$this->assertSame( $geoJson, json_encode( $extendedGeoJson[0] ) );
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
