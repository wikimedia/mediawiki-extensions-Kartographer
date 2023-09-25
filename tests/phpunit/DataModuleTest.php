<?php

namespace Kartographer\Tests;

use HashConfig;
use Kartographer\Modules\DataModule;
use MediaWiki\ResourceLoader\Context;
use MediaWikiIntegrationTestCase;

/**
 * @covers \Kartographer\Modules\DataModule
 * @group Kartographer
 * @license MIT
 */
class DataModuleTest extends MediaWikiIntegrationTestCase {

	public static function provideNumberOfNearbyPoints() {
		return [
			[ 0, 0 ],
			[ 1, 1 ],
			[ 99999, 99999 ],
			[ null, 0 ],
			[ false, 0 ],
			[ true, 300 ],
		];
	}

	/**
	 * @dataProvider provideNumberOfNearbyPoints
	 */
	public function testNumberOfNearbyPoints( $nearbyConfig, int $expected ) {
		if ( $nearbyConfig ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GeoData' );
			$this->markTestSkippedIfExtensionNotLoaded( 'CirrusSearch' );
		}

		$module = new DataModule();
		$module->setConfig( new HashConfig( [
			'KartographerDfltStyle' => '',
			'KartographerFallbackZoom' => 0,
			'KartographerMapServer' => '',
			'KartographerNearby' => $nearbyConfig,
			'KartographerSimpleStyleMarkers' => null,
			'KartographerSrcsetScales' => [],
			'KartographerStyles' => [],
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageNearby' => false,
		] ) );
		$script = $module->getScript( $this->createMock( Context::class ) );
		$this->assertStringContainsString( '"wgKartographerNearby":' . $expected . ',', $script );
	}

}
