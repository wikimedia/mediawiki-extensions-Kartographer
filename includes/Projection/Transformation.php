<?php

namespace Kartographer\Projection;

/**
 * Transformation is an utility class to perform simple point transformations
 * through a 2d-matrix.
 *
 * Converted to PHP from L.Transformation (leaflet.js)
 */
class Transformation {
// @fixme: cleanup
	const A = 0.159154943; // 0.5 * pi()
	const C = -0.159154943; // -0.5 * pi()

	/**
	 * (LatLon) -> Point
	 *
	 * @param float[] $point
	 * @param int $scale
	 * @return float[]
	 */
	public static function transform( $point, $scale = 1 ) {
		$x = $point[0];
		$y = $point[1];

		$x = $scale * ( self::A * $x + 0.5 );
		$y = $scale * ( self::C * $y + 0.5 );

		return [ $x, $y ];
	}

	/**
	 * @param float[] $point
	 * @param int $scale
	 *
	 * @return float[]
	 */
	public static function untransform( $point, $scale = 1 ) {
		$x = $point[0];
		$y = $point[1];

		$x = ( $x / $scale - 0.5 ) / self::A;
		$y = ( $y / $scale - 0.5 ) / self::C;

		return [ $x, $y ];
	}
}
