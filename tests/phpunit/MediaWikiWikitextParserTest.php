<?php

namespace Kartographer\Tests;

use Kartographer\MediaWikiWikitextParser;
use MediaWiki\Parser\Parser;
use MediaWikiIntegrationTestCase;

/**
 * @covers \Kartographer\MediaWikiWikitextParser
 * @covers \Kartographer\WikitextParser
 * @group Kartographer
 * @license MIT
 */
class MediaWikiWikitextParserTest extends MediaWikiIntegrationTestCase {

	public static function provideWikitexts() {
		return [
			[ '[[a]]', true ],
			[ 'a', false ],
			[ str_repeat( 'a', 65535 ), false ],
			[ str_repeat( 'a', 65536 ), true ],
		];
	}

	/**
	 * @dataProvider provideWikitexts
	 */
	public function testWikitextParsing( string $wikitext, bool $expected ) {
		$coreParser = $this->createMock( Parser::class );
		$coreParser->expects( $this->exactly( (int)$expected ) )
			->method( 'recursiveTagParseFully' )
			->willReturn( 'HTML' );

		$parser = new MediaWikiWikitextParser( $coreParser );
		$html = $parser->parseWikitext( $wikitext );
		$this->assertSame( $expected ? 'HTML' : $wikitext, $html );
	}

}
