<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer;

use ExtensionRegistry;
// phpcs:disable MediaWiki.Classes.FullQualifiedClassName -- T308814
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;

class DataModule extends RL\Module {

	/** @inheritDoc */
	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * @inheritDoc
	 */
	public function getScript( RL\Context $context ) {
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
	 * @see RL\Module::supportsURLLoading
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
