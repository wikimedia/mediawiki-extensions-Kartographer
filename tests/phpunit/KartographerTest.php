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

		if ( $expected === false ) {
			$this->assertTrue( $output->getExtensionData( 'kartographer_broken' ), 'Parse is expected to fail' );
			$this->assertTrue( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), 'Category for failed maps should be added' );
			return;
		}
		$this->assertFalse( !!$output->getExtensionData( 'kartographer_broken' ), 'Parse is expected to succeeed' );
		$this->assertFalse( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), 'No tracking category for ' );

		$expected = json_encode( json_decode( $expected ) );

		$this->assertEquals( $expected, json_encode( $output->getExtensionData( 'kartographer_data' ) ) );
	}

	public function provideTagParse() {
		return array(
			array( false, '<maps/>', 'Empty tag is meaningless' ),
			array( false, '<maps></maps>', 'Empty tag is meaningless 2' ),
			array( 'null', '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive/>', 'Map without JSON' ),
			array( 'null', '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive></maps>', 'Map without JSON 2' ),
			array( 'null', '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>[]</maps>', 'Map with empty JSON' ),
		);
	}

	private function hasTrackingCategory( ParserOutput $output, $key ) {
		$cat = wfMessage( $key )->inContentLanguage()->text();
		$title = Title::makeTitleSafe( NS_CATEGORY, $cat );
		$cats = $output->getCategories();
		return isset( $cats[$title->getDBkey()] );
	}
}
