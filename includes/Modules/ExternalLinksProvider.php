<?php
/**
 * Provides external links information to ResourceLoader module ext.kartographer.dialog.sidebar
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer\Modules;

use MediaWiki\Json\FormatJson;
use MediaWiki\ResourceLoader\Context;
use RuntimeException;
use stdClass;

/**
 * @license MIT
 */
class ExternalLinksProvider {

	/**
	 * @param Context $context
	 *
	 * @return stdClass
	 */
	public static function getData( Context $context ) {
		$json = file_get_contents( __DIR__ . '/../../externalLinks.json' );
		if ( !$json ) {
			throw new RuntimeException( 'Error reading externalLinks.json' );
		}
		$status = FormatJson::parse( $json, FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS );

		if ( !$status->isOK() ) {
			$message = $status->getMessage( false, false, 'en' )->text();
			throw new RuntimeException( "Unable to parse externalLinks.json: $message" );
		}

		$data = $status->getValue();
		$usedTypes = [];

		foreach ( $data->services as $service ) {
			$service->name = $context->msg( 'kartographer-link-' . $service->id )->plain();

			foreach ( $service->links as $link ) {
				$usedTypes[$link->type] = true;
			}
		}

		$data->types = array_keys( array_merge( array_flip( $data->types ), $usedTypes ) );

		$data->localization = [];
		foreach ( $usedTypes as $type => $_ ) {
			$data->localization[$type] = $context->msg( 'kartographer-linktype-' . $type )->plain();
		}

		return $data;
	}
}
