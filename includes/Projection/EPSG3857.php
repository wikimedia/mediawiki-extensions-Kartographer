<?php

namespace Kartographer\Projection;

class EPSG3857 {

	private const MAX_LATITUDE = 85.0511287798;
	private const A = 0.159154943;

	/**
	 * EPSG3857 (Spherical Mercator) is the most common CRS for web mapping and is used by Leaflet
	 * by default. Converted to PHP from L.CRS.EPSG3857 (leaflet.js)
	 *
	 * @param float[] $latLon Latitude (north–south) and longitude (east-west) in degree.
	 * @return float[] Point (x, y)
	 */
	public static function latLonToPoint( array $latLon ): array {
		$projectedPoint = self::projectToSphericalMercator( $latLon );
		$scale = 256;

		return self::pointTransformation( $projectedPoint, $scale );
	}

	/**
	 * Spherical Mercator is the most popular map projection, used by EPSG:3857 CRS. Converted to
	 * PHP from L.Projection.SphericalMercator (leaflet.js)
	 *
	 * @param float[] $latLon Latitude (north–south) and longitude (east-west) in degree. Latitude
	 *  is truncated between approx. -85.05° and 85.05°. Longitude should be -180 to 180°, but is
	 *  not limited.
	 * @return float[] Point (x, y)
	 */
	private static function projectToSphericalMercator( array $latLon ): array {
		$lat = max( min( self::MAX_LATITUDE, $latLon[0] ), -self::MAX_LATITUDE );
		$x = deg2rad( $latLon[1] );
		$y = deg2rad( $lat );

		$y = log( tan( ( pi() / 4 ) + ( $y / 2 ) ) );

		return [ $x, $y ];
	}

	/**
	 * Performs a simple point transformation through a 2d-matrix. Converted to PHP from
	 * L.Transformation (leaflet.js)
	 *
	 * @param float[] $point
	 * @param int $scale
	 * @return float[] Point (x, y)
	 */
	private static function pointTransformation( array $point, int $scale ): array {
		return [
			$scale * ( self::A * $point[0] + 0.5 ),
			$scale * ( -self::A * $point[1] + 0.5 )
		];
	}

}
