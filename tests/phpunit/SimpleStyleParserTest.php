<?php

namespace Kartographer\Tests;

use Kartographer\MediaWikiWikitextParser;
use Kartographer\SimpleStyleParser;
use Kartographer\WikitextParser;
use LogicException;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use Title;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 */
class SimpleStyleParserTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		$this->setMwGlobals( 'wgKartographerMapServer', 'https://maps.wikimedia.org' );
	}

	/**
	 * @dataProvider provideExternalData
	 */
	public function testExternalData( string $expected, string $input, string $message ) {
		$expected = json_decode( $expected );

		$options = ParserOptions::newFromAnon();
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$title = Title::newFromText( 'Test' );
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$ssp = new SimpleStyleParser( new MediaWikiWikitextParser( $parser ) );

		$status = $ssp->parse( $input );

		$this->assertTrue( $status->isOK(),
			"Parse is expected to succeed, but encountered '{$status->getMessage()->text()}'"
		);
		$this->assertEquals( $expected, $status->getValue(), $message );
	}

	public function provideExternalData() {
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
	 */
	public function testNormalizeAndSanitize( string $json, string $expected = null ) {
		$parser = $this->createMock( WikitextParser::class );
		$parser->method( 'parseWikitext' )->willReturn( 'HTML' );
		$ssp = new SimpleStyleParser( $parser );
		$data = json_decode( $json );

		if ( $expected && !str_starts_with( $expected, '{' ) && class_exists( $expected ) ) {
			$this->expectException( $expected );
		}

		$status = $ssp->normalizeAndSanitize( $data );

		$this->assertTrue( $status->isOK() );
		$this->assertEquals( json_decode( $expected ?? $json ), $data );
	}

	public function provideDataToNormalizeAndSanitize() {
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
			// TODO: Cover "service": "page" as well

			// Test cases specifically for SimpleStyleParser::sanitize()
			[
				'{ "_a": "…", "b": { "_c": "…", "d": "…" } }',
				'{ "b": { "d": "…" } }',
			],
			[
				'{ "properties": { "title": "…", "description": {} } }',
				'{ "properties": { "title": "HTML" } }',
			],
			[
				'{ "properties": { "title": { "en": "…", "de": null } } }',
				'{ "properties": { "title": { "en": "HTML" } } }',
			],
			// TODO: Cover special cases with the "saveUnparsed" option set
		];
	}

}
