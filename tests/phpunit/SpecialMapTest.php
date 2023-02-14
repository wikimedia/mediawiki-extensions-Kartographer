<?php

namespace Kartographer\Tests;

use GeoData\Globe;
use Kartographer\SpecialMap;
use MediaWikiIntegrationTestCase;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Kartographer\SpecialMap
 * @group Kartographer
 * @license MIT
 */
class SpecialMapTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideParseSubpage
	 */
	public function testParseSubpage(
		string $par, float $expectedLat = null, float $expectedLon = null, string $expectedLang = null
	) {
		/** @var SpecialMap $specialMap */
		$specialMap = TestingAccessWrapper::newFromObject( new SpecialMap() );
		$res = $specialMap->parseSubpage( $par );
		if ( $expectedLat === null || $expectedLon === null ) {
			$this->assertFalse( $res, 'Parsing is expected to fail' );
		} else {
			[ 'lat' => $lat, 'lon' => $lon, 'lang' => $lang ] = $res;
			$this->assertSame( $expectedLat, $lat, 'Comparing latitudes' );
			$this->assertSame( $expectedLon, $lon, 'Comparing longitudes' );
			$this->assertSame( $expectedLang, $lang, 'Comparing language' );
		}
	}

	public function provideParseSubpage() {
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
	 * @dataProvider provideLinks
	 */
	public function testLink( string $expected, ?float $lat, ?float $lon, int $zoom = null, string $lang = 'local' ) {
		$this->setMwGlobals( 'wgArticlePath', '/wiki/$1' );
		$title = SpecialMap::link( $lat, $lon, $zoom, $lang );
		$this->assertInstanceOf( Title::class, $title );
		$this->assertSame( $expected, $title->getLocalURL() );
	}

	public function provideLinks() {
		return [
			[ '/wiki/Special:Map/6/12/-34.5/local', 12, -34.5, 6 ],
			[ '/wiki/Special:Map/6/12/-34.5/zh', 12, -34.5, 6, 'zh' ],
			[ '/wiki/Special:Map/6/12/34/local', 12, 34, 6, 'local' ],
			[ '/wiki/Special:Map//12/34/', 12, 34, null, '' ],
			[ '/wiki/Special:Map//-12/34/local', -12, 34 ],
			[ '/wiki/Special:Map//12//local', 12, null ],
			[ '/wiki/Special:Map////local', null, null ],
		];
	}

}
