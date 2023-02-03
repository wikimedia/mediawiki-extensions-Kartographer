<?php

namespace Kartographer\Tests;

use Kartographer\ExternalDataLoader;
use Kartographer\Tag\ParserFunctionTracker;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use Status;
use stdClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Kartographer
 * @covers \Kartographer\ExternalDataLoader
 * @license MIT
 */
class ExternalDataLoaderTest extends MediaWikiUnitTestCase {

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
		/** @var ExternalDataLoader $fetcher */
		$fetcher = TestingAccessWrapper::newFromObject( $fetcher );
		$this->assertEquals( $expected, $fetcher->handleMaskGeoData( $input ) );
	}

	public function testGeoMaskWhenHttpRequestFails() {
		$geoJson = json_decode( '[{"type":"ExternalData","service":"geomask","url":"…"}]' );

		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( Status::newFatal( '' ) );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )
			->willReturn( $request );

		$fetcher = new ExternalDataLoader( $factory );
		$fetcher->parse( $geoJson );

		$this->assertSame( 'ExternalData', $geoJson[0]->type );
	}

	public function provideTestParseData() {
		yield 'test with geomask' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'geomask',
				'url' => '…',
				'features' => []
			] ],
			'maskGeoDataCount' => 1
		];

		yield 'test with geoline' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'geoline',
				'url' => '…'
			] ],
			'maskGeoDataCount' => 0
		];

		yield 'test with page' => [
			'input' => [ (object)[
				'type' => 'ExternalData',
				'service' => 'page',
				'url' => '…'
			] ],
			'maskGeoDataCount' => 0
		];
	}

	/**
	 * @dataProvider provideTestParseData
	 */
	public function testParse( array $input, $maskGeoDataCount ) {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( Status::newGood() );
		$request->method( 'getContent' )
			->willReturn( '{"features":[]}' );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $request );

		$fetcher = new ExternalDataLoader( $factory );
		$fetcher->parse( $input );
		$this->assertSame(
			$maskGeoDataCount ? 'Feature' : 'ExternalData',
			$input[0]->type ?? 'ExternalData'
		);
	}

	public function provideGeoJsonWithoutExternalData() {
		return [
			'missing type' => [ '{ "service": "", "url": "…" }' ],
			'missing service' => [ '{ "type": "ExternalData", "url": "…" }' ],
			'missing url' => [ '{ "type": "ExternalData", "service": "" }' ],
			'wrong type' => [ '{ "type": "Feature", "service": "", "url": "…" }' ],
			'empty url' => [ '{ "type": "ExternalData", "service": "", "url": "" }' ],
		];
	}

	/**
	 * @dataProvider provideGeoJsonWithoutExternalData
	 */
	public function testParseWithoutExternalData( string $input ) {
		$geoJson = [ json_decode( $input ) ];

		$requestFactory = $this->createMock( HttpRequestFactory::class );
		$requestFactory->expects( $this->never() )
			->method( 'create' );

		$tracker = $this->createMock( ParserFunctionTracker::class );
		$tracker->expects( $this->never() )
			->method( 'incrementExpensiveFunctionCount' );

		$fetcher = new ExternalDataLoader( $requestFactory, $tracker );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( $input ), $geoJson[0] );
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

		$tracker = $this->createMock( ParserFunctionTracker::class );
		$tracker->expects( $this->once() )
			->method( 'incrementExpensiveFunctionCount' )
			->willReturn( true );

		$fetcher = new ExternalDataLoader( $factory, $tracker );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::JSON_EXTENDED ), $geoJson[0] );
	}

	public function testJsonDataApiIntegration() {
		$geoJson = [ json_decode( '{ "type": "ExternalData", "service": "page", "url": "…", "properties": {} }' ) ];

		$request = $this->createMock( \MWHttpRequest::class );
		$request->method( 'execute' )
			->willReturn( Status::newGood() );
		$request->method( 'getContent' )
			->willReturn( '{ "jsondata": { "data": { "type": "FeatureCollection", "features": [ {} ] } } }' );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )
			->willReturn( $request );

		$fetcher = new ExternalDataLoader( $factory );
		$fetcher->parse( $geoJson );

		// Make sure the JSON returned by the jsondata API is resolved and not used as is
		$this->assertObjectNotHasAttribute( 'jsondata', $geoJson[0] );
		$this->assertSame( 'FeatureCollection', $geoJson[0]->type );
		// Merging properties back into the "page" GeoJSON is currently not supported
		$this->assertObjectNotHasAttribute( 'properties', $geoJson[0]->features[0] );
	}

	public function testExpensiveFunctionCountReached() {
		$geoJson = [ json_decode( self::JSON_EXTERNAL_LINK ) ];

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->expects( $this->never() )
			->method( 'create' );

		$tracker = $this->createMock( ParserFunctionTracker::class );
		$tracker->expects( $this->once() )
			->method( 'incrementExpensiveFunctionCount' )
			->willReturn( false );

		$fetcher = new ExternalDataLoader( $factory, $tracker );
		$fetcher->parse( $geoJson );

		$this->assertEquals( json_decode( self::JSON_EXTERNAL_LINK ), $geoJson[0] );
	}

}
