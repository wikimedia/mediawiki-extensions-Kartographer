<?php

namespace Kartographer\Tests;

use Kartographer\SimpleStyleParser;
use MediaWikiTestCase;
use Parser;
use ParserOptions;
use Title;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 */
class SimpleStyleParserTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideExternalData
	 * @param string $expected
	 * @param string $input
	 * @param string $message
	 */
	public function testExternalData( $expected, $input, $message = '' ) {
		$expected = json_decode( $expected );

		$options = new ParserOptions();
		$parser = new Parser();
		$title = Title::newFromText( 'Test' );
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$ssp = new SimpleStyleParser( $parser );

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
}
