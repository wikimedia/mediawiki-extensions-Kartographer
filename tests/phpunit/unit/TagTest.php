<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\Tag;
use MediaWikiUnitTestCase;
use StatusValue;

/**
 * @covers \Kartographer\Tag\Tag
 * @group Kartographer
 * @license MIT
 */
class TagTest extends MediaWikiUnitTestCase {

	public function testGetInt() {
		$status = new StatusValue();
		$args = new Tag( '', [ 'key' => '2', 'bad' => '0.3' ], $status );

		$this->assertSame( 2, $args->getInt( 'key' ) );
		$this->assertNull( $args->getInt( 'missing' ) );
		$this->assertTrue( $status->isGood() );

		$this->assertNull( $args->getInt( 'bad' ) );
		$this->assertTrue( $status->hasMessage( 'kartographer-error-bad_attr' ) );
	}

	public function testGetFloat() {
		$status = new StatusValue();
		$args = new Tag( '', [ 'key' => '0.3', 'bad' => 'a' ], $status );

		$this->assertSame( 0.3, $args->getFloat( 'key' ) );
		$this->assertNull( $args->getFloat( 'missing' ) );
		$this->assertTrue( $status->isGood() );

		$this->assertNull( $args->getFloat( 'bad' ) );
		$this->assertTrue( $status->hasMessage( 'kartographer-error-bad_attr' ) );
	}

	public function testGetString() {
		$status = new StatusValue();
		$args = new Tag( '', [ 'key' => 'a' ], $status );

		$this->assertTrue( $args->has( 'key' ) );
		$this->assertFalse( $args->has( 'missing' ) );

		$this->assertSame( 'a', $args->getString( 'key' ) );
		$this->assertNull( $args->getString( 'missing' ) );
		$this->assertTrue( $status->isGood() );

		$this->assertNull( $args->getString( 'key', '/z/' ) );
		$this->assertTrue( $status->hasMessage( 'kartographer-error-bad_attr' ) );
	}

}
