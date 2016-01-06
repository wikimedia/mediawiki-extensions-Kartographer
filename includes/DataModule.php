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
use ResourceLoaderFileModule;

class DataModule extends ResourceLoaderFileModule {

	public function getScript( ResourceLoaderContext $context ) {
		$config = $context->getResourceLoader()->getConfig();
		return ResourceLoader::makeConfigSetScript( array(
			'wgKartographerMapServer' => $config->get( 'KartographerMapServer' ),
			'wgKartographerIconServer' => $config->get( 'KartographerIconServer' ),
			'wgKartographerSrcsetScales' => $config->get( 'KartographerSrcsetScales' ),
		) );
	}
}
