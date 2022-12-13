<?php

namespace Kartographer;

use FormatJson;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use stdClass;

/**
 * Gets external data in GeoJSON on parse time
 *
 * @license MIT
 */
class ExternalDataLoader {
	/** @var HttpRequestFactory */
	private HttpRequestFactory $requestFactory;

	/**
	 * @param HttpRequestFactory $requestFactory
	 */
	public function __construct( HttpRequestFactory $requestFactory ) {
		$this->requestFactory = $requestFactory;
	}

	/**
	 * Parses external data in a GeoJSON
	 *
	 * @param stdClass[] &$geoJson
	 */
	public function parse( array &$geoJson ) {
		foreach ( $geoJson as &$data ) {
			if ( !isset( $data->type ) || !isset( $data->service ) ||
				$data->type !== 'ExternalData' ||
				$data->service === 'page'
			) {
				continue;
			}

			$data = $this->extend( $data );
			if ( $data->service === 'geomask' ) {
				$data = $this->handleMaskGeoData( $data );
			}
		}
	}

	/**
	 * Creates a mask out of a shape
	 *
	 * @param stdClass $data
	 * @return stdClass
	 */
	public function handleMaskGeoData( stdClass $data ): stdClass {
		// Mask-out the entire world 10 times east and west,
		// and add each result geometry as a hole
		$coordinates = [ [
			[ 3600, -180 ],
			[ 3600, 180 ],
			[ -3600, 180 ],
			[ -3600, -180 ]
		] ];

		for ( $i = 0; $i < count( $data->features ); $i++ ) {
			$geometry = $data->features[ $i ]->geometry ?? null;
			if ( !$geometry ) {
				continue;
			}

			switch ( $geometry->type ) {
				case 'Polygon':
					array_push( $coordinates, $geometry->coordinates[ 0 ] );
					break;
				case 'MultiPolygon':
					for ( $j = 0; $j < count( $geometry->coordinates ); $j++ ) {
						array_push( $coordinates, $geometry->coordinates[ $j ][ 0 ] );
					}
					break;
			}
		}

		unset( $data->features );

		$data->type = 'Feature';
		$data->geometry = [
			'type' => 'Polygon',
			'coordinates' => $coordinates
		];

		return $data;
	}

	/**
	 * Makes query to get external data
	 *
	 * @param stdClass $geoJson
	 * @return stdClass
	 */
	public function extend( stdClass $geoJson ): stdClass {
		if ( !isset( $geoJson->type ) || $geoJson->type !== 'ExternalData' ) {
			return $geoJson;
		}
		$originalGeoJson = $geoJson;
		$originalProperties = $geoJson->properties ?? [];

		$request = $this->requestFactory->create( $geoJson->url, [ 'method' => 'GET' ], __METHOD__ );
		$status = $request->execute();

		if ( $status->isOK() ) {
			$extendedGeoJson = FormatJson::decode( $request->getContent(), false );

			if ( (array)$originalProperties !== [] ) {
				foreach ( $extendedGeoJson->features as $feature ) {

					$feature->properties = empty( $feature->properties ) ? $originalProperties :
						(object)array_merge( (array)$originalProperties, (array)$feature->properties );

				}
			}

			return (object)array_merge( (array)$originalGeoJson, (array)$extendedGeoJson );

		}
		LoggerFactory::getInstance( 'Kartographer' )->warning(
			'Could not extend external data {url}',
			[
				'url' => $geoJson->url
			] );
		return $geoJson;
	}
}
