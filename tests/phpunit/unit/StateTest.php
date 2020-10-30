<?php

namespace Kartographer\UnitTests;

use Kartographer\State;
use MediaWikiUnitTestCase;
use ParserOutput;

/**
 * @covers \Kartographer\State
 * @group Kartographer
 */
class StateTest extends MediaWikiUnitTestCase {

	public function testValidTags() {
		$state = new State();
		$this->assertFalse( $state->hasValidTags() );

		$state->setValidTags();
		$this->assertTrue( $state->hasValidTags() );
	}

	public function testBrokenTags() {
		$state = new State();
		$this->assertFalse( $state->hasBrokenTags() );

		$state->setBrokenTags();
		$this->assertTrue( $state->hasBrokenTags() );
	}

	public function testMapLinks() {
		$state = new State();
		$this->assertSame( 0, $state->getMaplinks() );

		$state->useMaplink();
		$this->assertSame( 1, $state->getMaplinks() );

		$state->useMaplink();
		$this->assertSame( 2, $state->getMaplinks() );
	}

	public function testMapframes() {
		$state = new State();
		$this->assertSame( 0, $state->getMapframes() );

		$state->useMapframe();
		$this->assertSame( 1, $state->getMapframes() );

		$state->useMapframe();
		$this->assertSame( 2, $state->getMapframes() );
	}

	public function testInteractiveGroups() {
		$state = new State();
		$this->assertSame( [], $state->getInteractiveGroups() );

		$state->addInteractiveGroups( [ 'a', 'b' ] );
		$this->assertSame( [ 'a', 'b' ], $state->getInteractiveGroups() );

		$state->addInteractiveGroups( [ 'b', 'c' ] );
		$this->assertSame( [ 'a', 'b', 'c' ], $state->getInteractiveGroups() );
	}

	public function testRequestedGroups() {
		$state = new State();
		$this->assertSame( [], $state->getRequestedGroups() );

		$state->addRequestedGroups( [ 6 => 'a', 7 => 'b' ] );
		$this->assertSame( [ 'a' => 6, 'b' => 7 ], $state->getRequestedGroups() );

		$state->addRequestedGroups( [ 11 => 'b',  22 => 'c' ] );
		$this->assertSame( [ 'a' => 6, 'b' => 7, 'c' => 22 ], $state->getRequestedGroups() );
	}

	public function testCounters() {
		$state = new State();
		$this->assertEquals( (object)[], $state->getCounters() );

		$state->setCounters( (object)[ 'a' => 11, 'b' => 22 ] );
		$this->assertEquals( (object)[ 'a' => 11, 'b' => 22 ], $state->getCounters() );

		$state->setCounters( (object)[ 'b' => 33, 'c' => 55 ] );
		$this->assertEquals( (object)[ 'b' => 33, 'c' => 55 ], $state->getCounters() );
	}

	public function testData() {
		$state = new State();
		$this->assertSame( [], $state->getData() );

		$state->addData( 'x', [ 'a' => 11, 'b' => 22 ] );
		$this->assertSame( [ 'x' => [ 'a' => 11, 'b' => 22 ] ], $state->getData() );

		$state->addData( 'x', [ 'c' => 55, 'b' => 33 ] );
		$state->addData( 'y', [ 'kitten' ] );
		$this->assertSame(
			[ 'x' => [ 'a' => 11, 'b' => 33, 'c' => 55 ], 'y' => [ 'kitten' ] ],
			$state->getData()
		);
	}

	public function testParserOutputPersistence() {
		$output = new ParserOutput();

		$this->assertNull( State::getState( $output ) );

		$state = State::getOrCreate( $output );
		$state->addData( 'test', [ 'foo' => 'bar' ] );

		State::setState( $output, $state );
		$retrieved = State::getState( $output );
		$this->assertEquals( $state, $retrieved );
	}

	public function testParserOutputPersistenceForwardCompatibility() {
		$output = new ParserOutput();

		$state = new State( $output );
		$state->addData( 'test', [ 'foo' => 'bar' ] );

		// Set JSONified state. Should work before we set JSON-serializable data,
		// to be robust against old code reading new data after a rollback.
		$output->setExtensionData( State::DATA_KEY, $state->jsonSerialize() );

		$retrieved = State::getState( $output );
		$this->assertEquals( $state, $retrieved );
	}

	public function testParserOutputPersistenceBackwardCompatibility() {
		$output = new ParserOutput();

		$state = new State( $output );
		$state->addData( 'test', [ 'foo' => 'bar' ] );

		// Set the object directly. Should still work once we normally set JSON-serializable data.
		$output->setExtensionData( State::DATA_KEY, $state );

		$retrieved = State::getState( $output );
		$this->assertEquals( $state, $retrieved );
	}

	public function provideStates() {
		yield 'empty' => [ new State() ];

		$stateWithData = new State();
		$stateWithData->addData( 'test', [ 'foo' => 'bar' ] );
		yield 'with data' => [ $stateWithData ];

		$stateWithCounters = new State();
		$stateWithCounters->setCounters( (object)[ 'x' => 5, 'y' => 7 ] );
		yield 'with counters' => [ $stateWithCounters ];

		$stateWithGroups = new State();
		$stateWithGroups->addRequestedGroups( [ 5 => 'x', 11 => 'y' ] );
		$stateWithGroups->addInteractiveGroups( [ 'a', 'b', 'c' ] );
		yield 'with groups' => [ $stateWithGroups ];

		$stateWithFlags = new State();
		$stateWithFlags->setBrokenTags();
		$stateWithFlags->setValidTags();
		$stateWithFlags->useMapframe();
		$stateWithFlags->useMapframe();
		$stateWithFlags->useMaplink();
		$stateWithFlags->useMaplink();
		$stateWithFlags->useMaplink();
		yield 'with flags' => [ $stateWithFlags ];
	}

	/**
	 * @dataProvider provideStates
	 */
	public function testParserOutputPersistenceRoundTrip( State $state ) {
		$output = new ParserOutput();
		State::setState( $output, $state );

		$this->assertEquals( $state, State::getState( $output ) );
	}

	/**
	 * @dataProvider provideStates
	 */
	public function testJsonRoundTrip( State $state ) {
		$jsonData = $state->jsonSerialize();

		$json = json_encode( $jsonData );
		$this->assertIsString( $json );
		$this->assertIsArray( json_decode( $json, true ) );

		$decoded = State::newFromJson( $jsonData );
		$this->assertEquals( $state, $decoded );
	}

}
