<?php

namespace Kartographer\Tests\Tag;

use Kartographer\State;
use Kartographer\Tag\TagHandler;

/**
 * @license MIT
 * @author Thiemo Kreuz
 */
trait TagHandlerTest {
	use \PHPUnit4And6Compat;

	/**
	 * @covers \Kartographer\Tag\TagHandler::finalParseStep
	 */
	public function testFinalParseStep() {
		$state = new State();
		$state->addRequestedGroups( [ 'group1' ] );
		$state->addRequestedGroups( [ 'group2' ] );

		$output = $this->createMock( \ParserOutput::class );
		$output->expects( $this->once() )
			->method( 'addJsConfigVars' )
			->with(
				'wgKartographerLiveData',
				(object)[
					'group1' => [],
					'group2' => [],
				]
			);

		TagHandler::finalParseStep(
			$state,
			$output,
			false,
			$this->createMock( \Title::class )
		);
	}

}
