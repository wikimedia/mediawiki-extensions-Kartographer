<?php

namespace Kartographer;

use Language;

/**
 * Formats coordinates into human-readable strings
 *
 * @license MIT
 */
class CoordFormatter {

	/**
	 * Formats coordinates
	 *
	 * @param float $lat
	 * @param float $lon
	 * @param Language|string $language
	 *
	 * @return string
	 */
	public static function format( $lat, $lon, $language ): string {
		$latStr = self::formatOneCoord( $lat, 'lat', $language );
		$lonStr = self::formatOneCoord( $lon, 'lon', $language );

		return wfMessage( 'kartographer-coord-combined' )
			->params( $latStr, $lonStr )
			->inLanguage( $language )
			->plain();
	}

	/**
	 * @param float $coord
	 * @param string $latLon 'lat' or 'lon'
	 * @param Language|string $language
	 * @return string
	 */
	private static function formatOneCoord( $coord, string $latLon, $language ): string {
		$val = $sign = round( $coord * 3600 );
		$val = abs( $val );
		$degrees = floor( $val / 3600 );
		$minutes = floor( ( $val - $degrees * 3600 ) / 60 );
		$seconds = $val - $degrees * 3600 - $minutes * 60;
		$plusMinus = $sign < 0 ? 'negative' : 'positive';
		$text = wfMessage( 'kartographer-coord-dms' )
			->numParams( $degrees, $minutes, round( $seconds ) )
			->inLanguage( $language )
			->plain();

		// Messages that can be used here:
		// * kartographer-coord-lat-positive
		// * kartographer-coord-lat-negative
		// * kartographer-coord-lon-positive
		// * kartographer-coord-lon-negative
		return wfMessage( "kartographer-coord-$latLon-$plusMinus" )
			->params( $text )
			->inLanguage( $language )
			->plain();
	}
}
