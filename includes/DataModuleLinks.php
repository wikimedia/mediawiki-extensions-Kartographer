<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use FormatJson;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderModule;

class DataModuleLinks extends ResourceLoaderModule {

	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return ResourceLoader::makeConfigSetScript( [
			'wgKartographerExternalLinks' => $this->getExternalLinks( $context )
		] );
	}

	/**
	 * @param ResourceLoaderContext $context
	 *
	 * @return \stdClass|array
	 */
	public function getExternalLinks( ResourceLoaderContext $context ) {
		$st = FormatJson::parse(
			file_get_contents( __DIR__ . '/../externalLinks.json' ),
			FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS
		);

		if ( !$st->isOK() ) {
			wfWarn( 'Unable to parse externalLinks.json' );
			return [];
		}

		$data = $st->getValue();
		$allTypes = [];

		foreach ( $data->services as &$service ) {
			$service->name = $context->msg( 'kartographer-link-' . $service->id )->plain();

			foreach ( $service->links as &$link ) {
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

	public function enableModuleContentVersion() {
		return true;
	}

	/**
	 * @see ResourceLoaderModule::supportsURLLoading
	 *
	 * @return bool
	 */
	public function supportsURLLoading() {
		return false; // always use getScript() to acquire JavaScript (even in debug mode)
	}
}
