<?php

namespace Kartographer\UnitTests;

use Kartographer\ParserFunctionTracker;
use MediaWikiUnitTestCase;
use Parser;

/**
 * @covers \Kartographer\ParserFunctionTracker
 * @group Kartographer
 * @license MIT
 */
class ParserFunctionTrackerTest extends MediaWikiUnitTestCase {

	public function testBasicFunctionality() {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( 'foo' );

		$counter = new ParserFunctionTracker( $parser );

		$counter->addTrackingCategories( [ 'foo' => true, 'bar' => false ] );
	}

}
