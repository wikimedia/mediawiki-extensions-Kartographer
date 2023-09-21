<?php
namespace Kartographer\Tests;

use Kartographer\State;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWikiLangTestCase;
use ParserOptions;
use ParserOutput;

/**
 * @group Kartographer
 * @group Database
 * @covers \Kartographer\Tag\LegacyTagHandler
 * @covers \Kartographer\Tag\LegacyMapFrame
 * @covers \Kartographer\Tag\LegacyMapLink
 * @license MIT
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
		$this->overrideConfigValues( [
			'KartographerMapServer' => 'http://192.0.2.0',
			'KartographerMediaWikiInternalUrl' => 'localhost',
			MainConfigNames::LanguageCode => 'qqx',
			MainConfigNames::Script => '/w/index.php',
			MainConfigNames::ScriptPath => '/w',
			'KartographerParsoidSupport' => 'true',
		] );
	}

	/**
	 * @dataProvider provideTagData
	 */
	public function testTagData( $expected, string $input, string $message, bool $wikivoyageMode = false ) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', $wikivoyageMode );
		$output = $this->parse( $input );
		$state = State::getState( $output );

		if ( $expected === false ) {
			$this->assertTrue( $state->hasBrokenTags(), $message . ' Parse is expected to fail' );
			$this->assertTrackingCategory( 'kartographer-broken-category', $output );
			$this->assertNotTrackingCategory( 'kartographer-tracking-category', $output );
			return;
		}
		$this->assertFalse( $state->hasBrokenTags(), $message . ' Parse is expected to succeed' );
		$this->assertTrue(
			$state->hasValidTags(),
			$message . ' State is expected to have valid tags'
		);
		$this->assertNotTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertTrackingCategory( 'kartographer-tracking-category', $output );

		// Normalize JSON
		$expected = json_encode( json_decode( $expected ) );

		$this->assertEquals( $expected, json_encode( $state->getData() ), $message );
	}

	/**
	 * @dataProvider provideTagData
	 */
	public function testTagDataParsoid( $expected, string $input, string $message, bool $wikivoyageMode = false,
										?string $parsoid = null
	) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', $wikivoyageMode );
		$output = $this->parseParsoid( $input );
		$state = $output->getExtensionData( 'kartographer' );

		if ( $expected === false ) {
			$this->assertTrue( $state['broken'] > 0, $message . ' Parse is expected to fail' );
			$this->assertTrackingCategory( 'kartographer-broken-category', $output );
			$this->assertNotTrackingCategory( 'kartographer-tracking-category', $output );
			return;
		}
		$this->assertFalse( $state['broken'] > 0, $message . ' Parse is expected to succeed' );
		$this->assertTrue(
			$state['maplinks'] + $state['mapframes'] > $state['broken'],
			$message . ' State is expected to have valid tags'
		);
		$this->assertNotTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertTrackingCategory( 'kartographer-tracking-category', $output );

		// Normalize JSON
		$expected = json_encode( json_decode( $parsoid ?? $expected ) );

		$this->assertEquals( $expected, json_encode( $state['data'] ), $message );
	}

	public static function provideTagData() {
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
		$wikitextJsonParsed = '{"_d0b261d7d6c90ab9ca4b2cbc36b5171b11510015":[
				{"type":"Feature","geometry":{"type":"Point","coordinates":[-122,37]},
				"properties":{"title":"&lt;script&gt;alert(document.cookie);&lt;\/script&gt;",
				"description":"<a href=\"\/w\/index.php?title=Link_to_nowhere&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"(red-link-title: Link to nowhere)\">Link to nowhere<\/a>","marker-symbol":"1"}}
			]}';
		$wikitextJsonParsoid = '{"_301c273795f88ed29491555b76a382a279ea387e":[
			{"type":"Feature","geometry":{"type":"Point","coordinates":[-122,37]},
			"properties":{"title":"&lt;script>alert(document.cookie);&lt;\/script>",
			"description":"<a rel=\"mw:WikiLink\" href=\".\/Link_to_nowhere\" title=\"Link to nowhere\" data-parsoid=\'{\"tsr\":[0,19],\"stx\":\"simple\",\"a\":{\"href\":\".\/Link_to_nowhere\"},\"sa\":{\"href\":\"Link to nowhere\"}}\'>Link to nowhere<\/a>","marker-symbol":"1"}}
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
				'<mapframe> with parsable text and description',
				false,
				$wikitextJsonParsoid
			],
			[
				$wikitextJsonParsed,
				'<maplink zoom=13 longitude=-122 latitude=37>[' .
					self::WIKITEXT_JSON . ']</maplink>',
				'<maplink> with parsable text and description',
				false,
				$wikitextJsonParsoid
			],

			// Bugs
			[ '[]', "<maplink zoom=13 longitude=-122 latitude=37>\t\r\n </maplink>", 'T127345: whitespace-only tag content, <maplink>' ],
			[ $xssJsonSanitized, "<maplink zoom=13 longitude=10 latitude=20>$xssJson</maplink>", 'T134719: XSS via __proto__' ],
			[ '[]', '<mapframe show="foo, bar, baz" zoom=12 latitude=10 longitude=20 width=100 height=100 />', 'T148971 - weird LiveData', true ],
		];
		// phpcs:enable
	}

	public function testBothTrackingCategories() {
		// An invalid and a valid mapframe
		$wikitext = '<mapframe /><mapframe width="1" height="1" />';
		$output = $this->parse( $wikitext );
		$this->assertTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertTrackingCategory( 'kartographer-tracking-category', $output );
	}

	public function testBothTrackingCategoriesParsoid() {
		// An invalid and a valid mapframe
		$wikitext = '<mapframe /><mapframe width="1" height="1" />';
		$output = $this->parseParsoid( $wikitext );
		$this->assertTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertTrackingCategory( 'kartographer-tracking-category', $output );
	}

	public function testNoTrackingCategories() {
		$output = $this->parse( '' );
		$this->assertNotTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertNotTrackingCategory( 'kartographer-tracking-category', $output );
	}

	public function testNoTrackingCategoriesParsoid() {
		$output = $this->parseParsoid( '' );
		$this->assertNotTrackingCategory( 'kartographer-broken-category', $output );
		$this->assertNotTrackingCategory( 'kartographer-tracking-category', $output );
	}

	/**
	 * @dataProvider provideResourceModulesData
	 */
	public function testResourceModules( string $input, array $expectedModules, array $expectedStyles ) {
		$this->setMwGlobals( 'wgKartographerStaticMapframe', false );
		$output = $this->parse( $input );

		$this->assertArrayEquals(
			array_keys( $expectedModules ), array_unique( $output->getModules() )
		);
		$this->assertArrayEquals(
			array_keys( $expectedStyles ), array_unique( $output->getModuleStyles() )
		);
	}

	/**
	 * @dataProvider provideResourceModulesData
	 */
	public function testResourceModulesParsoid( string $input, array $expectedModules, array $expectedStyles ) {
		$this->setMwGlobals( 'wgKartographerStaticMapframe', false );
		$output = $this->parseParsoid( $input );

		$this->assertArrayEquals(
			array_keys( $expectedModules ), array_unique( $output->getModules() )
		);
		$this->assertArrayEquals(
			array_keys( $expectedStyles ), array_unique( $output->getModuleStyles() )
		);
	}

	public static function provideResourceModulesData() {
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

	public function testImagePreview() {
		// Previews should not contain group data for the static image
		$this->setMwGlobals( [
			'wgKartographerStaticMapframe' => false,
			'KartographerDfltStyle' => 'osm-intl',
		] );
		$input = '<mapframe width=700 height=400 zoom=13 longitude=-122 latitude=37>' .
				self::WIKITEXT_JSON .
				'</mapframe>';
		$output = $this->parse( $input, true, true );
		// In preview mode, static maps get disabled and dynamic maps are used
		// The embedded img url therefor cannot refer to any groups,
		// because they might not yet exist when the renderer requests them.
		$this->assertStringNotContainsString( 'domain=localhost&amp;title=Test&amp;', $output->getRawText() );
		$this->assertStringContainsString(
			'/img/osm-intl,13,37,-122,700x400.png?lang=qqx',
			$output->getRawText()
		);
	}

	/**
	 * @dataProvider provideLiveData
	 */
	public function testLiveData(
		string $wikitext,
		array $expected,
		bool $isPreview = false,
		bool $isSectionPreview = false,
		bool $wikivoyageMode = false
	) {
		$this->setMwGlobals( [
			'wgKartographerWikivoyageMode' => $wikivoyageMode,
		] );
		$output = $this->parse( $wikitext, $isPreview, $isSectionPreview );
		$vars = $output->getJsConfigVars();
		$this->assertArrayHasKey( 'wgKartographerLiveData', $vars );
		$this->assertArrayEquals( $expected, array_keys( (array)$vars['wgKartographerLiveData'] ) );
	}

	/** @dataProvider provideLiveData */
	public function testLiveDataParsoid(
		string $wikitext,
		array $expected,
		bool $isPreview = false,
		bool $isSectionPreview = false,
		bool $wikivoyageMode = false
	) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', $wikivoyageMode );
		$output = $this->parseParsoid( $wikitext );
		$vars = $output->getJsConfigVars();
		$this->assertArrayHasKey( 'wgKartographerLiveData', $vars );

		if ( MediaWikiServices::getInstance()->getMainConfig()->has( 'KartographerParsoidSupport' ) &&
			MediaWikiServices::getInstance()->getMainConfig()->get( 'KartographerParsoidSupport' ) !== true ) {
			// not testing the exact content without parsoid, this would fail
			return;
		}
		// FIXME ideally, we would not ship more data than we strictly need, but for now we're fine with it.
		// To be revisited when preview mode is implemented in Parsoid.
		foreach ( $expected as $v ) {
			self::assertArrayHasKey( $v, (array)$vars['wgKartographerLiveData'] );
		}
	}

	public static function provideLiveData() {
		$maplinkJson = '{"type":"Feature","geometry":{"type":"Point","coordinates":[-122,37]}}';
		$mapframeJson = '{"type":"Feature","geometry":{"type":"Point","coordinates":[10,20]}}';
		$maplinkHash = '_' . sha1( "[$maplinkJson]" );
		$mapframeHash = '_' . sha1( "[$mapframeJson]" );
		$frameAndLink = "<maplink latitude=10 longitude=20 zoom=13>$maplinkJson</maplink>" .
			"<mapframe width=200 height=200 latitude=10 longitude=20 zoom=13>$mapframeJson</mapframe>";
		$wikivoyageMaps = '<mapframe show="foo, bar, baz" zoom=12 latitude=10 longitude=20 width=100 height=100 />';

		return [
			[
				'wikitext' => $frameAndLink,
				'expected' => [ $mapframeHash ],
			],
			[
				'wikitext' => $frameAndLink,
				'expected' => [ $maplinkHash, $mapframeHash ],
				'isPreview' => true,
			],
			[
				'wikitext' => $frameAndLink,
				'expected' => [ $maplinkHash, $mapframeHash ],
				'isPreview' => false,
				'isSectionPreview' => true,
			],
			[
				'wikitext' => $frameAndLink,
				'expected' => [ $maplinkHash, $mapframeHash ],
				'isPreview' => true,
				'isSectionPreview' => true,
			],
			[
				'wikitext' => $wikivoyageMaps,
				'expected' => [ 'foo', 'bar', 'baz' ],
				'isPreview' => false,
				'isSectionPreview' => false,
				'wikivoyageMode' => true,
			],
		];
	}

	/**
	 * @dataProvider providePageProps
	 */
	public function testPageProps( string $text, ?int $frames, ?int $links ) {
		$po = $this->parse( $text );
		$this->assertEquals( $frames, $po->getPageProperty( 'kartographer_frames' ) );
		$this->assertEquals( $links, $po->getPageProperty( 'kartographer_links' ) );
	}

	public static function providePageProps() {
		return [
			[ '', null, null ],
			[ '<foo>', null, null ],
			[ '<mapframe>broken but still track</mapframe>
				<mapframe width=100 height=100 zoom=0 latitude=0 longitude=0 />', 2, null ],
			[ '<mapframe/><maplink/><mapframe></mapframe><maplink></maplink>', 2, 2 ],
		];
	}

	/**
	 * @dataProvider provideGroupNames
	 */
	public function testGroupNames( array $expected, string $input ) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', true );
		$output = $this->parse( $input );
		$state = State::getState( $output );

		$this->assertTrue( $state->hasValidTags() );
		$this->assertFalse( $state->hasBrokenTags() );
		$this->assertSame( $expected, $state->getRequestedGroups() );
	}

	public static function provideGroupNames() {
		return [
			[ [], '<maplink></maplink>' ],
			[ [ 'a1' ], '<maplink show="a1"></maplink>' ],
			[ [ 'a1', 'b1' ], '<maplink group="b1" show="a1"></maplink>' ],
			[ [ 'a 1', 'b 1' ], '<maplink group="b 1" show="a 1"></maplink>' ],
			[ [ 'a 1', 'b 1' ], '<maplink group="b 1" show="a 1, b 1"></maplink>' ],
			[ [ 'cab 1', 'b 1' ], '<maplink group="b 1" show="cab 1, b 1"></maplink>' ],
			[ [ 'שלום' ], '<maplink show="שלום"></maplink>' ],
		];
	}

	/**
	 * @dataProvider provideInvalidGroupNames
	 */
	public function testInvalidGroupNames( string $input ) {
		$this->setMwGlobals( 'wgKartographerWikivoyageMode', true );
		$output = $this->parse( $input );
		$state = State::getState( $output );

		$this->assertFalse( $state->hasValidTags() );
		$this->assertTrue( $state->hasBrokenTags() );
	}

	public static function provideInvalidGroupNames() {
		return [
			[ '<maplink show="test😂"></maplink>' ],
			[ '<maplink show="hell.o"></maplink>' ],
			[ '<maplink group="c,1" show="a 1"></maplink>' ],
		];
	}

	/**
	 * Parses wikitext
	 * @param string $text
	 * @param bool $isPreview
	 * @param bool $isSectionPreview
	 * @return ParserOutput
	 */
	private function parse( string $text, bool $isPreview = false, bool $isSectionPreview = false ): ParserOutput {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$options = ParserOptions::newFromAnon();
		$options->setIsPreview( $isPreview );
		$options->setIsSectionPreview( $isSectionPreview );
		$title = Title::newFromText( 'Test' );

		return $parser->parse( $text, $title, $options );
	}

	private function assertTrackingCategory( string $expected, ParserOutput $output ): void {
		$this->assertHasTrackingCategory( true, $expected, $output,
			"Expected tracking category $expected" );
	}

	private function assertNotTrackingCategory( string $expected, ParserOutput $output ): void {
		$this->assertHasTrackingCategory( false, $expected, $output,
			"Unexpected tracking category $expected" );
	}

	private function assertHasTrackingCategory(
		bool $expected,
		string $key,
		ParserOutput $output,
		string $message
	): void {
		$cat = wfMessage( $key )->inContentLanguage()->text();
		$title = Title::makeTitleSafe( NS_CATEGORY, $cat );
		$cats = $output->getCategoryNames();
		$this->assertSame( $expected, in_array( $title->getDBkey(), $cats, true ), $message );
	}

	private function parseParsoid( string $wikitext ) {
		$parsoid = $this->getServiceContainer()->getParsoidParserFactory()->create();
		return $parsoid->parse( $wikitext, Title::newFromText( 'Test Page' ),
			ParserOptions::newFromAnon() );
	}
}
