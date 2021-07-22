<?php
namespace Kartographer\Tests;

use ExtensionRegistry;
use Kartographer\State;
use MediaWiki\MediaWikiServices;
use MediaWikiLangTestCase;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * @group Kartographer
 * @covers \Kartographer\Tag\TagHandler
 * @covers \Kartographer\Tag\MapFrame
 * @covers \Kartographer\Tag\MapLink
 */
class KartographerTest extends MediaWikiLangTestCase {

	private const WIKITEXT_JSON = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122, 37]
    },
    "properties": {
      "title": "<script>alert(document.cookie);</script>",
      "description": "[[Link to nowhere]]",
      "marker-symbol": "-number"
    }
  }';

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
	}

	/**
	 * @return bool
	 */
	private function hasParserFunctions() {
		return ExtensionRegistry::getInstance()->isLoaded( 'ParserFunctions' );
	}

	/**
	 * @dataProvider provideTagData
	 */
	public function testTagData( $expected, $input, $message, $wikivoyageMode = false ) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', $wikivoyageMode );
		$output = $this->parse( $input );
		$state = State::getState( $output );

		if ( $expected === false ) {
			$this->assertTrue( $state->hasBrokenTags(), $message . ' Parse is expected to fail' );

			if ( $this->hasParserFunctions() ) {
				$this->assertTrue(
					$this->hasTrackingCategory( $output, 'kartographer-broken-category' ),
					$message . ' Category for failed maps should be added'
				);
			}
			return;
		}
		$this->assertFalse( $state->hasBrokenTags(), $message . ' Parse is expected to succeed' );
		$this->assertTrue(
			$state->hasValidTags(),
			$message . ' State is expected to have valid tags'
		);
		$this->assertFalse(
			$this->hasTrackingCategory( $output, 'kartographer-broken-category' ),
			$message . ' No tracking category'
		);

		$expected = json_encode( json_decode( $expected ) ); // Normalize JSON

		$this->assertEquals( $expected, json_encode( $state->getData() ), $message );
	}

	public function provideTagData() {
		// phpcs:disable Generic.Files.LineLength
		$validJson = '{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122, 37]
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
		"coordinates": [-122, 37]
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
		$xssJsonSanitized = '{"_52fbfcdf508cc75f6e496a8988b5311f9f0df8a8":[
			{"type":"Feature","geometry":{"type":"Point","coordinates":[-122,37]},"properties":{"title":"Foo bar"}},
			{"type":"GeometryCollection","geometries":[{"type":"Point","coordinates":[0,0],"properties":{}}]}
		]}';
		$wikitextJsonParsed = '{"_ee2aa7342f7aee686e9d155932d0118dd4370c36":[
				{"type":"Feature","geometry":{"type":"Point","coordinates":[-122,37]},
				"properties":{"title":"&lt;script&gt;alert(document.cookie);&lt;\/script&gt;",
				"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Link to nowhere (page does not exist)\">Link to nowhere<\/a>","marker-symbol":"1"}}
			]}';
		return [
			[ '[]', '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37/>', '<mapframe> without JSON' ],
			[ '[]', '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37></mapframe>', '<mapframe> without JSON 2' ],
			[ "{\"_07f50db5d8d017fd95ccd49d38b9b156fd35a281\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>$validJson</mapframe>", '<mapframe> with GeoJSON' ],
			[ "{\"_07f50db5d8d017fd95ccd49d38b9b156fd35a281\":[$validJson]}", "<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>[$validJson]</mapframe>", '<mapframe> with GeoJSON array' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>123</mapframe>', 'Invalid JSON' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>{{"":""}}</mapframe>', 'Invalid JSON 2' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>[[]]</mapframe>', 'Invalid JSON 3' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>{"type":"fail"}</mapframe>', 'Invalid JSON 4' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>null</mapframe>', 'Invalid JSON 5' ],
			[ false, '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122, 37]
    },
    "properties": {
      "title": "Foo bar",
      "marker-symbol": "Cthulhu fhtagn!",
      "marker-size": "medium"
    }
  }</mapframe>', 'Invalid JSON 6' ],
			[
				$wikitextJsonParsed,
				'<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>[' .
					self::WIKITEXT_JSON . ']</mapframe>',
				'<mapframe> with parsable text and description'
			],
			[
				$wikitextJsonParsed,
				'<maplink zoom=13 longitude=-122 latitude=37>[' .
					self::WIKITEXT_JSON . ']</maplink>',
				'<maplink> with parsable text and description'
			],

			// Bugs
			[ '[]', "<maplink zoom=13 longitude=-122 latitude=37>\t\r\n </maplink>", 'T127345: whitespace-only tag content, <maplink>' ],
			[ $xssJsonSanitized, "<maplink zoom=13 longitude=10 latitude=20>$xssJson</maplink>", 'T134719: XSS via __proto__' ],
			[ '[]', '<mapframe show="foo, bar, baz" zoom=12 latitude=10 longitude=20 width=100 height=100 />', 'T148971 - weird LiveData', true ],
		];
		// phpcs:enable
	}

	/**
	 * @dataProvider provideResourceModulesData
	 */
	public function testResourceModules( $input, array $expectedModules, array $expectedStyles ) {
		$this->setMwGlobals( 'wgKartographerStaticMapframe', false );
		$output = $this->parse( $input );

		$this->assertArrayEquals(
			array_keys( $expectedModules ), array_unique( $output->getModules() )
		);
		$this->assertArrayEquals(
			array_keys( $expectedStyles ), array_unique( $output->getModuleStyles() )
		);
	}

	public function provideResourceModulesData() {
		$mapframe = '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37/>';
		$maplink = '<maplink width=700 height=400 zoom=13 longitude=-122 latitude=37/>';

		// @todo @fixme These are incorrect, but match existing code
		// When the code is fixed, they should be changed
		$frameMod = [ 'ext.kartographer.frame' => '' ];
		$frameStyle = [ 'ext.kartographer.style' => '' ];

		$linkMod = [ 'ext.kartographer.link' => '' ];
		$linkStyle = [ 'ext.kartographer.style' => '' ];

		return [
			[ '', [], [] ],
			[ $mapframe, $frameMod, $frameStyle ],
			[ $maplink, $linkMod, $linkStyle ],
			[ $mapframe . $maplink, $frameMod + $linkMod, $frameStyle + $linkStyle ],
		];
	}

	/**
	 * @dataProvider provideLiveData
	 */
	public function testLiveData(
		$text, array $expected, $preview, $sectionPreview, $wikivoyageMode
	) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', $wikivoyageMode );
		$output = $this->parse( $text,
			static function ( ParserOptions $options ) use ( $preview, $sectionPreview ) {
				$options->setIsPreview( $preview );
				$options->setIsSectionPreview( $sectionPreview );
			}
		);
		$vars = $output->getJsConfigVars();
		$this->assertArrayHasKey( 'wgKartographerLiveData', $vars );
		$this->assertArrayEquals( $expected, array_keys( (array)$vars['wgKartographerLiveData'] ) );
	}

	public function provideLiveData() {
		// phpcs:disable Generic.Files.LineLength
		$frameAndLink =
			<<<WIKITEXT
			<maplink latitude=10 longitude=20 zoom=13>
{
    "type": "Feature",
    "geometry": {
      "type": "Point",
      "coordinates": [-122, 37]
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
		$wikivoyageMaps = '<mapframe show="foo, bar, baz" zoom=12 latitude=10 longitude=20 width=100 height=100 />';
		return [
			// text          expected                                        preview sectionPreview wikivoyageMode
			[ $frameAndLink, [ '_5e4843908b3c3d3b11ac4321edadedde28882cc2' ], false, false, false ],
			[ $frameAndLink, [ '_0616e83db3b0cc67d5f835eb765da7a1ca26f4ce', '_5e4843908b3c3d3b11ac4321edadedde28882cc2' ], true, false, false ],
			[ $frameAndLink, [ '_0616e83db3b0cc67d5f835eb765da7a1ca26f4ce', '_5e4843908b3c3d3b11ac4321edadedde28882cc2' ], false, true, false ],
			[ $frameAndLink, [ '_0616e83db3b0cc67d5f835eb765da7a1ca26f4ce', '_5e4843908b3c3d3b11ac4321edadedde28882cc2' ], true, true, false ],
			[ $wikivoyageMaps, [ 'foo', 'bar', 'baz' ], false, false, true ],
		];
		// phpcs:enable
	}

	/**
	 * @dataProvider providePageProps
	 */
	public function testPageProps( $text, $frames, $links ) {
		$po = $this->parse( $text );
		$this->assertEquals( $frames, $po->getProperty( 'kartographer_frames' ) );
		$this->assertEquals( $links, $po->getProperty( 'kartographer_links' ) );
	}

	public function providePageProps() {
		return [
			[ '', null, null ],
			[ '<foo>', null, null ],
			[ '<mapframe>broken but still track</mapframe>
				<mapframe width=100 height=100 zoom=0 latitude=0 longitude=0 />', 2, null ],
			[ '<mapframe/><maplink/><mapframe></mapframe><maplink></maplink>', 2, 2 ],
		];
	}

	/**
	 * Parses wikitext
	 * @param string $text
	 * @param callable|null $optionsCallback
	 * @return ParserOutput
	 */
	private function parse( $text, callable $optionsCallback = null ) {
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$options = ParserOptions::newFromAnon();
		if ( $optionsCallback ) {
			$optionsCallback( $options );
		}
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
