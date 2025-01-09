<?php

namespace Kartographer\Tests;

use GeoData\Globe;
use Kartographer\Special\SpecialMap;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Kartographer\Special\SpecialMap
 * @group Kartographer
 * @license MIT
 */
class SpecialMapTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::LanguageCode => 'qqx',
		] );
	}

	/**
	 * @covers ::__construct
	 * @covers ::parseSubpage
	 * @dataProvider provideParseSubpage
	 */
	public function testParseSubpage(
		string $par, ?float $expectedLat = null, ?float $expectedLon = null, ?string $expectedLang = null
	) {
		/** @var SpecialMap $specialMap */
		$specialMap = TestingAccessWrapper::newFromObject( new SpecialMap() );
		$res = $specialMap->parseSubpage( $par );
		if ( $expectedLat === null || $expectedLon === null ) {
			$this->assertNull( $res, 'Parsing is expected to fail' );
		} else {
			[ 'lat' => $lat, 'lon' => $lon, 'lang' => $lang ] = $res;
			$this->assertSame( $expectedLat, $lat, 'Comparing latitudes' );
			$this->assertSame( $expectedLon, $lon, 'Comparing longitudes' );
			$this->assertSame( $expectedLang, $lang, 'Comparing language' );
		}
	}

	public static function provideParseSubpage() {
		$tests = [
			[ '' ],
			[ 'foo' ],
			[ 'foo/bar/baz' ],
			[ '123' ],
			[ '1/2' ],
			[ '1/2/3/en/4' ],
			[ '1.0/2/3' ],
			[ '-1/2/3' ],
			[ '1/2/.3' ],
			[ '1/2/3.45e+6' ],
			[ '1/2,3/4,5' ],

			[ '12/23/34/en', 23.0, 34.0, 'en' ],
			[ '10/3.4/4.5/local', 3.4, 4.5, 'local' ],
			[ '0/0/-0', 0.0, 0.0, 'local' ],
			[ '12/-34.56/0.78', -34.56, 0.78, 'local' ],
			[ '18/89.9/179.9', 89.9, 179.9, 'local' ],
			[ '18/-89.9/-179.9', -89.9, -179.9, 'local' ],
			[ '18/90/-180', 90.0, -180.0, 'local' ],
			[ '/1/2', 1.0, 2.0, 'local' ],
		];

		if ( class_exists( Globe::class ) ) {
			$tests = array_merge( $tests,
				[
					[ '0/90.000001/10' ],
					[ '0/10/180.000000001' ],
				]
			);
		}

		return $tests;
	}

	/**
	 * @covers ::__construct
	 * @covers ::getWorldMapSrcset
	 * @covers ::getWorldMapUrl
	 * @dataProvider provideSrcsetScales
	 */
	public function testGetWorldMapSrcset( ?string $expected, string $mapServer, array $srcsetScales ) {
		$this->overrideConfigValues( [
			'KartographerMapServer' => $mapServer,
			'KartographerSrcsetScales' => $srcsetScales,
		] );
		/** @var SpecialMap $specialMap */
		$specialMap = TestingAccessWrapper::newFromObject( new SpecialMap() );
		$this->assertSame(
			$expected,
			$specialMap->getWorldMapSrcset()
		);
	}

	public static function provideSrcsetScales() {
		return [
			[ null, 'http://192.0.2.0', [] ],
			[ null, 'http://192.0.2.0', [ 1 ] ],
			[ 'http://192.0.2.0/osm-intl/0/0/0@2x.png 2x', 'http://192.0.2.0', [ 2 ] ],
		];
	}

	/**
	 * @covers ::link
	 * @dataProvider provideLinks
	 */
	public function testLink( ?string $expected, ?float $lat, ?float $lon, ?int $zoom = null, string $lang = 'local' ) {
		$this->overrideConfigValue( MainConfigNames::ArticlePath, '/wiki/$1' );
		$this->assertSame( $expected, SpecialMap::link( $lat, $lon, $zoom, $lang ) );
	}

	public static function provideLinks() {
		return [
			[ '/wiki/Special:Map/6/12/-34.5', 12, -34.5, 6 ],
			[ '/wiki/Special:Map/6/12/-34.5/zh', 12, -34.5, 6, 'zh' ],
			[ '/wiki/Special:Map/6/12/34', 12, 34, 6, 'local' ],
			[ '/wiki/Special:Map/0/12/34', 12, 34, null, '' ],
			[ '/wiki/Special:Map/0/-12/34', -12, 34 ],
			[ null, 12, null ],
			[ null, null, null ],
		];
	}

}
