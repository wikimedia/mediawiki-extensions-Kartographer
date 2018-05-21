<?php

namespace Kartographer\Projection;

/**
 * Spherical Mercator is the most popular map projection,
 * used by EPSG:3857 CRS.
 *
 * Converted to PHP from L.Projection.SphericalMercator (leaflet.js)
 */
class SphericalMercator {

	const MAX_LATITUDE = 85.0511287798;

	/**
	 * (LatLon) -> Point
	 *
	 * @param float[] $latLon
	 * @return float[]
	 */
	public static function project( $latLon ) {
		$lat = max( min( self::MAX_LATITUDE, $latLon[0] ), -self::MAX_LATITUDE );
		$x = deg2rad( $latLon[1] );
		$y = deg2rad( $lat );

		$y = log( tan( ( pi() / 4 ) + ( $y / 2 ) ) );

		return [ $x, $y ];
	}

	/**
	 * (Point, Boolean) -> LatLon
	 *
	 * @param float[] $point
	 * @return float[]
	 */
	public static function unproject( $point ) {
		$lon = rad2deg( $point[0] );
		$lat = rad2deg( 2 * atan( exp( $point[1] ) ) - ( pi() / 2 ) );

		return [ $lat, $lon ];
	}
}
