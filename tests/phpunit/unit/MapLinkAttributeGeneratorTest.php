<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\MapLinkAttributeGenerator;
use Kartographer\Tag\MapTagArgumentValidator;
use MediaWiki\Config\HashConfig;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Kartographer\Tag\MapLinkAttributeGenerator
 * @covers \Kartographer\Tag\MapTagArgumentValidator
 * @group Kartographer
 * @license MIT
 */
class MapLinkAttributeGeneratorTest extends MediaWikiUnitTestCase {

	private function createArgs( string $color ): MapTagArgumentValidator {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
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
		], $config, $language, $languageNameUtils );
		$args->setFirstMarkerProperties( null, (object)[ 'marker-color' => $color ] );
		return $args;
	}

	public function testContainerAttributeGeneration() {
		$args = $this->createArgs( '#F00' );
		$generator = new MapLinkAttributeGenerator( $args );

		$attrs = $generator->prepareAttrs();
		$this->assertSame( [
			'class' => [ 'mw-kartographer-maplink', 'mw-kartographer-autostyled', 'custom-class', 'error' ],
			'data-mw-kartographer' => 'maplink',
			'data-style' => 'custom',
			'href' => null,
			'data-zoom' => '12',
			'style' => 'background-color: #f00; color: #fff;',
			'data-overlays' => '["hotels"]',
		], $attrs );
	}

	/**
	 * @dataProvider provideMarkerColors
	 */
	public function testContrastingFill( string $background, string $expected ) {
		$args = $this->createArgs( $background );
		/** @var MapLinkAttributeGenerator $generator */
		$generator = TestingAccessWrapper::newFromObject( new MapLinkAttributeGenerator( $args ) );
		$this->assertSame(
			// For this test we don't care which exact white and black are actually used
			$expected === 'black' ? '#202122' : '#fff',
			$generator->contrastingFill( $background )
		);
	}

	public function provideMarkerColors() {
		return [
			// Semi-problematic edge-cases that shouldn't be possible, just to cover the behavior
			[ '', 'white' ],
			[ '#', 'white' ],
			[ '#f', 'white' ],
			[ '#ff', 'white' ],
			[ '#ffff', 'black' ],
			[ '#fffff', 'black' ],

			// Threshold for pure blue
			[ '#9d9dff', 'white' ],
			[ '#9e9eff', 'black' ],

			// Threshold for pure green
			[ '#00c200', 'white' ],
			[ '#00c300', 'black' ],

			// Threshold for pure cyan
			[ '#00b9b9', 'white' ],
			[ '#00baba', 'black' ],

			// Threshold for pure red
			[ '#ff8181', 'white' ],
			[ '#ff8282', 'black' ],

			// Threshold for pure magenta
			[ '#ff69ff', 'white' ],
			[ '#ff6aff', 'black' ],

			// Threshold for pure yellow
			[ '#acac00', 'white' ],
			[ '#adad00', 'black' ],

			// Threshold for pure white (gray)
			[ '#a6a6a6', 'white' ],
			[ '#a7a7a7', 'black' ],
		];
	}

}
