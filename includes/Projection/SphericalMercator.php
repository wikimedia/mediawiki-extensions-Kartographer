<?php

namespace Kartographer\Projection;

/**
 * Spherical Mercator is the most popular map projection,
 * used by EPSG:3857 CRS.
 *
 * Converted to PHP from L.Projection.SphericalMercator (leaflet.js)
 */
class SphericalMercator {

	private const MAX_LATITUDE = 85.0511287798;

	/**
	 * (LatLon) -> Point
	 *
	 * @param float[] $latLon Latitude (north–south) and longitude (east-west) in degree. Latitude
	 *  is truncated between approx. -85.05° and 85.05°. Longitude should be -180 to 180°, but is
	 *  not limited.
	 * @return float[]
	 */
	public static function project( $latLon ): array {
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
	 * @return float[] Latitude (north–south) and longitude (east-west) in degree.
	 */
	public static function unproject( $point ): array {
		$lon = rad2deg( $point[0] );
		$lat = rad2deg( 2 * atan( exp( $point[1] ) ) - ( pi() / 2 ) );

		return [ $lat, $lon ];
	}
}
