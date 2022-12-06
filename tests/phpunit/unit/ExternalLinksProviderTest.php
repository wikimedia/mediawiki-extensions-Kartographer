<?php

namespace Kartographer\UnitTests;

use Kartographer\ExternalLinksProvider;
use MediaWiki\ResourceLoader\Context;

/**
 * @covers \Kartographer\ExternalLinksProvider
 * @group Kartographer
 * @license MIT
 */
class ExternalLinksProviderTest extends \MediaWikiUnitTestCase {

	public function testGetData() {
		$context = $this->createMock( Context::class );
		$context->method( 'msg' )->willReturnCallback( function ( $key ) {
			$msg = $this->createMock( \Message::class );
			$msg->method( 'plain' )->willReturn( "<$key>" );
			return $msg;
		} );

		$data = ExternalLinksProvider::getData( $context );

		$this->assertInstanceOf( \stdClass::class, $data );
		$this->assertIsArray( $data->types );
		// TODO: Can be replaced with array_is_list() when we can use PHP 8.1
		$this->assertSame( array_values( $data->types ), $data->types );
		$this->assertIsArray( $data->localization );
		$this->assertLessThanOrEqual( count( $data->types ), count( $data->localization ) );

		// Check for an example value
		$this->assertContains( 'map', $data->types );
		$this->assertSame( '<kartographer-linktype-map>', $data->localization['map'] );
	}

}
