<?php
/**
 * ResourceLoader module providing extra data to the client-side.
 *
 * @file
 * @ingroup Extensions
 */

namespace Kartographer\Modules;

use MediaWiki\Config\ConfigException;
use MediaWiki\Registration\ExtensionRegistry;
// phpcs:disable MediaWiki.Classes.FullQualifiedClassName -- T308814
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;

/**
 * @license MIT
 */
class DataModule extends RL\Module {

	/** @inheritDoc */
	public function getScript( RL\Context $context ) {
		$config = $this->getConfig();
		return ResourceLoader::makeConfigSetScript( [
			'wgKartographerMapServer' => $config->get( 'KartographerMapServer' ),
			'wgKartographerStaticFullWidth' => $config->get( 'KartographerStaticFullWidth' ),
			'wgKartographerSrcsetScales' => $config->get( 'KartographerSrcsetScales' ),
			'wgKartographerStyles' => $config->get( 'KartographerStyles' ),
			'wgKartographerDfltStyle' => $config->get( 'KartographerDfltStyle' ),
			'wgKartographerUsePageLanguage' => $config->get( 'KartographerUsePageLanguage' ),
			'wgKartographerFallbackZoom' => $config->get( 'KartographerFallbackZoom' ),
			'wgKartographerSimpleStyleMarkers' => $config->get( 'KartographerSimpleStyleMarkers' ),
			'wgKartographerNearby' => $this->numberOfNearbyPoints(),
		] );
	}

	/** @inheritDoc */
	public function enableModuleContentVersion() {
		return true;
	}

	/** @inheritDoc */
	public function supportsURLLoading() {
		// always use getScript() to acquire JavaScript (even in debug mode)
		return false;
	}

	/**
	 * @return int Number of points to load, 0 when the feature is disabled
	 */
	private function numberOfNearbyPoints(): int {
		$limit = $this->getConfig()->get( 'KartographerNearby' );
		if ( !$limit ) {
			return 0;
		}

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'GeoData' ) ||
			!ExtensionRegistry::getInstance()->isLoaded( 'CirrusSearch' )
		) {
			throw new ConfigException( '$wgKartographerNearby requires GeoData and CirrusSearch extensions' );
		}

		return $limit === true ? 300 : (int)$limit;
	}

}
