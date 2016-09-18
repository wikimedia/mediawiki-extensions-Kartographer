<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use FormatJson;
use Language;
use Parser;
use ParserOptions;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderModule;
use stdClass;
use Title;

class DataModuleLinks extends ResourceLoaderModule {

	protected $origin = self::ORIGIN_USER_SITEWIDE;
	protected $targets = array( 'desktop', 'mobile' );

	public function getScript( ResourceLoaderContext $context ) {
		return ResourceLoader::makeConfigSetScript( array(
			'wgKartographerExternalLinks' => $this->getExternalLinks( $context )
		) );
	}

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
		foreach ( $data->services as &$service ) {
			$service->name = $context->msg( 'kartographer-link-' . $service->name )->plain();

			foreach ( $service->links as &$link ) {
				$link->type = $context->msg( 'kartographer-linktype-' . $link->type )->plain();
			}
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
