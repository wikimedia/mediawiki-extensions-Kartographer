<?php

namespace Kartographer;

use FormatJson;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use stdClass;

/**
 * Gets external data in GeoJSON on parse time
 */
class ExternalDataLoader {

	/**
	 * Parses external data in a GeoJSON
	 *
	 * @param stdClass[] &$geoJson
	 */
	public function parse( array &$geoJson ) {
		foreach ( $geoJson as &$element ) {
			$element = $this->extend( $element );
		}
	}

	/**
	 * Makes query to get external data
	 *
	 * @param stdClass $geoJson
	 * @return stdClass
	 */
	private function extend( stdClass $geoJson ): stdClass {
		if ( !isset( $geoJson->type ) || $geoJson->type !== 'ExternalData' ) {
			return $geoJson;
		}

		$request = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $geoJson->url, [ 'method' => 'GET' ], __METHOD__ );
		$status = $request->execute();

		if ( $status->isOK() ) {
			$extendedGeoJson = FormatJson::decode( $request->getContent(), false );

			if ( $extendedGeoJson !== null ) {
				return $extendedGeoJson;
			}

			LoggerFactory::getInstance( 'Kartographer' )->warning(
				'Could not extend external data {url}',
				[
					'url' => $geoJson->url
				] );

			return $geoJson;

		}
	}
}
