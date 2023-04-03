<?php

namespace Kartographer\UnitTests;

use Kartographer\ParserFunctionTracker;
use Kartographer\State;
use Kartographer\Tag\LegacyTagHandler;
use MediaWikiUnitTestCase;
use stdClass;

/**
 * @license MIT
 */
class LegacyTagHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \Kartographer\Tag\LegacyTagHandler::finalParseStep
	 * @dataProvider groupsProvider
	 */
	public function testFinalParseStep( ?array $data, array $groupTypes, bool $isPreview, stdClass $expected ) {
		$state = new State();

		if ( $data ) {
			$state->addData( $data['groupId'], $data['geometries'] );
		}

		foreach ( $groupTypes as $type => $groups ) {
			foreach ( $groups as $group ) {
				if ( $type == 'requested' ) {
					$state->addRequestedGroups( $group );
				} elseif ( $type == 'interactive' ) {
					$state->addInteractiveGroups( $group );
				}
			}
		}

		$output = $this->createMock( \ParserOutput::class );
		$output->expects( $this->once() )
			->method( 'setJsConfigVar' )
			->with( 'wgKartographerLiveData', $expected );

		$tracker = $this->createMock( ParserFunctionTracker::class );
		$tracker->expects( $this->once() )
			->method( 'addTrackingCategories' )
			->with( [
				'kartographer-broken-category' => false,
				'kartographer-tracking-category' => false,
			] );

		LegacyTagHandler::finalParseStep( $state, $output, $isPreview, $tracker );
	}

	public function groupsProvider() {
		yield 'test requested groups with isPreview false' => [
				'data' => null,
				'groups' => [
					'requested' => [
						[ 'group1' ],
						[ 'group2' ],
					],
				],
				'isPreview' => false,
				'expected' => (object)[
					'group1' => [],
					'group2' => [],
				]
			];

		yield 'test requested and interactive groups with isPreview false' => [
			'data' => [
				'groupId' => 'group3',
				'geometries' => []
			],
			'groups' => [
				'requested' => [
					[ 'group1' ],
					[ 'group2' ],
				],
				'interactive' => [
					[ 'group3' ],
					[ 'group4' ],
				],
			],
			'isPreview' => false,
			'expected' => (object)[
				'group1' => [],
				'group2' => [],
				'group3' => [],
			]
		];
	}

}
