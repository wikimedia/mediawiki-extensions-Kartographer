<?php

namespace Kartographer\Tests;

use Kartographer\MediaWikiWikitextParser;
use Kartographer\Tests\Mock\MockSimpleStyleParser;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use Title;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 */
class ValidationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideTestCases
	 */
	public function testValidation( $file, $shouldFail ) {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$options = ParserOptions::newFromAnon();
		$title = Title::newMainPage();
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$validator = new MockSimpleStyleParser( new MediaWikiWikitextParser( $parser ) );

		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}

		$result = $validator->parse( $content );

		if ( $shouldFail ) {
			$this->assertFalse( $result->isGood(), 'Validation unexpectedly succeeded' );
		} else {
			$this->assertTrue( $result->isGood(), 'Validation failed' );
		}
	}

	public function provideTestCases() {
		foreach ( glob( __DIR__ . '/data/good-schemas/*.json' ) as $file ) {
			yield basename( $file ) => [ $file, false ];
		}
		foreach ( glob( __DIR__ . '/data/bad-schemas/*.json' ) as $file ) {
			yield basename( $file ) => [ $file, true ];
		}
	}
}
