<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\MapFrameAttributeGenerator;
use Kartographer\Tag\MapTagArgumentValidator;
use MediaWiki\Config\HashConfig;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapFrameAttributeGenerator
 * @group Kartographer
 * @license MIT
 */
class MapFrameAttributeGeneratorTest extends MediaWikiUnitTestCase {

	public function testContainerAndImageAttributeGeneration() {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$config = new HashConfig( [
			'KartographerDfltStyle' => 'custom',
			'KartographerMapServer' => '',
			'KartographerMediaWikiInternalUrl' => 'localhost',
			'KartographerSrcsetScales' => [],
			'KartographerStaticFullWidth' => 800,
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
		], $config, $language, $languageNameUtils );
		$generator = new MapFrameAttributeGenerator( $args, $config );

		$attrs = $generator->prepareAttrs();
		$this->assertSame( [
			'class' => [ 'mw-kartographer-map', 'notheme', 'mw-kartographer-container', 'mw-kartographer-full' ],
			'style' => 'width: 100%; height: 300px;',
			'data-mw-kartographer' => 'mapframe',
			'data-style' => 'custom',
			'data-width' => 'full',
			'data-height' => 300,
			'data-zoom' => 12,
			'data-overlays' => '["hotels"]',
			'href' => null,
		], $attrs );

		$imgAttrs = $generator->prepareImgAttrs( true, 'X', 9 );
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
