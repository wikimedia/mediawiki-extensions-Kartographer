<?php

namespace Kartographer\UnitTests;

use HashConfig;
use Kartographer\Tag\MapLinkAttributeGenerator;
use Kartographer\Tag\MapTagArgumentValidator;
use Language;
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
		$markerProperties = (object)[ 'marker-color' => '#f00' ];

		$args = new MapTagArgumentValidator( '', [
			'class' => 'custom-class',
			'zoom' => 12,
			'group' => 'hotels',
		], $config, $language );
		$generator = new MapLinkAttributeGenerator( $args, $config, $markerProperties );

		$attrs = $generator->prepareAttrs();
		$this->assertSame( [
			'class' => [ 'mw-kartographer-maplink', 'mw-kartographer-autostyled', 'custom-class' ],
			'data-mw-kartographer' => 'maplink',
			'data-style' => 'custom',
			'href' => null,
			'data-zoom' => '12',
			'style' => 'background: #f00;',
			'data-overlays' => '["hotels"]',
		], $attrs );
	}

}
