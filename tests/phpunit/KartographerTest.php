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
	public function setUp() {
		$this->setMwGlobals( array(
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		) );
		parent::setUp();
	}

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
		$validJson = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "Foo bar",
      "marker-symbol": "museum",
      "marker-size": "medium",
      "marker-color": "0050d0"
    }
  }';
		$wikitextJson = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "<script>alert(document.cookie);</script>",
      "description": "[[Link to nowhere]]"
    }
  }';
		$wikitextJsonParsed = '{"_b3a06246589b01ce9e9c2ba3dc97e265f7ea0308":[
				{"type":"Feature","geometry":{"type":"Point","coordinates":[-122.3988,37.8013]},
				"properties":{"title":"&lt;script&gt;alert(document.cookie);&lt;\/script&gt;",
				"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>"}}
			]}';
		return array(
			array( false, '<maps/>', 'Empty tag is meaningless' ),
			array( false, '<maps></maps>', 'Empty tag is meaningless 2' ),
			array( 'null', '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive/>', 'Map without JSON' ),
			array( 'null', '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive></maps>', 'Map without JSON 2' ),
			array( false, '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>123</maps>', 'Invalid JSON' ),
			array( false, '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>fail</maps>', 'Invalid JSON 2' ),
			array( false, '<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>{{"":""}}</maps>', 'Invalid JSON 3' ),
			array( "{\"_bc2671e0e7a829e9d19c743d6701fa410dd04827\":[$validJson]}", "<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>$validJson</maps>", 'Map with GeoJSON' ),
			array( "{\"_bc2671e0e7a829e9d19c743d6701fa410dd04827\":[$validJson]}", "<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>[$validJson]</maps>", 'Map with GeoJSON array' ),
			array( $wikitextJsonParsed, "<maps width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>[$wikitextJson]</maps>", 'Map with parsable text and description' ),
		);
	}

	private function hasTrackingCategory( ParserOutput $output, $key ) {
		$cat = wfMessage( $key )->inContentLanguage()->text();
		$title = Title::makeTitleSafe( NS_CATEGORY, $cat );
		$cats = $output->getCategories();
		return isset( $cats[$title->getDBkey()] );
	}
}
