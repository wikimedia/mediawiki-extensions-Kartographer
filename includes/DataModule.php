<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use ExtensionRegistry;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderModule;

class DataModule extends ResourceLoaderModule {

	/** @inheritDoc */
	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		$config = $this->getConfig();
		return ResourceLoader::makeConfigSetScript( [
			'wgKartographerMapServer' => $config->get( 'KartographerMapServer' ),
			'wgKartographerVersionedLiveMaps' => $config->get( 'KartographerVersionedLiveMaps' ),
			'wgKartographerSrcsetScales' => $config->get( 'KartographerSrcsetScales' ),
			'wgKartographerStyles' => $config->get( 'KartographerStyles' ),
			'wgKartographerDfltStyle' => $config->get( 'KartographerDfltStyle' ),
			'wgKartographerEnableMapFrame' => $config->get( 'KartographerEnableMapFrame' ),
			'wgKartographerUsePageLanguage' => $config->get( 'KartographerUsePageLanguage' ),
			'wgKartographerFallbackZoom' => $config->get( 'KartographerFallbackZoom' ),
			'wgKartographerSimpleStyleMarkers' => $config->get( 'KartographerSimpleStyleMarkers' ),
			'wgKartographerNearby' => $this->canUseNearby(),
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function enableModuleContentVersion() {
		return true;
	}

	/**
	 * @see ResourceLoaderModule::supportsURLLoading
	 *
	 * @return bool
	 */
	public function supportsURLLoading() {
		// always use getScript() to acquire JavaScript (even in debug mode)
		return false;
	}

	/**
	 * @return bool
	 */
	private function canUseNearby() {
		return $this->getConfig()->get( 'KartographerNearby' ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'GeoData' ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' );
	}
}
