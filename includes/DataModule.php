<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderModule;

class DataModule extends ResourceLoaderModule {

	protected $origin = self::ORIGIN_USER_SITEWIDE;
	protected $targets = array( 'desktop', 'mobile' );

	public function getScript( ResourceLoaderContext $context ) {
		$config = $context->getResourceLoader()->getConfig();
		return ResourceLoader::makeConfigSetScript( array(
			'wgKartographerMapServer' => $config->get( 'KartographerMapServer' ),
			'wgKartographerIconServer' => $config->get( 'KartographerIconServer' ),
			'wgKartographerSrcsetScales' => $config->get( 'KartographerSrcsetScales' ),
		) );
	}

	public function enableModuleContentVersion() {
		return true;
	}
}
