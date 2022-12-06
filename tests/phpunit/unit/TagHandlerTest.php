<?php

namespace Kartographer\UnitTests;

use Kartographer\State;
use Kartographer\Tag\ParserFunctionTracker;
use Kartographer\Tag\TagHandler;
use MediaWikiUnitTestCase;
use Parser;
use stdClass;

/**
 * @license MIT
 */
class TagHandlerTest extends MediaWikiUnitTestCase {

	/**
	 * @covers \Kartographer\Tag\TagHandler::finalParseStep
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
			->with(
				'wgKartographerLiveData',
				$expected
			);

		TagHandler::finalParseStep(
			$state,
			$output,
			$isPreview,
			new ParserFunctionTracker( $this->createMock( Parser::class ) )
		);
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
