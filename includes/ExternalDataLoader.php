<?php

namespace Kartographer;

use FormatJson;
use Kartographer\Tag\ParserFunctionTracker;
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
	/** @var ParserFunctionTracker|null */
	private ?ParserFunctionTracker $tracker;

	/**
	 * @param HttpRequestFactory $requestFactory
	 * @param ParserFunctionTracker|null $tracker
	 */
	public function __construct(
		HttpRequestFactory $requestFactory,
		ParserFunctionTracker $tracker = null
	) {
		$this->requestFactory = $requestFactory;
		$this->tracker = $tracker;
	}

	/**
	 * Parses external data in a GeoJSON
	 *
	 * @param stdClass[] &$geoJson
	 */
	public function parse( array &$geoJson ) {
		foreach ( $geoJson as &$data ) {
			if ( !isset( $data->type ) || !isset( $data->service ) ||
				!isset( $data->url ) ||
				$data->type !== 'ExternalData' ||
				!$data->url
			) {
				continue;
			}

			if ( $this->tracker && !$this->tracker->incrementExpensiveFunctionCount() ) {
				continue;
			}

			$data = $this->extend( $data );
			if ( $data->service === 'geomask' && isset( $data->features ) ) {
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
	private function handleMaskGeoData( stdClass $data ): stdClass {
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
	private function extend( stdClass $geoJson ): stdClass {
		$request = $this->requestFactory->create( $geoJson->url, [ 'method' => 'GET' ], __METHOD__ );
		$startTime = microtime( true );
		$status = $request->execute();

		$elapsedTime = microtime( true ) - $startTime;
		if ( $elapsedTime > 1 ) {
			LoggerFactory::getInstance( 'Kartographer' )->warning(
				'Took too long ({time} sec) to expand {url}',
				[ 'time' => $elapsedTime, 'url' => $geoJson->url ]
			);
		}

		if ( !$status->isOK() ) {
			LoggerFactory::getInstance( 'Kartographer' )->warning(
				'Could not extend external data {url}',
				[ 'url' => $geoJson->url ]
			);
			return $geoJson;
		}

		$extendedGeoJson = FormatJson::decode( $request->getContent() );

		if ( isset( $geoJson->properties ) ) {
			foreach ( $extendedGeoJson->features as $feature ) {
				if ( isset( $feature->properties ) ) {
					$feature->properties = (object)array_merge(
						(array)$geoJson->properties,
						(array)$feature->properties
					);
				} else {
					$feature->properties = $geoJson->properties;
				}
			}
		}

		return (object)array_merge( (array)$geoJson, (array)$extendedGeoJson );
	}

}
