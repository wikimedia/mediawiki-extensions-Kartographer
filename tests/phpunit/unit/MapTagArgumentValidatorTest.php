<?php

namespace Kartographer\UnitTests;

use HashConfig;
use Kartographer\Tag\MapTagArgumentValidator;
use Language;
use MediaWikiUnitTestCase;

/**
 * @covers \Kartographer\Tag\MapTagArgumentValidator
 * @group Kartographer
 * @license MIT
 */
class MapTagArgumentValidatorTest extends MediaWikiUnitTestCase {

	public function testBasicFunctionality() {
		$config = new HashConfig( [
			'KartographerDfltStyle' => 'custom',
			'KartographerStyles' => [],
			'KartographerUsePageLanguage' => false,
			'KartographerWikivoyageMode' => true,
		] );
		$language = $this->createMock( Language::class );
		$args = new MapTagArgumentValidator( 'mapframe', [
			'width' => '100%',
			'height' => '300',
			'align' => 'center',
			'frameless' => '',
			'group' => 'hotels',
		], $config, $language );

		$this->assertTrue( $args->status->isGood() );
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
		$this->assertSame( 'local', $args->resolvedLangCode );
		$this->assertNull( $args->text );
		$this->assertSame( 'hotels', $args->groupId );
		$this->assertSame( [ 'hotels' ], $args->showGroups );
		$this->assertTrue( $args->usesAutoPosition() );
	}

}
