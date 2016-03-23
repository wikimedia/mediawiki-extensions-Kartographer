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
	private $wikitextJson = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "<script>alert(document.cookie);</script>",
      "description": "[[Link to nowhere]]",
      "marker-symbol": "-number"
    }
  }';

	public function setUp() {
		$this->setMwGlobals( [
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
		parent::setUp();
	}

	/**
	 * @dataProvider provideTagData
	 */
	public function testTagData( $expected, $input, $message ) {
		$output = $this->parse( $input );

		if ( $expected === false ) {
			$this->assertTrue( $output->getExtensionData( 'kartographer_broken' ), 'Parse is expected to fail' );
			$this->assertTrue( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), 'Category for failed maps should be added' );
			return;
		}
		$this->assertFalse( !!$output->getExtensionData( 'kartographer_broken' ), 'Parse is expected to succeeed' );
		$this->assertFalse( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), 'No tracking category for ' );

		$expected = json_encode( json_decode( $expected ) ); // Normalize JSON

		$this->assertEquals( $expected, json_encode( $output->getExtensionData( 'kartographer_data' ) ) );
	}

	public function provideTagData() {
		$validJson = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "Foo bar",
      "marker-symbol": "1",
      "marker-size": "medium",
      "marker-color": "0050d0"
    }
  }';
		$wikitextJsonParsed = '{"_be34df99c99d1efd9eaa8eabc87a43f2541a67e5":[
				{"type":"Feature","geometry":{"type":"Point","coordinates":[-122.3988,37.8013]},
				"properties":{"title":"&lt;script&gt;alert(document.cookie);&lt;\/script&gt;",
				"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>","marker-symbol":"1"}}
			]}';
		return [
			[ 'null', '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013/>', '<mapframe> without JSON' ],
			[ 'null', '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013></mapframe>', '<mapframe> without JSON 2' ],
			//[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>123</mapframe>', 'Invalid JSON' ],
			//[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013 mode=interactive>{{"":""}}</maps>', 'Invalid JSON 3' ],
			[ "{\"_4622d19afa2e6480c327846395ed932ba6fa56d4\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>$validJson</mapframe>", '<mapframe> with GeoJSON' ],
			[ "{\"_4622d19afa2e6480c327846395ed932ba6fa56d4\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>[$validJson]</mapframe>", '<mapframe> with GeoJSON array' ],
			[ $wikitextJsonParsed, "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>[{$this->wikitextJson}]</mapframe>", '<mapframe> with parsable text and description' ],
			[ $wikitextJsonParsed, "<maplink zoom=13 longitude=-122.3988 latitude=37.8013>[{$this->wikitextJson}]</maplink>", '<maplink> with parsable text and description' ],
			// Bugs
			[ 'null', "<maplink zoom=13 longitude=-122.3988 latitude=37.8013>\t\r\n </maplink>", 'T127345: whitespace-only tag content, <maplink>' ],
		];
	}

	public function testLiveData() {
		$text =
<<<WIKITEXT
<maplink latitude=10 longitude=20 zoom=13>
{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    }
}
</maplink>
<mapframe width=200 height=200 latitude=10 longitude=20 zoom=13>
{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [10, 20]
    }
}
</mapframe>
WIKITEXT;
		$output = $this->parse( $text );
		$vars = $output->getJsConfigVars();
		$this->assertArrayHasKey( 'wgKartographerLiveData', $vars );
		$this->assertArrayEquals( [ '_5e4843908b3c3d3b11ac4321edadedde28882cc2' ], array_keys( $vars['wgKartographerLiveData'] ) );
	}

	/**
	 * Parses wikitext
	 * @param string $text
	 * @return ParserOutput
	 */
	private function parse( $text ) {
		$parser = new Parser();
		$options = new ParserOptions();
		$title = Title::newFromText( 'Test' );

		return $parser->parse( $text, $title, $options );
	}

	private function hasTrackingCategory( ParserOutput $output, $key ) {
		$cat = wfMessage( $key )->inContentLanguage()->text();
		$title = Title::makeTitleSafe( NS_CATEGORY, $cat );
		$cats = $output->getCategories();
		return isset( $cats[$title->getDBkey()] );
	}
}
