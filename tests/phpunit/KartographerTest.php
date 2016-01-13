<?php
namespace Kartographer\Tests;

use MediaWikiTestCase;
use Parser;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * @group Kartographer
*/
class KartographerTest extends MediaWikiTestCase {
	/**
	 * @dataProvider provideTagParse
	 */
	public function testTagParse( $expected, $input, $message ) {
		$parser = new Parser();
		$options = new ParserOptions();
		$title = Title::newFromText( 'Test' );

		$output = $parser->parse( $input, $title, $options );

		if ( $expected === null ) {
			$this->assertTrue( $output->getExtensionData( 'kartographer_broken' ) );
			$this->assertTrue( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ) );
			return;
		}
	}

	public function provideTagParse() {
		return array(
			array( null, '<maps/>', 'Empty tag is meaningless' ),
		);
	}

	private function hasTrackingCategory( ParserOutput $output, $key ) {
		$cat = wfMessage( $key )->inContentLanguage()->text();
		$title = Title::makeTitleSafe( NS_CATEGORY, $cat );
		$cats = $output->getCategories();
		return isset( $cats[$title->getDBkey()] );
	}
}
