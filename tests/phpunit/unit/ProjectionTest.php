<?php

namespace Kartographer\UnitTests;

use Kartographer\Projection\EPSG3857;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Projection\EPSG3857
 * @covers \Kartographer\Projection\SphericalMercator
 * @covers \Kartographer\Projection\Transformation
 * @group Kartographer
 */
class ProjectionTest extends MediaWikiUnitTestCase {

	private const EARTH_RADIUS = 6378137;
	private const DELTA = 0.00001;

	public function provideCoordinates() {
		return [
			[ [ 0, 0 ], [ 0, 0 ] ],
			[ [ 1, 1 ], [ 0.017453292519, 0.017454178683 ] ],
			[ [ 40, 0 ], [ 0, 0.762909652066 ] ],
			[ [ 80, 180 ], [ pi(), 2.436246053715 ] ],
			[ [ 0, 360 ], [ 2 * pi(), 0 ] ],
		];
	}

	/**
	 * @dataProvider provideCoordinates
	 */
	public function testProject( $latLon, $expected ) {
		$actual = EPSG3857::project( $latLon );
		$this->assertCount( 2, $actual );
		$this->assertEqualsWithDelta( $expected[0] * self::EARTH_RADIUS, $actual[0], self::DELTA, 'x' );
		$this->assertEqualsWithDelta( $expected[1] * self::EARTH_RADIUS, $actual[1], self::DELTA, 'y' );
	}

	public function provideCoordinatesAndZoom() {
		return [
			[ [ 0, 0 ], [ 128, 128 ] ],
			[ [ 1, 1 ], [ 128.711111, 127.288852 ] ],
			[ [ 40, 0 ], [ 128, 96.916264 ] ],
			[ [ 80, 180 ], [ 256, 28.738405 ] ],
			[ [ 0, 360 ], [ 384, 128 ] ],

			[ [ 0, 0 ], [ 256, 256 ], 1 ],
			[ [ 1, 1 ], [ 257.422222221401, 254.577705567489 ], 1 ],
			[ [ 40, 0 ], [ 256, 193.832528799328 ], 1 ],
			[ [ 80, 180 ], [ 511.999999852186, 57.476811871679 ], 1 ],
			[ [ 0, 360 ], [ 767.999999704373, 256 ], 1 ],

			[ [ 0, 0 ], [ 512, 512 ], 2 ],
			[ [ 1, 1 ], [ 514.844444442802, 509.155411134978 ], 2 ],
			[ [ 40, 0 ], [ 512, 387.665057598657 ], 2 ],
			[ [ 80, 180 ], [ 1023.999999704373, 114.953623743359 ], 2 ],
			[ [ 0, 360 ], [ 1535.999999408747, 512 ], 2 ],
		];
	}

	/**
	 * @dataProvider provideCoordinatesAndZoom
	 */
	public function testLatLonProjection( $latLon, $point, $zoom = 0 ) {
		$actual = EPSG3857::latLonToPoint( $latLon, $zoom );
		$this->assertCount( 2, $actual );
		$this->assertEqualsWithDelta( $point[0], $actual[0], self::DELTA, 'x' );
		$this->assertEqualsWithDelta( $point[1], $actual[1], self::DELTA, 'y' );

		$actual = EPSG3857::pointToLatLon( $point, $zoom );
		$this->assertCount( 2, $actual );
		$this->assertEqualsWithDelta( $latLon[0], $actual[0], self::DELTA, 'lat' );
		$this->assertEqualsWithDelta( $latLon[1], $actual[1], self::DELTA, 'lon' );
	}

	public function provideZooms() {
		return [
			[ -1, 128 ],
			[ 0, 256 ],
			[ 0.1, 274.3740064 ],
			[ 1, 512 ],
			[ 2, 1024 ],
		];
	}

	/**
	 * @dataProvider provideZooms
	 */
	public function testScaleAndSize( $zoom, $expected ) {
		$actual = EPSG3857::getSize( $zoom );
		$this->assertCount( 2, $actual );
		$this->assertSame( $actual[0], $actual[1] );
		$this->assertEqualsWithDelta( $expected, $actual[0], self::DELTA );
	}

}
