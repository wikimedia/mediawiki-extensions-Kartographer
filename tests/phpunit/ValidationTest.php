<?php

namespace Kartographer\Tests;

use Kartographer\SimpleStyleParser;
use MediaWikiIntegrationTestCase;
use Parser;
use ParserOptions;
use Status;
use Title;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 * @license MIT
 */
class ValidationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideTestCases
	 * phpcs:disable Squiz.WhiteSpace.FunctionSpacing.BeforeFirst
	 */
	public function testValidation( string $file, bool $shouldFail ) {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$options = ParserOptions::newFromAnon();
		$title = Title::newMainPage();
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$validator = new class extends SimpleStyleParser {
			public function __construct() {
			}

			public function normalizeAndSanitize( &$data ): Status {
				return Status::newGood();
			}
		};

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
