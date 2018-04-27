<?php

namespace Kartographer\Tests;

use GeoData\Globe;
use Kartographer\SpecialMap;
use MediaWikiTestCase;
use Title;

/**
 * @covers \Kartographer\SpecialMap
 * @group Kartographer
 */
class SpecialMapTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideParseSubpage
	 *
	 * @param string $par
	 * @param float $expectedLat
	 * @param float $expectedLon
	 */
	public function testParseSubpage(
		$par, $expectedLat = null, $expectedLon = null, $expectedLang = null
	) {
		$res = SpecialMap::parseSubpage( $par );
		if ( $expectedLat === null || $expectedLon === null ) {
			$this->assertFalse( $res, 'Parsing is expected to fail' );
		} else {
			list( , $lat, $lon, $lang ) = $res;
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

	public function testLink() {
		$this->setMwGlobals( 'wgArticlePath', '/wiki/$1' );
		$title = SpecialMap::link( 12, -34.5, 6 );
		$this->assertType( Title::class, $title );
		$this->assertEquals( '/wiki/Special:Map/6/12/-34.5/local', $title->getLocalURL() );

		$title = SpecialMap::link( 12, -34.5, 6, 'zh' );
		$this->assertType( Title::class, $title );
		$this->assertEquals( '/wiki/Special:Map/6/12/-34.5/zh', $title->getLocalURL() );
	}
}
