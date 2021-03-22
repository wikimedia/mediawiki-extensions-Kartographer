<?php
/**
 * Provides external links information to ResourceLoader module ext.kartographer.dialog.sidebar
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use Exception;
use FormatJson;
use ResourceLoaderContext;
use RuntimeException;
use stdClass;

class ExternalLinksProvider {

	/**
	 * @param ResourceLoaderContext $context
	 *
	 * @return stdClass|array
	 */
	public static function getData( ResourceLoaderContext $context ) {
		$json = file_get_contents( __DIR__ . '/../externalLinks.json' );
		if ( !$json ) {
			throw new RuntimeException( 'Error reading externalLinks.json' );
		}
		$status = FormatJson::parse( $json, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );

		if ( !$status->isOK() ) {
			$message = $status->getMessage( false, false, 'en' )->text();
			throw new Exception( "Unable to parse externalLinks.json: $message" );
		}

		$data = $status->getValue();
		$allTypes = [];

		foreach ( $data->services as $service ) {
			$service->name = $context->msg( 'kartographer-link-' . $service->id )->plain();

			foreach ( $service->links as $link ) {
				$allTypes[ $link->type ] = true;
			}
		}

		$allTypes = array_keys( $allTypes );
		$data->types = array_unique( array_merge( $data->types, $allTypes ) );

		$data->localization = [];
		foreach ( $allTypes as $type ) {
			$data->localization[$type] = $context->msg( 'kartographer-linktype-' . $type )->plain();
		}

		return $data;
	}
}
