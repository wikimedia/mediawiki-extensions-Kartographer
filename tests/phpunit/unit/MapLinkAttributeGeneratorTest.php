<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\MapLinkAttributeGenerator;
use Kartographer\Tag\MapTagArgumentValidator;
use Language;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapLinkAttributeGenerator
 * @group Kartographer
 * @license MIT
 */
class MapLinkAttributeGeneratorTest extends MediaWikiUnitTestCase {

	public function testContainerAttributeGeneration() {
		$language = $this->createMock( Language::class );
		$config = new HashConfig( [
			'KartographerDfltStyle' => 'custom',
			'KartographerStyles' => [],
			'KartographerUseMarkerStyle' => true,
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageMode' => true,
		] );

		$args = new MapTagArgumentValidator( '', [
			'class' => 'custom-class',
			'zoom' => 12,
			'group' => 'hotels',
		], $config, $language );
		$args->setFirstMarkerProperties( null, (object)[ 'marker-color' => '#f00' ] );
		$generator = new MapLinkAttributeGenerator( $args );

		$attrs = $generator->prepareAttrs();
		$this->assertSame( [
			'class' => [ 'mw-kartographer-maplink', 'mw-kartographer-autostyled', 'custom-class', 'error' ],
			'data-mw-kartographer' => 'maplink',
			'data-style' => 'custom',
			'href' => null,
			'data-zoom' => '12',
			'style' => 'background: #f00;',
			'data-overlays' => '["hotels"]',
		], $attrs );
	}

}
