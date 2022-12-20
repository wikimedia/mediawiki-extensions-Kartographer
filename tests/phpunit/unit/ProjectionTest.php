<?php

namespace Kartographer\UnitTests;

use Kartographer\Projection\EPSG3857;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Projection\EPSG3857
 * @group Kartographer
 * @license MIT
 */
class ProjectionTest extends MediaWikiUnitTestCase {

	private const DELTA = 0.00001;

	public function provideCoordinatesAndZoom() {
		return [
			[ [ 0, 0 ], [ 128, 128 ] ],
			[ [ 1, 1 ], [ 128.711111, 127.288852 ] ],
			[ [ 40, 0 ], [ 128, 96.916264 ] ],
			[ [ 80, 180 ], [ 256, 28.738405 ] ],
			[ [ 0, 360 ], [ 384, 128 ] ],
		];
	}

	/**
	 * @dataProvider provideCoordinatesAndZoom
	 */
	public function testLatLonProjection( array $latLon, array $point ) {
		$actual = EPSG3857::latLonToPoint( $latLon );
		$this->assertCount( 2, $actual );
		$this->assertEqualsWithDelta( $point[0], $actual[0], self::DELTA, 'x' );
		$this->assertEqualsWithDelta( $point[1], $actual[1], self::DELTA, 'y' );
	}

}
