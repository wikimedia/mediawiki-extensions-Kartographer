<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\MapTagArgumentValidator;
use Language;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapTagArgumentValidator
 * @group Kartographer
 * @license MIT
 */
class MapTagArgumentValidatorTest extends MediaWikiUnitTestCase {

	public function testBasicFunctionality() {
		$language = $this->createMock( Language::class );
		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '100%',
			'height' => '300',
			'align' => 'center',
			'frameless' => '',
			'group' => 'hotels',
		], $this->getConfig(), $language );

		$this->assertStatusGood( $args->status );
		$this->assertNull( $args->lat );
		$this->assertNull( $args->lon );
		$this->assertNull( $args->zoom );
		$this->assertSame( 'custom', $args->mapStyle );
		$this->assertSame( 'full', $args->width );
		$this->assertSame( 300, $args->height );
		$this->assertSame( 'none', $args->align );
		$this->assertTrue( $args->frameless );
		$this->assertSame( '', $args->cssClass );
		$this->assertNull( $args->specifiedLangCode );
		$this->assertSame( 'local', $args->getLanguageCodeWithDefaultFallback() );
		$this->assertNull( $args->text );
		$this->assertSame( 'hotels', $args->groupId );
		$this->assertSame( [ 'hotels' ], $args->showGroups );
		$this->assertTrue( $args->usesAutoPosition() );
	}

	public function testRequiredAttributes() {
		$language = $this->createMock( Language::class );
		$args = new MapTagArgumentValidator( 'mapframe', [], $this->getConfig(), $language );

		$this->assertStatusError( 'kartographer-error-missing-attr', $args->status );
	}

	public function testInvalidCoordinatePair() {
		$language = $this->createMock( Language::class );
		$args = new MapTagArgumentValidator( '', [
			'latitude' => 0,
		], $this->getConfig(), $language );

		$this->assertStatusError( 'kartographer-error-latlon', $args->status );
	}

	public function testInvalidAlignment() {
		$language = $this->createMock( Language::class );
		$language->method( 'alignEnd' )->willReturn( 'left' );

		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '200',
			'height' => '200',
			'align' => 'invalid',
		], $this->getConfig(), $language );

		$this->assertSame( 'left', $args->align );
		$this->assertStatusError( 'kartographer-error-bad_attr', $args->status );
	}

	private function getConfig(): Config {
		return new HashConfig( [
			'KartographerDfltStyle' => 'custom',
			'KartographerStyles' => [],
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageMode' => true,
		] );
	}

}
