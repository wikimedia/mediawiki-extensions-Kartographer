<?php

namespace Kartographer\Tests;

use Kartographer\SimpleStyleParser;
use MediaWikiTestCase;
use Parser;
use ParserOptions;
use Status;
use Title;

/**
 * @covers \Kartographer\SimpleStyleParser
 * @group Kartographer
 */
class ValidationTest extends MediaWikiTestCase {
	private $basePath;

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->basePath = __DIR__ . '/data';
	}

	/**
	 * @dataProvider provideTestCases
	 */
	public function testValidation( $fileName, $shouldFail ) {
		$parser = new Parser();
		$options = new ParserOptions();
		$title = Title::newMainPage();
		$parser->startExternalParse( $title, $options, Parser::OT_HTML );
		$validator = new MockSimpleStyleParser( $parser );

		$file = $this->basePath . '/' . $fileName;
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
		$result = [];

		foreach ( glob( "{$this->basePath}/good-schemas/*.json" ) as $file ) {
			$file = substr( $file, strlen( $this->basePath ) + 1 );
			$result[] = [ $file, false ];
		}
		foreach ( glob( "{$this->basePath}/bad-schemas/*.json" ) as $file ) {
			$file = substr( $file, strlen( $this->basePath ) + 1 );
			$result[] = [ $file, true ];
		}

		return $result;
	}
}

class MockSimpleStyleParser extends SimpleStyleParser {
	protected function sanitize( &$json ) {
	}

	protected function normalize( array &$json ) {
		return Status::newGood( $json );
	}
}
