<?php

namespace Kartographer\Tests;

use GeoData\Globe;
use Kartographer\SpecialMap;
use MediaWikiTestCase;
use Title;

/**
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
	public function testParseSubpage( $par, $expectedLat = null, $expectedLon = null ) {
		$res = SpecialMap::parseSubpage( $par );
		if ( $expectedLat === null || $expectedLon === null ) {
			$this->assertFalse( $res, 'Parsing is expected to fail' );
		} else {
			list( , $lat, $lon ) = $res;
			$this->assertSame( $expectedLat, $lat, 'Comparing latitudes' );
			$this->assertSame( $expectedLon, $lon, 'Comparing longitudes' );
		}
	}

	public function provideParseSubpage() {
		$tests = [
			[ '' ],
			[ 'foo' ],
			[ 'foo/bar/baz' ],
			[ '123' ],
			[ '1/2' ],
			[ '1/2/3/4' ],
			[ '1.0/2/3' ],
			[ '-1/2/3' ],
			[ '1/2/.3' ],
			[ '1/2/3.45e+6' ],
			[ '1/2,3/4,5' ],

			[ '0/0/-0', 0.0, 0.0 ],
			[ '12/-34.56/0.78', -34.56, 0.78 ],
			[ '18/89.9/179.9', 89.9, 179.9 ],
			[ '18/-89.9/-179.9', -89.9, -179.9 ],
			[ '18/90/-180', 90.0, -180.0 ],
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
		$this->assertEquals( '/wiki/Special:Map/6/12/-34.5', $title->getLocalURL() );
	}
}
