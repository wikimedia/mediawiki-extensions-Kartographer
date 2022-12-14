<?php

namespace Kartographer\UnitTests;

use Kartographer\Tag\ParserFunctionTracker;
use MediaWikiUnitTestCase;
use Parser;

/**
 * @covers \Kartographer\Tag\ParserFunctionTracker
 * @group Kartographer
 * @license MIT
 */
class ParserFunctionTrackerTest extends MediaWikiUnitTestCase {

	public function testBasicFunctionality() {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->exactly( 2 ) )
			->method( 'incrementExpensiveFunctionCount' )
			->willReturnOnConsecutiveCalls( true, false );
		$parser->expects( $this->once() )
			->method( 'addTrackingCategory' )
			->with( 'foo' );

		$counter = new ParserFunctionTracker( $parser );

		$this->assertTrue( $counter->incrementExpensiveFunctionCount() );
		$this->assertFalse( $counter->incrementExpensiveFunctionCount() );

		$counter->addTrackingCategories( [ 'foo' => true, 'bar' => false ] );
	}

}
