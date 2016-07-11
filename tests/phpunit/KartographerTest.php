<?php
namespace Kartographer\Tests;

use Kartographer\State;
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
	 * @param string|false $expected
	 * @param string $input
	 * @param string $message
	 */
	public function testTagData( $expected, $input, $message ) {
		$output = $this->parse( $input );
		$state = State::getState( $output );

		if ( $expected === false ) {
			$this->assertTrue( $state->hasBrokenTags(), $message . ' Parse is expected to fail' );
			$this->assertTrue( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), $message . ' Category for failed maps should be added' );
			return;
		}
		$this->assertFalse( $state->hasBrokenTags(), $message . ' Parse is expected to succeed' );
		$this->assertTrue( $state->hasValidTags(), $message . ' State is expected to have valid tags' );
		$this->assertFalse( $this->hasTrackingCategory( $output, 'kartographer-broken-category' ), $message . ' No tracking category' );

		$expected = json_encode( json_decode( $expected ) ); // Normalize JSON

		$this->assertEquals( $expected, json_encode( $state->getData() ), $message );
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
		/** @noinspection HtmlUnknownTarget */
		$xssJson = '[
  {
	"__proto__": { "some": "bad stuff" },
	"type": "Feature",
	"geometry": {
		"type": "Point",
		"coordinates": [-122.3988, 37.8013]
	},
	"properties": {
		"__proto__": { "foo": "bar" },
		"title": "Foo bar"
	}
  },
  {
	"type": "GeometryCollection",
	"geometries": [
		{
			"__proto__": "recurse me",
			"type": "Point",
			"coordinates": [ 0, 0 ],
			"properties": { "__proto__": "is evil" }
		}
	]
  }
]';
		$xssJsonSanitized = '{"_a4d5387a1b7974bf854321421a36d913101f5724":[
			{"type":"Feature","geometry":{"type":"Point","coordinates":[-122.3988,37.8013]},"properties":{"title":"Foo bar"}},
			{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[0,0],"properties":{}}]}
		]}';
		$wikitextJsonParsed = '{"_be34df99c99d1efd9eaa8eabc87a43f2541a67e5":[
				{"type":"Feature","geometry":{"type":"Point","coordinates":[-122.3988,37.8013]},
				"properties":{"title":"&lt;script&gt;alert(document.cookie);&lt;\/script&gt;",
				"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>","marker-symbol":"1"}}
			]}';
		return [
			[ '[]', '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013/>', '<mapframe> without JSON' ],
			[ '[]', '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013></mapframe>', '<mapframe> without JSON 2' ],
			[ "{\"_4622d19afa2e6480c327846395ed932ba6fa56d4\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>$validJson</mapframe>", '<mapframe> with GeoJSON' ],
			[ "{\"_4622d19afa2e6480c327846395ed932ba6fa56d4\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>[$validJson]</mapframe>", '<mapframe> with GeoJSON array' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>123</mapframe>', 'Invalid JSON' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>{{"":""}}</mapframe>', 'Invalid JSON 2' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>[[]]</mapframe>', 'Invalid JSON 3' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>{"type":"fail"}</mapframe>', 'Invalid JSON 4' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>null</mapframe>', 'Invalid JSON 5' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122.3988, 37.8013]
    },
    "properties": {
      "title": "Foo bar",
      "marker-symbol": "Cthulhu fhtagn!",
      "marker-size": "medium"
    }
  }</mapframe>', 'Invalid JSON 6' ],
			[ $wikitextJsonParsed, "<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013>[{$this->wikitextJson}]</mapframe>", '<mapframe> with parsable text and description' ],
			[ $wikitextJsonParsed, "<maplink zoom=13 longitude=-122.3988 latitude=37.8013>[{$this->wikitextJson}]</maplink>", '<maplink> with parsable text and description' ],

			// Bugs
			[ '[]', "<maplink zoom=13 longitude=-122.3988 latitude=37.8013>\t\r\n </maplink>", 'T127345: whitespace-only tag content, <maplink>' ],
			[ $xssJsonSanitized, "<maplink zoom=13 longitude=10 latitude=20>$xssJson</maplink>", 'T134719: XSS via __proto__' ],
		];
	}

	/**
	 * @dataProvider provideResourceModulesData
	 * @param string $input
	 * @param array $expectedModules
	 * @param array $expectedStyles
	 */
	public function testResourceModules( $input, array $expectedModules, array $expectedStyles ) {
		$output = $this->parse( $input );

		$this->assertArrayEquals( array_keys( $expectedModules ), array_unique( $output->getModules() ) );
		$this->assertArrayEquals( array_keys( $expectedStyles ), array_unique( $output->getModuleStyles() ) );
		$this->assertArrayEquals( [], array_unique( $output->getModuleScripts() ) );
	}

	public function provideResourceModulesData() {
		$mapframe = '<mapframe width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013/>';
		$maplink = '<maplink width=700 height=400 zoom=13 longitude=-122.3988 latitude=37.8013/>';

		// @todo @fixme These are incorrect, but match existing code
		// When the code is fixed, they should be changed
		$frameMod = [ 'ext.kartographer.frame' => ''];
		$frameStyle = [ 'ext.kartographer.style' => ''];

		$linkMod = [ 'ext.kartographer.link' => ''];
		$linkStyle = [ 'ext.kartographer.style' => ''];

		return [
			[ '', [], [] ],
			[ $mapframe, $frameMod, $frameStyle ],
			[ $maplink, $linkMod, $linkStyle ],
			[ $mapframe . $maplink, $frameMod + $linkMod, $frameStyle + $linkStyle ],
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
