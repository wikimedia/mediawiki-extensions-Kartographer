<?php

namespace Kartographer\UnitTests;

use Kartographer\State;
use Kartographer\Tag\LegacyMapFrame;
use Kartographer\Tag\LegacyMapLink;
use MediaWiki\Parser\ParserOutput;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Kartographer\State
 * @group Kartographer
 * @license MIT
 */
class StateTest extends MediaWikiUnitTestCase {

	public function testValidTags() {
		$state = new State();
		$this->assertFalse( $state->hasValidTags() );

		$state->incrementUsage( LegacyMapLink::TAG );
		$this->assertTrue( $state->hasValidTags() );
	}

	public function testBrokenTags() {
		$state = new State();
		$this->assertFalse( $state->hasBrokenTags() );

		$state->incrementBrokenTags();
		$this->assertTrue( $state->hasBrokenTags() );
	}

	public function testMapLinks() {
		$state = new State();
		$this->assertSame( [], $state->getUsages() );

		$state->incrementUsage( LegacyMapLink::TAG );
		$this->assertSame( 1, $state->getUsages()['maplinks'] );

		$state->incrementUsage( LegacyMapLink::TAG );
		$this->assertSame( 2, $state->getUsages()['maplinks'] );
	}

	public function testMapframes() {
		$state = new State();
		$this->assertSame( [], $state->getUsages() );

		$state->incrementUsage( LegacyMapFrame::TAG );
		$this->assertSame( 1, $state->getUsages()['mapframes'] );

		$state->incrementUsage( LegacyMapFrame::TAG );
		$this->assertSame( 2, $state->getUsages()['mapframes'] );
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
		$this->assertSame( [ 'a', 'b' ], $state->getRequestedGroups() );

		$state->addRequestedGroups( [ 11 => 'b', 22 => 'c' ] );
		$this->assertSame( [ 'a', 'b', 'c' ], $state->getRequestedGroups() );
	}

	public function testCounters() {
		$state = new State();
		$this->assertEquals( [], $state->getCounters() );

		$state->setCounters( [ 'a' => 11, 'b' => 22 ] );
		$this->assertEquals( [ 'a' => 11, 'b' => 22 ], $state->getCounters() );

		$state->setCounters( [ 'b' => 33, 'c' => 55 ] );
		$this->assertEquals( [ 'b' => 33, 'c' => 55 ], $state->getCounters() );
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

		$state = new State();
		$state->addData( 'test', [ 'foo' => 'bar' ] );

		// Set JSONified state. Should work before we set JSON-serializable data,
		// to be robust against old code reading new data after a rollback.
		$output->setExtensionData( State::DATA_KEY, $state->jsonSerialize() );

		$retrieved = State::getState( $output );
		$this->assertEquals( $state, $retrieved );
	}

	public function testParserOutputPersistenceBackwardCompatibility() {
		$output = new ParserOutput();

		$state = new State();
		$state->addData( 'test', [ 'foo' => 'bar' ] );

		// Set the object directly. Should still work once we normally set JSON-serializable data.
		$output->setExtensionData( State::DATA_KEY, $state );

		$retrieved = State::getState( $output );
		$this->assertEquals( $state, $retrieved );
	}

	public static function provideStates() {
		yield 'empty' => [ new State() ];

		$stateWithData = new State();
		$stateWithData->addData( 'test', [ 'foo' => 'bar' ] );
		yield 'with data' => [ $stateWithData ];

		$stateWithCounters = new State();
		$stateWithCounters->setCounters( [ 'x' => 5, 'y' => 7 ] );
		yield 'with counters' => [ $stateWithCounters ];

		$stateWithGroups = new State();
		$stateWithGroups->addRequestedGroups( [ 5 => 'x', 11 => 'y' ] );
		$stateWithGroups->addInteractiveGroups( [ 'a', 'b', 'c' ] );
		yield 'with groups' => [ $stateWithGroups ];

		$stateWithFlags = new State();
		$stateWithFlags->incrementBrokenTags();
		$stateWithFlags->incrementUsage( LegacyMapFrame::TAG );
		$stateWithFlags->incrementUsage( LegacyMapFrame::TAG );
		$stateWithFlags->incrementUsage( LegacyMapLink::TAG );
		$stateWithFlags->incrementUsage( LegacyMapLink::TAG );
		$stateWithFlags->incrementUsage( LegacyMapLink::TAG );
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

	public function testJsonStability() {
		$state = new State();
		$state->incrementUsage( LegacyMapLink::TAG );
		$state->incrementUsage( LegacyMapFrame::TAG );
		$state->addInteractiveGroups( [ 'interactive' ] );
		$state->addRequestedGroups( [ 'requested' ] );
		// This intentionally breaks when unexpected changes are made to the JSON serialization
		$this->assertEquals( [
			'broken' => 0,
			'maplinks' => 1,
			'mapframes' => 1,
			// FIXME: Why do we store flipped arrays with meaningless values in the parser cache?
			'interactiveGroups' => [ 'interactive' => 0 ],
			'requestedGroups' => [ 'requested' => 0 ],
			'counters' => null,
			'data' => [],
		], $state->jsonSerialize() );
	}

	/**
	 * @dataProvider provideStates
	 */
	public function testJsonRoundTrip( State $state ) {
		$jsonData = $state->jsonSerialize();

		$json = json_encode( $jsonData );
		$this->assertIsString( $json );
		$this->assertIsArray( json_decode( $json, true ) );

		/** @var State $class */
		$class = TestingAccessWrapper::newFromClass( State::class );
		$decoded = $class->newFromJson( $jsonData );
		$this->assertEquals( $state, $decoded );
	}

}
