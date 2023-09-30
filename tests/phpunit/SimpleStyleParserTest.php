<?php

namespace Kartographer\Tests;

use JsonConfig\JCMapDataContent;
use Kartographer\SimpleStyleParser;
use Kartographer\WikitextParser;
use LogicException;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 * @license MIT
 */
class SimpleStyleParserTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		$this->overrideConfigValues( [
			'JsonConfigModels' => [ 'Map.JsonConfig' => [ 'class' => JCMapDataContent::class ] ],
			'JsonConfigs' => [ 'Map.JsonConfig' => [ 'namespace' => 486, 'nsName' => 'Data' ] ],
			'KartographerMapServer' => 'https://maps.wikimedia.org',
			MainConfigNames::LanguageCode => 'qqx',
			MainConfigNames::ScriptPath => '',
		] );
	}

	/**
	 * @dataProvider provideExternalData
	 */
	public function testExternalData( string $expected, string $input, string $message ) {
		$expected = json_decode( $expected );

		$options = ParserOptions::newFromAnon();
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$title = Title::newFromText( 'Test' );
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$ssp = SimpleStyleParser::newFromParser( $parser );

		$status = $ssp->parse( $input );

		$this->assertStatusGood( $status );
		$this->assertEquals( $expected, $status->getValue()['data'], $message );
	}

	public static function provideExternalData() {
		return [
			[
				'[
					{
						"type": "ExternalData",
						"url": "https://maps.wikimedia.org/geoshape?getgeojson=1&ids=Q1%2CQ2&query=FOO",
						"service": "geoshape"
					},
					{
						"type": "ExternalData",
						"url": "https://maps.wikimedia.org/geoline?getgeojson=1&query=bar",
						"service": "geoline",
						"properties": {
							"text": "foo"
						}
					}
				]',
				'[
					{
						"type": "ExternalData",
						"service": "geoshape",
						"ids": [ "Q1", "Q2" ],
						"query": "FOO",
						"some": "thing"
					},
					{
						"type": "ExternalData",
						"service": "geoline",
						"query": "bar",
						"properties": {
							"text": "foo"
						}
					}
				]',
				'Test a couple objects in the same JSON blob'
			],
			[
				'[
					{
						"type": "ExternalData",
						"service": "geoshape",
						"url": "https://maps.wikimedia.org/geoshape?getgeojson=1&query=test",
						"properties": {}
					}
				]',
				'{
					"type": "ExternalData",
					"service": "geoshape",
					"query": "test",
					"url": "http://some/bad/site",
					"properties": {
						"__proto__": "nasty stuff"
					},
					"__proto__": "stop me somebody!"
				}',
				"Make sure 'url' field or '__proto__' can't get through"
			],
		];
	}

	/**
	 * @dataProvider provideDataToNormalizeAndSanitize
	 * phpcs:disable Squiz.WhiteSpace.FunctionSpacing.BeforeFirst
	 */
	public function testNormalizeAndSanitize(
		string $json,
		string $expected = null,
		string $expectedError = null,
		string $option = null
	) {
		$parser = $this->createMock( WikitextParser::class );
		$parser->method( 'parseWikitext' )
			->willReturn( 'HTML' );
		$ssp = new SimpleStyleParser( $parser, [ $option => true ] );
		$data = json_decode( $json );

		if ( $expected && ctype_alpha( $expected ) && class_exists( $expected ) ) {
			$this->expectException( $expected );
		}

		$status = $ssp->normalizeAndSanitize( $data );

		if ( $expectedError ) {
			$this->assertStatusError( $expectedError, $status );
		} else {
			$this->assertStatusGood( $status );
		}
		$this->assertEquals( json_decode( $expected ?? $json ), $data );
		$this->assertSame( $data, $status->getValue()['data'] );
	}

	public static function provideDataToNormalizeAndSanitize() {
		return [
			[ 'null' ],
			[ '[]' ],
			[ '{}' ],
			[ '{ "type": "…" }' ],
			[ '{ "type": "ExternalData" }', LogicException::class ],
			[ '{ "type": "ExternalData", "service": "…" }', LogicException::class ],
			[
				'{
					"type": "ExternalData",
					"service": "geoshape"
				}',
				'{
					"type": "ExternalData",
					"service": "geoshape",
					"url": "https://maps.wikimedia.org/geoshape?getgeojson=1"
				}',
			],
			[
				'{
					"type": "ExternalData",
					"service": "geoshape",
					"ids": "Q1, Q2",
					"query": "foo",
					"properties": "bar"
				}',
				'{
					"type": "ExternalData",
					"service": "geoshape",
					"url": "https://maps.wikimedia.org/geoshape?getgeojson=1&ids=Q1%2CQ2&query=foo",
					"properties": "bar"
				}',
			],
			[
				'{
					"type": "ExternalData",
					"service": "geomask",
					"ids": [ "Q1", "Q2" ]
				}',
				'{
					"type": "ExternalData",
					"service": "geomask",
					"url": "https://maps.wikimedia.org/geoshape?getgeojson=1&ids=Q1%2CQ2"
				}',
			],
			[
				'{
					"type": "ExternalData",
					"service": "page",
					"title": "Data:Germany.map"
				}',
				'{
					"type": "ExternalData",
					"service": "page",
					"url": "/api.php?format=json&formatversion=2&action=jsondata&title=Data%3AGermany.map"
				}',
			],
			[
				'[ {
					"type": "ExternalData",
					"service": "page",
					"title": ""
				} ]',
				null,
				'kartographer-error-title',
			],

			// Test cases specifically for SimpleStyleParser::sanitize()
			[
				'{ "": "…" }',
				'{ "": "…" }',
			],
			[
				'{ "_a": "…", "b": { "_c": "…", "d": "…" } }',
				'{ "b": { "d": "…" } }',
			],
			[
				'{ "properties": { "title": { "en": null }, "description": "" } }',
				'{ "properties": {} }',
			],
			[
				'{ "properties": { "title": "…", "description": {} } }',
				'{ "properties": { "title": "HTML" } }',
			],
			[
				'{ "properties": { "title": { "en": "…", "de": null, "fr": "" } } }',
				'{ "properties": { "title": { "en": "HTML" } } }',
			],
			[
				'{ "properties": { "title": "…" } }',
				'{ "properties": { "title": "HTML", "_origtitle": "…" } }',
				null,
				'saveUnparsed'
			],
			[
				'{ "properties": { "title": { "en": "…", "de": null } } }',
				'{ "properties": { "title": { "en": "HTML" }, "_origtitle": { "en": "…" } } }',
				null,
				'saveUnparsed'
			],
		];
	}

	/**
	 * @dataProvider provideDataWithMarkerSymbolCounters
	 */
	public function testUpdateMarkerSymbolCounters(
		string $data,
		string $expectedData,
		string $expectedFirstMarker = null
	) {
		$data = json_decode( $data );
		$firstMarker = SimpleStyleParser::updateMarkerSymbolCounters( $data );
		$this->assertEquals( json_decode( $expectedData ), $data );
		if ( $expectedFirstMarker ) {
			$this->assertSame( $expectedFirstMarker, $firstMarker[0] );
			$this->assertIsObject( $firstMarker[1] );
		} else {
			$this->assertNull( $firstMarker );
		}
	}

	/**
	 * @dataProvider provideDataWithoutMarkerSymbolCounters
	 */
	public function testFindMarkerSymbolCounters(
		string $data,
		string $expectedFirstMarker = null
	) {
		$expectedData = json_decode( $data );
		$data = json_decode( $data );
		$firstMarker = SimpleStyleParser::findFirstMarkerSymbol( $data );
		$this->assertEquals( $expectedData, $data );
		if ( $expectedFirstMarker ) {
			$this->assertSame( $expectedFirstMarker, $firstMarker[0] );
			$this->assertIsObject( $firstMarker[1] );
		} else {
			$this->assertNull( $firstMarker );
		}
	}

	public static function provideDataWithMarkerSymbolCounters() {
		return [
			'bad data' => [ '[ null ]', '[ null ]' ],
			'empty data' => [ '[ {} ]', '[ {} ]' ],
			'number' => [
				'[
					{ "properties": { "marker-symbol": "-number" } },
					{ "properties": { "marker-symbol": "-numberDifferent" } },
					{ "properties": { "marker-symbol": "-number" } }
				]',
				'[
					{ "properties": { "marker-symbol": "1" } },
					{ "properties": { "marker-symbol": "1" } },
					{ "properties": { "marker-symbol": "2" } }
				]',
				'1'
			],
			'letter' => [
				'[
					{ "properties": { "marker-symbol": "-letter" } },
					{ "properties": { "marker-symbol": "-letterDifferent" } },
					{ "properties": { "marker-symbol": "-letter" } }
				]',
				'[
					{ "properties": { "marker-symbol": "a" } },
					{ "properties": { "marker-symbol": "a" } },
					{ "properties": { "marker-symbol": "b" } }
				]',
				'A'
			],
			'recursing into FeatureCollection' => [
				'[ { "type": "FeatureCollection", "features": [
					{ "properties": { "marker-symbol": "-number" } }
				] } ]',
				'[ { "type": "FeatureCollection", "features": [
					{ "properties": { "marker-symbol": "1" } }
				] } ]',
				'1'
			],
			'recursing into GeometryCollection' => [
				'[ { "type": "GeometryCollection", "geometries": [
					{ "properties": { "marker-symbol": "-number" } }
				] } ]',
				'[ { "type": "GeometryCollection", "geometries": [
					{ "properties": { "marker-symbol": "1" } }
				] } ]',
				'1'
			],
		];
	}

	public static function provideDataWithoutMarkerSymbolCounters() {
		return [
			'bad data' => [ '[ null ]' ],
			'empty data' => [ '[ {} ]' ],
			'number' => [
				'[
					{ "properties": { "marker-symbol": "-number-monument" } },
					{ "properties": { "marker-symbol": "-number-park" } }
				]',
				'-number-monument'
			],
			'letter' => [
				'[
					{ "properties": { "marker-symbol": "-letter-monument" } },
					{ "properties": { "marker-symbol": "-letter-park" } }
				]',
				'-letter-monument'
			],
			'recursing into FeatureCollection' => [
				'[ { "type": "FeatureCollection", "features": [
					{ "properties": { "marker-symbol": "-number" } }
				] } ]',
				'-number'
			],
			'recursing into GeometryCollection' => [
				'[ { "type": "GeometryCollection", "geometries": [
					{ "properties": { "marker-symbol": "-number" } }
				] } ]',
				'-number'
			],
		];
	}

	public function testParseEmptyObjectsAsObjects() {
		$ssp = new SimpleStyleParser( $this->createMock( WikitextParser::class ) );
		$status = $ssp->parse( '[ {
			"type": "ExternalData",
			"service": "geoshape",
			"query": "",
			"properties": {}
		} ]' );

		$geoJson = $status->getValue()['data'][0];
		$this->assertIsObject( $geoJson->properties );
		$this->assertSame( [], (array)$geoJson->properties );
	}

}
