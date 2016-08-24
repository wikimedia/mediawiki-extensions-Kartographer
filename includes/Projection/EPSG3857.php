<?php

namespace Kartographer\Projection;

/**
 * EPSG3857 (Spherical Mercator) is the most common CRS for web mapping
 * and is used by Leaflet by default.
 *
 * Converted to PHP from L.CRS.EPSG3857 (leaflet.js)
 */
class EPSG3857 {

	const EARTH_RADIUS = 6378137;

	/**
	 * (LatLon) -> Point
	 *
	 * @param float[] $latLon
	 * @return float[]
	 */
	public static function project( $latLon ) {
		$projectedPoint = SphericalMercator::project( $latLon );

		return [ $projectedPoint * self::EARTH_RADIUS, $projectedPoint * self::EARTH_RADIUS ];
	}

	/**
	 * (LatLon, Number) -> Point
	 *
	 * @param float[] $latLon
	 * @param int $zoom
	 * @return array
	 */
	public static function latLonToPoint( $latLon, $zoom ) {
		$projectedPoint = SphericalMercator::project( $latLon );
		$scale = self::scale( $zoom );

		return Transformation::transform( $projectedPoint, $scale );
	}

	/**
	 * (Point, Number[, Boolean]) -> LatLon
	 *
	 * @param $point
	 * @param $zoom
	 * @return array
	 */
	public static function pointToLatLon( $point, $zoom ) {
		$scale = self::scale( $zoom );
		$untransformedPoint = Transformation::untransform( $point, $scale );

		return SphericalMercator::unproject( $untransformedPoint );
	}

	public static function scale( $zoom ) {
		return 256 * pow( 2, $zoom );
	}

	public static function getSize( $zoom ) {
		$size = self::scale( $zoom );

		return [ $size, $size ];
	}
}
