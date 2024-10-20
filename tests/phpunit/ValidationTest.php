<?php

namespace Kartographer\Tests;

use Kartographer\SimpleStyleParser;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use StatusValue;

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

			public function normalizeAndSanitize( &$data ): StatusValue {
				return StatusValue::newGood();
			}
		};

		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}

		$result = $validator->parse( $content );

		if ( $shouldFail ) {
			$this->assertStatusNotGood( $result );
		} else {
			$this->assertStatusGood( $result );
		}
	}

	public static function provideTestCases() {
		foreach ( glob( __DIR__ . '/data/good-schemas/*.json' ) as $file ) {
			yield basename( $file ) => [ $file, false ];
		}
		foreach ( glob( __DIR__ . '/data/bad-schemas/*.json' ) as $file ) {
			yield basename( $file ) => [ $file, true ];
		}
	}
}
