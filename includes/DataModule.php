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

	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		$config = $context->getResourceLoader()->getConfig();
		return ResourceLoader::makeConfigSetScript( [
			'wgKartographerMapServer' => $config->get( 'KartographerMapServer' ),
			'wgKartographerIconServer' => $config->get( 'KartographerIconServer' ),
			'wgKartographerSrcsetScales' => $config->get( 'KartographerSrcsetScales' ),
			'wgKartographerStyles' => $config->get( 'KartographerStyles' ),
			'wgKartographerDfltStyle' => $config->get( 'KartographerDfltStyle' ),
			'wgKartographerEnableMapFrame' => $config->get( 'KartographerEnableMapFrame' ),
			'wgKartographerUsePageLanguage' => $config->get( 'KartographerUsePageLanguage' ),
		] );
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
