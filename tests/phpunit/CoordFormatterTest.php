<?php

namespace Kartographer\Tests;

use Kartographer\CoordFormatter;
use MediaWikiIntegrationTestCase;

/**
 * @covers \Kartographer\CoordFormatter
 * @group Kartographer
 * @license MIT
 */
class CoordFormatterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideFormatter
	 */
	public function testFormatter( string $expected, float $lat, float $lon ) {
		$result = CoordFormatter::format( $lat, $lon, 'en' );
		$this->assertEquals( $expected, $result );
	}

	public function provideFormatter() {
		return [
			[ '0°0′0″N 0°0′0″E', 0, 0 ],
			[ '0°0′0″N 0°0′0″E', -0.000000000001, 0.000000000001 ],
			[ '1°0′0″S 1°0′0″W', -1, -1 ],
			[ '1°0′0″N 11°0′0″W', 0.999999999999, -10.999999999999 ],
			[ '10°20′0″N 0°0′0″E', 10.333333333333333333, 0 ],
			// Was getting 10°11′60″N 0°0′0″E here
			[ '10°12′0″N 0°0′0″E', 10.2, 0 ],
			[ '45°30′0″N 20°0′36″E', 45.5, 20.01 ],
			[ '27°46′23″N 82°38′24″W', 27.773056, -82.64 ],
			[ '29°57′31″N 90°3′54″W', 29.958611, -90.065 ],
		];
	}
}
