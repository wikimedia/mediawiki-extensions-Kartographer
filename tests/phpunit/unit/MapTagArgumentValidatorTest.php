<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\MapTagArgumentValidator;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapTagArgumentValidator
 * @group Kartographer
 * @license MIT
 */
class MapTagArgumentValidatorTest extends MediaWikiUnitTestCase {

	public function testBasicFunctionality() {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '100%',
			'height' => '300',
			'align' => 'center',
			'frameless' => '',
			'group' => 'hotels',
		], $this->getConfig(), $language, $languageNameUtils );

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
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$args = new MapTagArgumentValidator( 'mapframe', [], $this->getConfig(), $language, $languageNameUtils );

		$this->assertStatusError( 'kartographer-error-missing-attr', $args->status );
	}

	public function testInvalidCoordinatePair() {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$args = new MapTagArgumentValidator( '', [
			'latitude' => 0,
		], $this->getConfig(), $language, $languageNameUtils );

		$this->assertStatusError( 'kartographer-error-latlon', $args->status );
	}

	public function testInvalidAlignment() {
		$language = $this->createMock( Language::class );
		$language->method( 'alignEnd' )->willReturn( 'left' );

		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '200',
			'height' => '200',
			'align' => 'invalid',
		], $this->getConfig(), $language, $languageNameUtils );

		$this->assertSame( 'left', $args->align );
		$this->assertStatusError( 'kartographer-error-bad_attr', $args->status );
	}

	public function testUserProvidedLanguage() {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$languageNameUtils->method( 'isKnownLanguageTag' )->willReturn( true );

		$args = new MapTagArgumentValidator( 'mapframe', [
			'lang' => 'hu',
		], $this->getConfig(), $language, $languageNameUtils );

		$this->assertSame( 'hu', $args->getLanguageCodeWithDefaultFallback() );
	}

	public function testUsePageLanguage() {
		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( 'fr' );
		$validator = $this->createMock( LanguageNameUtils::class );
		$validator->method( 'isKnownLanguageTag' )->willReturn( true );

		$args = new MapTagArgumentValidator( 'mapframe', [],
			$this->getConfig( [ 'KartographerUsePageLanguage' => true ] ),
			$language,
			$validator
		);

		$this->assertSame( 'fr', $args->getLanguageCodeWithDefaultFallback() );
	}

	public function testInvalidDefaultLanguage() {
		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( 'fr' );
		$validator = $this->createMock( LanguageNameUtils::class );
		$validator->method( 'isKnownLanguageTag' )->willReturn( false );

		$args = new MapTagArgumentValidator( 'mapframe', [],
			$this->getConfig( [ 'KartographerUsePageLanguage' => true ] ),
			$language,
			$validator
		);

		$this->assertSame( 'local', $args->getLanguageCodeWithDefaultFallback() );
	}

	/**
	 * @dataProvider provideShowGroups
	 */
	public function testShowGroups( string $show, ?array $expected ) {
		$language = $this->createMock( Language::class );
		$languageNameUtils = $this->createMock( LanguageNameUtils::class );
		$args = new MapTagArgumentValidator( 'maplink', [
			'show' => $show,
		], $this->getConfig(), $language, $languageNameUtils );

		if ( $expected === null ) {
			$this->assertStatusError( 'kartographer-error-bad_attr', $args->status );
		} else {
			$this->assertSame( $expected, $args->showGroups );
		}
	}

	public static function provideShowGroups() {
		return [
			'empty' => [ '', [] ],
			'duplicates' => [ 'a,a,b', [ 'a', 'b' ] ],
			'names can contain whitespace' => [ 'a b', [ 'a b' ] ],
			'comma separates names' => [ 'a b,c', [ 'a b', 'c' ] ],
			'all whitespace is trimmed' => [ ' a , b ', [ 'a', 'b' ] ],
			'empty name is not allowed' => [ 'a,,b', null ],
			'whitespace-only name is not allowed' => [ 'a, ,b', null ],
			'comma at the start' => [ ' ,a', null ],
			'comma at the end' => [ 'a, ', null ],
		];
	}

	private function getConfig( array $settings = [] ): Config {
		return new HashConfig( $settings + [
			'KartographerDfltStyle' => 'custom',
			'KartographerStyles' => [],
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageMode' => true,
		] );
	}

}
