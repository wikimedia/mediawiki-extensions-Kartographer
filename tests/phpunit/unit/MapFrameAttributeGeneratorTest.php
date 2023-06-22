<?php

namespace Kartographer\UnitTests;

use HashConfig;
use Kartographer\Tag\MapFrameAttributeGenerator;
use Kartographer\Tag\MapTagArgumentValidator;
use Language;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapFrameAttributeGenerator
 * @group Kartographer
 * @license MIT
 */
class MapFrameAttributeGeneratorTest extends MediaWikiUnitTestCase {

	public function testContainerAndImageAttributeGeneration() {
		$language = $this->createMock( Language::class );
		$config = new HashConfig( [
			'KartographerDfltStyle' => 'custom',
			'KartographerMapServer' => '',
			'KartographerMediaWikiInternalUrl' => 'localhost',
			'KartographerSrcsetScales' => [],
			'KartographerStyles' => [],
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageMode' => true,
		] );

		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '100%',
			'height' => '300',
			'zoom' => 12,
			'group' => 'hotels',
			'frameless' => '',
		], $config, $language );
		$generator = new MapFrameAttributeGenerator( $args, $config );

		$attrs = $generator->prepareAttrs();
		$this->assertSame( [
			'class' => [ 'mw-kartographer-map', 'mw-kartographer-container', 'mw-kartographer-full' ],
			'style' => 'width: 100%; height: 300px;',
			'data-mw-kartographer' => 'mapframe',
			'data-style' => 'custom',
			'data-width' => 'full',
			'data-height' => 300,
			'data-zoom' => 12,
			'data-overlays' => '["hotels"]',
			'href' => null,
		], $attrs );

		$imgAttrs = $generator->prepareImgAttrs( false, 'X', 9 );
		$this->assertSame( [
			'src' => '/img/custom,12,a,a,800x300.png?lang=local&domain=localhost&title=X&revid=9&groups=hotels',
			'width' => 800,
			'height' => 300,
			'decoding' => 'async',
		], $imgAttrs );

		$this->assertSame( [
			'mw-kartographer-container',
			'mw-kartographer-full',
			'thumb',
			'tnone',
		], $generator->getThumbClasses() );
	}

}
