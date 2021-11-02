<?php

namespace Kartographer\Tests;

use ApiTestCase;
use CommentStoreComment;
use MediaWiki\Storage\SlotRecord;
use WikitextContent;

/**
 * @group API
 * @group Database
 * @group medium
 * @covers \Kartographer\ApiQueryMapData
 */
class ApiQueryMapDataTest extends ApiTestCase {

	// phpcs:disable Generic.Files.LineLength
	private const MAPFRAME_CONTENT = '<mapframe latitude=0 longitude=0 width=1 height=1>{"type": "Feature","geometry": {"type": "Point","coordinates": [1, 2]}}</mapframe>';
	private const MAPFRAME_JSON = [ '{"_1b3d2dce6411896528c219bf0af1e6c4c3985a1a":[{"type":"Feature","geometry":{"type":"Point","coordinates":[1,2]}}]}' ];
	// phpcs:enable

	public function testExecuteMissingPage() {
		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => __METHOD__,
		] );

		$this->assertTrue( $apiResult[0]['query']['pages'][-1]['missing'] );
	}

	/**
	 * @dataProvider executeWithTitleProvider
	 */
	public function testExecuteWithTitle( $content, $expectedData ) {
		$page = $this->getExistingTestPage( __METHOD__ );
		$this->addRevision( $page, $content );

		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'titles' => $page->getDBkey(),
		] );

		$this->assertResult( $expectedData, $apiResult );
	}

	public function executeWithTitleProvider() {
		return [
			'No mapframe' => [ 'Nope', [ null ] ],
			'Empty mapframe' => [ '<mapframe latitude=0 longitude=0 width=1 height=1 />', [ [ '[]' ] ] ],
			'Map with features' => [ self::MAPFRAME_CONTENT, [ self::MAPFRAME_JSON ] ],
		];
	}

	public function testExecuteWithRevisionId() {
		$page = $this->getExistingTestPage( __METHOD__ );
		$prevRevision = $this->addRevision( $page, self::MAPFRAME_CONTENT );
		$currentRevision = $this->addRevision( $page, '<mapframe latitude=0 longitude=0 width=1 height=1 />' );

		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $currentRevision->getId(),
		] );
		$this->assertResult( [ [ '[]' ] ], $apiResult );

		// using an old revision still just returns the data from the current revision
		$apiResult = $this->doApiRequest( [
			'action' => 'query',
			'prop' => 'mapdata',
			'revids' => $prevRevision->getId(),
		] );
		$this->assertResult( [ [ '[]' ] ], $apiResult );
	}

	private function addRevision( $page, $content ) {
		return $page->newPageUpdater( $this->getTestUser()->getUser() )
			->setContent( SlotRecord::MAIN, new WikitextContent( $content ) )
			->saveRevision( CommentStoreComment::newUnsavedComment( __CLASS__ ) );
	}

	private function assertResult( array $expectedMapdata, $apiResult ) {
		$i = 0;
		foreach ( $apiResult[0]['query']['pages'] as $page ) {
			if ( $expectedMapdata[$i] !== null ) {
				$this->assertArrayHasKey( 'mapdata', $page );
				$this->assertArrayEquals( $expectedMapdata[$i], $page['mapdata'] );
			} else {
				$this->assertArrayNotHasKey( 'mapdata', $page );
			}
			$i++;
		}

		$this->assertSame( count( $expectedMapdata ), $i, 'number of results as expected' );
	}
}
